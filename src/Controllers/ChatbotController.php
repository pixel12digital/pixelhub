<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Auth;
use PixelHub\Services\ChatbotFlowService;
use PixelHub\Services\MetaTemplateService;

/**
 * Controller para gerenciar fluxos de automação do chatbot
 * 
 * Rotas:
 * - GET /chatbot/flows - Lista fluxos
 * - GET /chatbot/flows/create - Formulário de criação
 * - POST /chatbot/flows/create - Cria fluxo
 * - GET /chatbot/flows/edit - Formulário de edição
 * - POST /chatbot/flows/update - Atualiza fluxo
 * - POST /chatbot/flows/delete - Deleta fluxo
 * - POST /chatbot/flows/toggle - Ativa/desativa fluxo
 * 
 * Data: 2026-03-04
 */
class ChatbotController
{
    /**
     * Lista todos os fluxos
     * 
     * GET /chatbot/flows
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $triggerType = $_GET['trigger_type'] ?? null;
        $isActive = isset($_GET['is_active']) ? (bool) $_GET['is_active'] : null;
        
        $flows = ChatbotFlowService::listFlows($tenantId, $triggerType, $isActive);
        
        require_once __DIR__ . '/../../views/chatbot/flows/index.php';
    }
    
    /**
     * Exibe formulário de criação de fluxo
     * 
     * GET /chatbot/flows/create
     */
    public function create(): void
    {
        Auth::requireInternal();
        
        // Busca templates aprovados para seleção
        $templates = MetaTemplateService::listTemplates(null, 'approved');
        
        require_once __DIR__ . '/../../views/chatbot/flows/create.php';
    }
    
    /**
     * Processa criação de fluxo
     * 
     * POST /chatbot/flows/create
     */
    public function store(): void
    {
        Auth::requireInternal();
        
        try {
            $data = [
                'tenant_id' => isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null,
                'name' => trim($_POST['name'] ?? ''),
                'trigger_type' => $_POST['trigger_type'] ?? 'template_button',
                'trigger_value' => trim($_POST['trigger_value'] ?? ''),
                'response_type' => $_POST['response_type'] ?? 'text',
                'response_message' => trim($_POST['response_message'] ?? '') ?: null,
                'response_template_id' => isset($_POST['response_template_id']) && $_POST['response_template_id'] !== '' ? (int) $_POST['response_template_id'] : null,
                'response_media_url' => trim($_POST['response_media_url'] ?? '') ?: null,
                'response_media_type' => $_POST['response_media_type'] ?? null,
                'forward_to_human' => isset($_POST['forward_to_human']) ? 1 : 0,
                'assign_to_user_id' => isset($_POST['assign_to_user_id']) && $_POST['assign_to_user_id'] !== '' ? (int) $_POST['assign_to_user_id'] : null,
                'update_lead_status' => trim($_POST['update_lead_status'] ?? '') ?: null,
                'priority' => isset($_POST['priority']) ? (int) $_POST['priority'] : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // Processa botões
            $nextButtons = [];
            if (!empty($_POST['next_buttons'])) {
                $buttonsData = is_string($_POST['next_buttons']) ? json_decode($_POST['next_buttons'], true) : $_POST['next_buttons'];
                if (is_array($buttonsData)) {
                    $nextButtons = $buttonsData;
                }
            }
            $data['next_buttons'] = $nextButtons;
            
            // Processa tags
            $tags = [];
            if (!empty($_POST['add_tags'])) {
                $tagsData = is_string($_POST['add_tags']) ? explode(',', $_POST['add_tags']) : $_POST['add_tags'];
                if (is_array($tagsData)) {
                    $tags = array_map('trim', $tagsData);
                }
            }
            $data['add_tags'] = $tags;
            
            $flowId = ChatbotFlowService::create($data);
            
            $_SESSION['success'] = 'Fluxo criado com sucesso!';
            header('Location: ' . pixelhub_url('/chatbot/flows?id=' . $flowId));
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao criar fluxo: ' . $e->getMessage();
            header('Location: ' . pixelhub_url('/chatbot/flows/create'));
            exit;
        }
    }
    
    /**
     * Exibe formulário de edição de fluxo
     * 
     * GET /chatbot/flows/edit
     */
    public function edit(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $flow = ChatbotFlowService::getById($id);
        
        if (!$flow) {
            $_SESSION['error'] = 'Fluxo não encontrado';
            header('Location: ' . pixelhub_url('/chatbot/flows'));
            exit;
        }
        
        // Busca templates aprovados para seleção
        $templates = MetaTemplateService::listTemplates(null, 'approved');
        
        require_once __DIR__ . '/../../views/chatbot/flows/edit.php';
    }
    
    /**
     * Processa atualização de fluxo
     * 
     * POST /chatbot/flows/update
     */
    public function update(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $flow = ChatbotFlowService::getById($id);
            if (!$flow) {
                throw new \Exception('Fluxo não encontrado');
            }
            
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'trigger_type' => $_POST['trigger_type'] ?? 'template_button',
                'trigger_value' => trim($_POST['trigger_value'] ?? ''),
                'response_type' => $_POST['response_type'] ?? 'text',
                'response_message' => trim($_POST['response_message'] ?? '') ?: null,
                'response_template_id' => isset($_POST['response_template_id']) && $_POST['response_template_id'] !== '' ? (int) $_POST['response_template_id'] : null,
                'response_media_url' => trim($_POST['response_media_url'] ?? '') ?: null,
                'response_media_type' => $_POST['response_media_type'] ?? null,
                'forward_to_human' => isset($_POST['forward_to_human']) ? 1 : 0,
                'assign_to_user_id' => isset($_POST['assign_to_user_id']) && $_POST['assign_to_user_id'] !== '' ? (int) $_POST['assign_to_user_id'] : null,
                'update_lead_status' => trim($_POST['update_lead_status'] ?? '') ?: null,
                'priority' => isset($_POST['priority']) ? (int) $_POST['priority'] : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            // Processa botões
            if (!empty($_POST['next_buttons'])) {
                $buttonsData = is_string($_POST['next_buttons']) ? json_decode($_POST['next_buttons'], true) : $_POST['next_buttons'];
                if (is_array($buttonsData)) {
                    $data['next_buttons'] = $buttonsData;
                }
            }
            
            // Processa tags
            if (!empty($_POST['add_tags'])) {
                $tagsData = is_string($_POST['add_tags']) ? explode(',', $_POST['add_tags']) : $_POST['add_tags'];
                if (is_array($tagsData)) {
                    $data['add_tags'] = array_map('trim', $tagsData);
                }
            }
            
            ChatbotFlowService::update($id, $data);
            
            $_SESSION['success'] = 'Fluxo atualizado com sucesso!';
            header('Location: ' . pixelhub_url('/chatbot/flows?id=' . $id));
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao atualizar fluxo: ' . $e->getMessage();
            $id = $_POST['id'] ?? 0;
            header('Location: ' . pixelhub_url('/chatbot/flows/edit?id=' . $id));
            exit;
        }
    }
    
    /**
     * Ativa/desativa fluxo
     * 
     * POST /chatbot/flows/toggle
     */
    public function toggle(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $flow = ChatbotFlowService::getById($id);
            
            if (!$flow) {
                throw new \Exception('Fluxo não encontrado');
            }
            
            $newStatus = $flow['is_active'] ? 0 : 1;
            ChatbotFlowService::update($id, ['is_active' => $newStatus]);
            
            $_SESSION['success'] = 'Status do fluxo atualizado!';
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao atualizar status: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/chatbot/flows'));
        exit;
    }
    
    /**
     * Deleta fluxo
     * 
     * POST /chatbot/flows/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();
        
        try {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            
            $success = ChatbotFlowService::delete($id);
            
            if ($success) {
                $_SESSION['success'] = 'Fluxo deletado com sucesso!';
            } else {
                $_SESSION['error'] = 'Não foi possível deletar o fluxo.';
            }
            
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Erro ao deletar fluxo: ' . $e->getMessage();
        }
        
        header('Location: ' . pixelhub_url('/chatbot/flows'));
        exit;
    }
    
    /**
     * Visualiza detalhes de um fluxo
     * 
     * GET /chatbot/flows/view
     */
    public function view(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $flow = ChatbotFlowService::getById($id);
        
        if (!$flow) {
            $_SESSION['error'] = 'Fluxo não encontrado';
            header('Location: ' . pixelhub_url('/chatbot/flows'));
            exit;
        }
        
        require_once __DIR__ . '/../../views/chatbot/flows/view.php';
    }
    
    /**
     * Testa execução de um fluxo
     * 
     * POST /chatbot/flows/test
     */
    public function test(): void
    {
        Auth::requireInternal();
        
        header('Content-Type: application/json');
        
        try {
            $flowId = isset($_POST['flow_id']) ? (int) $_POST['flow_id'] : 0;
            $conversationId = isset($_POST['conversation_id']) ? (int) $_POST['conversation_id'] : 0;
            $context = isset($_POST['context']) ? json_decode($_POST['context'], true) : [];
            
            $result = ChatbotFlowService::executeFlow($flowId, $conversationId, $context);
            
            echo json_encode($result);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao testar fluxo: ' . $e->getMessage()
            ]);
        }
        
        exit;
    }
}
