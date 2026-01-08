<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Services\ServiceChatService;
use PixelHub\Services\BusinessCardIntakeService;
use PixelHub\Services\BusinessCardGeneratorService;
use PixelHub\Services\ServiceOrderService;
use PixelHub\Controllers\AIOrchestratorController;

/**
 * Controller para chat vinculado a pedidos de serviço
 * 
 * Regra: O chat sempre precisa estar amarrado a um pedido (order_id).
 */
class ChatController extends Controller
{
    /**
     * Exibe o chat vinculado a um pedido
     * 
     * GET /chat/order/{orderId} ou /chat/order?token={token}
     */
    public function show(): void
    {
        $orderId = $_GET['order_id'] ?? null;
        $token = $_GET['token'] ?? null;
        
        // Se veio token, busca pedido por token
        if ($token) {
            $order = ServiceOrderService::findOrderByToken($token);
            if (!$order) {
                die('Pedido não encontrado ou link expirado.');
            }
            $orderId = $order['id'];
        } else {
            $orderId = (int) $orderId;
        }
        
        if (empty($orderId)) {
            die('ID do pedido é obrigatório.');
        }
        
        // Busca pedido
        $order = ServiceOrderService::findOrder($orderId);
        if (!$order) {
            die('Pedido não encontrado.');
        }
        
        // Verifica se pedido está ativo
        if (!in_array($order['status'], ['active', 'in_progress', 'awaiting_payment'])) {
            // Se não estiver ativo, tenta abrir chat mesmo assim (pode estar em draft)
        }
        
        // Garante que existe thread para este pedido
        $threadId = ServiceChatService::ensureThreadForOrder($orderId);
        
        // Busca thread
        $thread = ServiceChatService::findThread($threadId);
        if (!$thread) {
            die('Erro ao criar thread de chat.');
        }
        
        // Busca mensagens
        $messages = ServiceChatService::getMessages($threadId);
        
        // Busca intake se existir
        $intake = BusinessCardIntakeService::findIntakeByOrder($orderId);
        $intakeData = $intake ? json_decode($intake['data_json'], true) : [];
        
        // Busca deliverables se existirem
        $deliverables = [];
        if (in_array($order['status'], ['delivered', 'in_progress'])) {
            $deliverables = BusinessCardGeneratorService::getDeliverables($orderId);
        }
        
        // Renderiza view
        $this->view('chat.show', [
            'order' => $order,
            'thread' => $thread,
            'messages' => $messages,
            'intake' => $intake,
            'intakeData' => $intakeData,
            'deliverables' => $deliverables,
            'currentStep' => $thread['current_step'] ?? 'step_0_welcome'
        ]);
    }
    
    /**
     * Processa mensagem do usuário no chat
     * 
     * POST /chat/message
     */
    public function sendMessage(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $threadId = !empty($input['thread_id']) ? (int) $input['thread_id'] : null;
        $message = trim($input['message'] ?? '');
        
        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'Thread ID é obrigatório'], 400);
            return;
        }
        
        if (empty($message)) {
            $this->json(['success' => false, 'error' => 'Mensagem é obrigatória'], 400);
            return;
        }
        
        // Busca thread
        $thread = ServiceChatService::findThread($threadId);
        if (!$thread) {
            $this->json(['success' => false, 'error' => 'Thread não encontrado'], 404);
            return;
        }
        
        // Adiciona mensagem do usuário
        ServiceChatService::addMessage($threadId, 'user', $message);
        
        // Atualiza status do thread
        ServiceChatService::updateStatus($threadId, 'waiting_ai');
        
        // Busca intake atual
        $intake = BusinessCardIntakeService::findIntakeByOrder($thread['order_id']);
        $currentIntakeData = $intake ? json_decode($intake['data_json'], true) : [];
        
        // Busca histórico de mensagens
        $history = ServiceChatService::getMessages($threadId, 20);
        $conversationHistory = array_map(function($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }, $history);
        
        // Determina step atual
        $currentStep = $thread['current_step'] ?? 'step_0_welcome';
        
        // Processa mensagem usando IA
        $aiController = new AIOrchestratorController();
        
        // Prepara dados para o orchestrator
        $formData = [
            'client' => [
                'name' => $currentIntakeData['full_name'] ?? '',
                'email' => $currentIntakeData['email'] ?? '',
                'phone' => $currentIntakeData['phone_whatsapp'] ?? '',
                'cpf_cnpj' => ''
            ]
        ];
        
        // Chama orchestrator para analisar mensagem
        $analysis = $this->analyzeMessage($message, $conversationHistory, $formData, $currentStep, 'business_card_express');
        
        // Extrai dados do step atual se necessário
        $extractedData = [];
        if (!empty($analysis['extractedFields']) && is_array($analysis['extractedFields'])) {
            // Extrai dados específicos do step
            $stepExtracted = BusinessCardIntakeService::extractDataFromStep(
                $currentStep,
                $message,
                $currentIntakeData
            );
            
            // Merge com dados extraídos pela IA
            $extractedData = array_merge($stepExtracted, $analysis['extractedFields']);
        }
        
        // Atualiza intake com dados extraídos
        if (!empty($extractedData)) {
            BusinessCardIntakeService::updateIntake($thread['order_id'], $extractedData);
        }
        
        // Gera resposta da IA
        $aiResponse = $analysis['response'] ?? 'Entendi!';
        
        // Adiciona mensagem da IA
        $aiMessageId = ServiceChatService::addMessage(
            $threadId,
            'assistant',
            $aiResponse,
            [
                'analysis' => $analysis,
                'extracted_fields' => $extractedData,
                'step' => $currentStep
            ]
        );
        
        // Determina próximo step se necessário
        $nextStep = $this->determineNextStep($currentStep, $currentIntakeData, $extractedData);
        if ($nextStep !== $currentStep) {
            ServiceChatService::updateStep($threadId, $nextStep);
            $currentStep = $nextStep;
        }
        
        // Atualiza status do thread
        ServiceChatService::updateStatus($threadId, 'waiting_user');
        
        // Se chegou no step de confirmação (step_6), verifica se pode gerar
        if ($nextStep === 'step_6_confirmation') {
            $updatedIntake = BusinessCardIntakeService::findIntakeByOrder($thread['order_id']);
            if ($updatedIntake && $updatedIntake['is_valid']) {
                // Formata dados finais
                $finalData = BusinessCardIntakeService::formatFinalDataJson(
                    json_decode($updatedIntake['data_json'], true)
                );
                BusinessCardIntakeService::updateIntake($thread['order_id'], $finalData);
            }
        }
        
        // Se chegou no step de geração (step_7), inicia geração
        if ($nextStep === 'step_7_generation') {
            try {
                BusinessCardGeneratorService::generateBusinessCard($thread['order_id']);
            } catch (\Exception $e) {
                error_log('[ChatController] Erro ao iniciar geração: ' . $e->getMessage());
            }
        }
        
        // Retorna resposta
        $this->json([
            'success' => true,
            'message' => [
                'id' => $aiMessageId,
                'role' => 'assistant',
                'content' => $aiResponse,
                'created_at' => date('Y-m-d H:i:s')
            ],
            'next_step' => $nextStep,
            'analysis' => $analysis
        ]);
    }
    
    /**
     * Analisa mensagem usando AIOrchestratorController
     */
    private function analyzeMessage(string $message, array $history, array $formData, string $currentStep, string $serviceType): array
    {
        // Simula chamada ao AIOrchestratorController
        // Por enquanto, usa lógica básica
        
        // Em produção, chamaria diretamente o método do orchestrator
        // Por enquanto, faz análise básica
        
        $lowerMessage = strtolower($message);
        
        // Detecta confirmação
        if (preg_match('/(sim|ok|confirmo|está certo|correto|pode gerar)/i', $lowerMessage)) {
            return [
                'intention' => 'confirmar',
                'action' => 'accept',
                'response' => 'Ótimo! Vou gerar seu cartão agora.',
                'extractedFields' => []
            ];
        }
        
        // Extração básica (o orchestrator faria melhor)
        $extracted = [];
        
        // Email
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $message, $matches)) {
            $extracted['email'] = strtolower(trim($matches[0]));
        }
        
        // Telefone
        if (preg_match('/(\+?55\s?)?(\(?\d{2}\)?\s?)(\d{4,5}-?\d{4})/', $message, $matches)) {
            $phone = preg_replace('/[^0-9+]/', '', $matches[0]);
            if (strpos($phone, '+55') === false && strlen($phone) >= 10) {
                $phone = '+55' . $phone;
            }
            $extracted['phone_whatsapp'] = $phone;
        }
        
        return [
            'intention' => 'informar_dado',
            'action' => 'accept',
            'response' => 'Obrigado pela informação!',
            'extractedFields' => $extracted
        ];
    }
    
    /**
     * Determina próximo step baseado no step atual e dados coletados
     */
    private function determineNextStep(string $currentStep, array $currentData, array $extractedData): string
    {
        $mergedData = array_merge($currentData, $extractedData);
        
        switch ($currentStep) {
            case 'step_0_welcome':
                return 'step_1_identity';
                
            case 'step_1_identity':
                if (!empty($mergedData['full_name'])) {
                    return 'step_2_contacts';
                }
                return $currentStep;
                
            case 'step_2_contacts':
                if (!empty($mergedData['phone_whatsapp']) || !empty($mergedData['email'])) {
                    return 'step_3_style';
                }
                return $currentStep;
                
            case 'step_3_style':
                if (!empty($mergedData['style'])) {
                    return 'step_4_logo_assets';
                }
                return $currentStep;
                
            case 'step_4_logo_assets':
                // Logo é opcional, pode pular
                return 'step_5_qr';
                
            case 'step_5_qr':
                // QR é opcional, mas já pergunta
                return 'step_6_confirmation';
                
            case 'step_6_confirmation':
                // Aguarda confirmação do usuário
                return $currentStep;
                
            case 'step_7_generation':
                return 'step_8_delivery';
                
            case 'step_8_delivery':
                return $currentStep;
                
            default:
                return $currentStep;
        }
    }
    
    /**
     * Busca mensagens do thread (AJAX)
     * 
     * GET /chat/messages?thread_id={id}
     */
    public function getMessages(): void
    {
        header('Content-Type: application/json');
        
        $threadId = !empty($_GET['thread_id']) ? (int) $_GET['thread_id'] : null;
        
        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'Thread ID é obrigatório'], 400);
            return;
        }
        
        $messages = ServiceChatService::getMessages($threadId);
        
        $this->json([
            'success' => true,
            'messages' => $messages
        ]);
    }
}

