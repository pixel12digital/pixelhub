<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\ChatbotFlowService;
use PixelHub\Services\ConversationService;

/**
 * Controller para receber webhooks da Meta Official API
 * 
 * Endpoint: POST /api/whatsapp/meta/webhook
 * Documentação: https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks
 */
class MetaWebhookController extends Controller
{
    /**
     * Processa webhook do Meta
     * 
     * Rota: POST /api/whatsapp/meta/webhook
     * Rota: GET /api/whatsapp/meta/webhook (verificação inicial)
     */
    public function handle(): void
    {
        // Limpa output buffer
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        // GET: Verificação de webhook (Meta envia na configuração inicial)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleVerification();
            return;
        }

        // POST: Webhook de evento
        try {
            $rawPayload = file_get_contents('php://input');
            $payload = json_decode($rawPayload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON payload',
                    'code' => 'INVALID_JSON'
                ]);
                exit;
            }

            // Log do payload recebido
            error_log('[MetaWebhook] Payload recebido: ' . substr($rawPayload, 0, 500));

            // Valida assinatura do Meta (segurança)
            if (!$this->validateSignature($rawPayload)) {
                error_log('[MetaWebhook] Assinatura inválida - possível tentativa de ataque');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid signature',
                    'code' => 'INVALID_SIGNATURE'
                ]);
                exit;
            }

            // Persiste payload bruto para auditoria e obtém o ID
            $webhookLogId = $this->persistRawPayload($payload);

            // Processa cada entrada do webhook
            $processed = 0;
            foreach ($payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if ($this->processChange($change, $entry, $webhookLogId)) {
                        $processed++;
                    }
                }
            }
            
            // Marca webhook como processado se houve sucesso
            if ($processed > 0 && $webhookLogId) {
                $this->markWebhookAsProcessed($webhookLogId);
            }

            // Responde 200 para o Meta
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'processed' => $processed
            ]);

            // Finaliza requisição (permite processamento async)
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro: ' . $e->getMessage());
            error_log('[MetaWebhook] Stack trace: ' . $e->getTraceAsString());

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal error',
                'code' => 'INTERNAL_ERROR'
            ]);
        }
    }

    /**
     * Processa verificação inicial do webhook (GET)
     */
    private function handleVerification(): void
    {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';

        error_log('[MetaWebhook] Verificação: mode=' . $mode . ', token=' . substr($token, 0, 10) . '...');

        // Busca verify token configurado (pode estar em qualquer tenant)
        $expectedToken = $this->getVerifyToken();

        if ($mode === 'subscribe' && $token === $expectedToken) {
            error_log('[MetaWebhook] Verificação bem-sucedida');
            http_response_code(200);
            echo $challenge;
        } else {
            error_log('[MetaWebhook] Verificação falhou - token incorreto');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid verify token']);
        }
    }

    /**
     * Obtém verify token configurado
     */
    private function getVerifyToken(): ?string
    {
        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT meta_webhook_verify_token 
                FROM whatsapp_provider_configs 
                WHERE provider_type = 'meta_official' 
                AND meta_webhook_verify_token IS NOT NULL 
                AND meta_webhook_verify_token != ''
                LIMIT 1
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['meta_webhook_verify_token'] ?? null;
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao buscar verify token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Valida assinatura do webhook Meta
     */
    private function validateSignature(string $payload): bool
    {
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        
        if (empty($signature)) {
            // Em desenvolvimento, pode não ter assinatura
            error_log('[MetaWebhook] AVISO: Webhook sem assinatura (desenvolvimento?)');
            return true; // TODO: Tornar obrigatório em produção
        }

        // TODO: Implementar validação de assinatura quando tiver App Secret
        // $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
        // return hash_equals($expectedSignature, $signature);

        return true;
    }

    /**
     * Persiste payload bruto para auditoria
     * @return int|null ID do webhook inserido
     */
    private function persistRawPayload(array $payload): ?int
    {
        try {
            $db = DB::getConnection();
            $eventType = $this->extractEventType($payload);
            $payloadHash = substr(md5(json_encode($payload)), 0, 16);

            $db->prepare("
                INSERT INTO webhook_raw_logs (event_type, payload_hash, payload_json, processed)
                VALUES (?, ?, ?, 0)
            ")->execute([
                'meta_' . $eventType,
                $payloadHash,
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            ]);
            
            return (int)$db->lastInsertId();
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao persistir payload: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Extrai tipo de evento do payload Meta
     */
    private function extractEventType(array $payload): string
    {
        $field = $payload['entry'][0]['changes'][0]['field'] ?? 'unknown';
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
        
        if (isset($value['messages'])) {
            return 'message';
        }
        if (isset($value['statuses'])) {
            return 'status';
        }
        
        return $field;
    }

    /**
     * Processa uma mudança (change) do webhook
     */
    private function processChange(array $change, array $entry, ?int $webhookLogId = null): bool
    {
        $field = $change['field'] ?? '';
        $value = $change['value'] ?? [];

        // Processa mensagens recebidas
        if ($field === 'messages' && isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                $this->processInboundMessage($message, $value, $entry);
            }
            return true;
        }

        // Processa status de mensagens enviadas
        if ($field === 'messages' && isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                $this->processMessageStatus($status, $value, $entry);
            }
            return true;
        }
        
        // Processa atualizações de status de templates
        if ($field === 'message_template_status_update') {
            $this->processTemplateStatusUpdate($value);
            return true;
        }

        error_log('[MetaWebhook] Campo não processado: ' . $field);
        return false;
    }
    
    /**
     * Marca webhook como processado
     */
    private function markWebhookAsProcessed(int $webhookLogId): void
    {
        try {
            $db = DB::getConnection();
            $db->prepare("UPDATE webhook_raw_logs SET processed = 1 WHERE id = ?")->execute([$webhookLogId]);
            error_log('[MetaWebhook] Webhook ID ' . $webhookLogId . ' marcado como processado');
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao marcar webhook como processado: ' . $e->getMessage());
        }
    }

    /**
     * Processa mensagem inbound (recebida)
     */
    private function processInboundMessage(array $message, array $value, array $entry): void
    {
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        $from = $message['from'] ?? null;
        $messageId = $message['id'] ?? null;
        $timestamp = $message['timestamp'] ?? time();
        $messageType = $message['type'] ?? 'text';

        error_log('[MetaWebhook] Mensagem inbound: from=' . $from . ', id=' . $messageId . ', type=' . $messageType . ', phone_number_id=' . $phoneNumberId);

        // Tenta resolver tenant pelo phone_number_id, mas aceita NULL (configuração global)
        $tenantId = $this->resolveTenantByPhoneNumberId($phoneNumberId);
        
        if ($tenantId === null) {
            error_log('[MetaWebhook] Tenant não encontrado para phone_number_id=' . $phoneNumberId . ', usando configuração global');
        }

        // Normaliza payload para formato interno
        $normalizedPayload = $this->normalizeInboundMessage($message, $value, $entry);

        // Ingere evento no sistema (aceita tenant_id NULL para configuração global)
        try {
            $eventId = EventIngestionService::ingest([
                'event_type' => 'whatsapp.inbound.message',
                'source_system' => 'meta_official',
                'payload' => $normalizedPayload,
                'tenant_id' => $tenantId,
                'process_media_sync' => false,
                'metadata' => [
                    'phone_number_id' => $phoneNumberId,
                    'provider_type' => 'meta_official',
                    'raw_message_id' => $messageId
                ]
            ]);

            error_log('[MetaWebhook] Evento ingerido com sucesso: event_id=' . $eventId);

            // Recupera o conversation_id resolvido pelo EventIngestionService
            // para evitar criar uma conversa duplicada com key diferente
            $resolvedConversationId = null;
            try {
                $db = DB::getConnection();
                $convStmt = $db->prepare('SELECT conversation_id FROM communication_events WHERE event_id = ? LIMIT 1');
                $convStmt->execute([$eventId]);
                $convRow = $convStmt->fetch(\PDO::FETCH_ASSOC);
                $resolvedConversationId = $convRow['conversation_id'] ?? null;
            } catch (\Exception $e) {
                error_log('[MetaWebhook] Erro ao recuperar conversation_id do evento: ' . $e->getMessage());
            }

            // Cancela follow-up se lead respondeu
            if ($resolvedConversationId) {
                try {
                    \PixelHub\Services\ScheduledMessageService::cancelProspectingFollowup(
                        (int) $resolvedConversationId,
                        'vou_analisar_primeiro'
                    );
                } catch (\Exception $e) {
                    error_log('[MetaWebhook] Erro ao cancelar follow-up: ' . $e->getMessage());
                }
            }

            // Processa botão interativo se for o caso
            if ($messageType === 'interactive' || $messageType === 'button') {
                $this->processInteractiveButton($message, $from, $tenantId, $phoneNumberId, $resolvedConversationId);
            }

        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao ingerir evento: ' . $e->getMessage());
            error_log('[MetaWebhook] Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Processa status de mensagem (entregue, lida, etc.)
     */
    private function processMessageStatus(array $status, array $value, array $entry): void
    {
        $messageId = $status['id'] ?? null;
        $statusType = $status['status'] ?? null;

        error_log('[MetaWebhook] Status: message_id=' . $messageId . ', status=' . $statusType);

        // TODO: Atualizar status da mensagem em communication_events
        // Buscar evento pelo message_id e atualizar metadata com status
    }

    /**
     * Resolve tenant pelo Phone Number ID do Meta
     */
    private function resolveTenantByPhoneNumberId(?string $phoneNumberId): ?int
    {
        if (empty($phoneNumberId)) {
            return null;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT tenant_id, is_global
                FROM whatsapp_provider_configs 
                WHERE provider_type = 'meta_official' 
                AND meta_phone_number_id = ? 
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$phoneNumberId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Se não encontrou nenhum registro, retorna null
            if (!$result) {
                error_log('[MetaWebhook] Phone Number ID não encontrado: ' . $phoneNumberId);
                return null;
            }

            // Se é configuração global (tenant_id NULL), retorna null mas é válido
            if ($result['tenant_id'] === null) {
                error_log('[MetaWebhook] Configuração global encontrada para Phone Number ID: ' . $phoneNumberId);
                return null; // null aqui significa "global", não "erro"
            }

            // Configuração específica de tenant
            return (int)$result['tenant_id'];

        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao resolver tenant: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Processa clique em botão interativo e executa fluxo de chatbot
     */
    private function processInteractiveButton(array $message, string $from, ?int $tenantId, ?string $phoneNumberId, ?int $preResolvedConversationId = null): void
    {
        try {
            // DEBUG: Log payload completo
            error_log('[MetaWebhook] Payload do botão: ' . json_encode($message));
            
            // Extrai ID do botão clicado
            $buttonId = null;
            
            // Formato Meta para quick_reply buttons
            if (isset($message['interactive']['button_reply']['id'])) {
                $buttonId = $message['interactive']['button_reply']['id'];
            }
            // Formato alternativo para buttons
            elseif (isset($message['button']['payload'])) {
                $buttonId = $message['button']['payload'];
            }
            
            if (!$buttonId) {
                error_log('[MetaWebhook] Botão interativo sem ID identificável');
                return;
            }
            
            error_log('[MetaWebhook] Botão clicado: ' . $buttonId . ' por ' . $from);
            
            // DEBUG: Log detalhado dos parâmetros exatos
            error_log('[MetaWebhook] DEBUG findByTrigger: triggerType=template_button buttonId=' . json_encode($buttonId) . ' tenantId=' . json_encode($tenantId) . ' strlen=' . strlen($buttonId));
            
            // Busca fluxo correspondente ao botão
            $flow = ChatbotFlowService::findByTrigger('template_button', $buttonId, $tenantId);
            
            if (!$flow) {
                error_log('[MetaWebhook] Nenhum fluxo encontrado para botão: ' . $buttonId);
                return;
            }
            
            error_log('[MetaWebhook] Fluxo encontrado: ' . $flow['name'] . ' (ID: ' . $flow['id'] . ')');
            
            // Usa conversa já resolvida pelo EventIngestionService (evita duplicata)
            // Fallback: resolve novamente se não veio do ingest
            if ($preResolvedConversationId) {
                $conversationId = $preResolvedConversationId;
            } else {
                $conversation = $this->resolveConversation($from, $tenantId, $phoneNumberId);
                if (!$conversation) {
                    error_log('[MetaWebhook] Não foi possível resolver conversa para ' . $from);
                    return;
                }
                $conversationId = $conversation['id'];
            }
            
            // Registra evento de clique no botão
            ChatbotFlowService::logEvent($conversationId, 'button_clicked', [
                'button_id' => $buttonId,
                'flow_id' => $flow['id'],
                'flow_name' => $flow['name']
            ]);
            
            // Executa fluxo de chatbot
            $result = ChatbotFlowService::executeFlow($flow['id'], $conversationId, [
                'phone' => $from,
                'button_id' => $buttonId
            ]);
            
            if ($result['success']) {
                error_log('[MetaWebhook] Fluxo executado com sucesso');
                
                // Envia resposta automática se houver
                if (!empty($result['response']['content'])) {
                    $this->sendAutomatedResponse($from, $result['response'], $phoneNumberId, $conversationId, $tenantId);
                }
                
            } else {
                error_log('[MetaWebhook] Erro ao executar fluxo: ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao processar botão interativo: ' . $e->getMessage());
            error_log('[MetaWebhook] Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Resolve ou cria conversa para um contato
     */
    private function resolveConversation(string $from, ?int $tenantId, ?string $phoneNumberId): ?array
    {
        try {
            // Formata contact_external_id no formato Meta
            $contactExternalId = $from; // Já vem no formato correto (ex: 5511999999999)
            
            // Busca conversa existente
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM conversations
                WHERE contact_external_id = ?
                AND channel_type = 'whatsapp'
                AND provider_type = 'meta_official'
                AND (tenant_id = ? OR (tenant_id IS NULL AND ? IS NULL))
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            
            $stmt->execute([$contactExternalId, $tenantId, $tenantId]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($conversation) {
                return $conversation;
            }
            
            // Cria nova conversa se não existir
            $conversationKey = 'whatsapp_meta_' . $contactExternalId;
            
            $stmt = $db->prepare("
                INSERT INTO conversations (
                    conversation_key,
                    channel_type,
                    channel_id,
                    provider_type,
                    contact_external_id,
                    contact_name,
                    tenant_id,
                    status,
                    is_bot_active,
                    created_at,
                    last_message_at
                ) VALUES (?, 'whatsapp', ?, 'meta_official', ?, NULL, ?, 'open', 1, NOW(), NOW())
            ");
            
            $stmt->execute([
                $conversationKey,
                $phoneNumberId,
                $contactExternalId,
                $tenantId
            ]);
            
            $conversationId = (int) $db->lastInsertId();
            
            error_log('[MetaWebhook] Nova conversa criada: ID=' . $conversationId);
            
            // Retorna conversa recém-criada
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao resolver conversa: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Envia resposta automática do chatbot e registra no Inbox
     */
    private function sendAutomatedResponse(string $to, array $response, ?string $phoneNumberId, ?int $conversationId = null, ?int $tenantId = null): void
    {
        try {
            $content = $response['content'] ?? '';
            $buttons = $response['buttons'] ?? [];
            
            if (empty($content)) {
                error_log('[MetaWebhook] Resposta automática vazia, ignorando envio');
                return;
            }
            
            error_log('[MetaWebhook] Enviando resposta automática para ' . $to . ': ' . substr($content, 0, 100));
            
            // Obtém provider Meta Official API
            $provider = \PixelHub\Services\WhatsAppProviderFactory::getProvider('meta_official');
            
            // Se tem botões, envia mensagem interativa
            if (!empty($buttons) && method_exists($provider, 'sendInteractiveButtons')) {
                error_log('[MetaWebhook] Enviando mensagem interativa com ' . count($buttons) . ' botões');
                $result = $provider->sendInteractiveButtons($to, $content, $buttons);
            } else {
                // Senão, envia mensagem de texto simples
                $result = $provider->sendText($to, $content);
            }
            
            if ($result['success']) {
                $messageId = $result['message_id'] ?? null;
                error_log('[MetaWebhook] Resposta automática enviada com sucesso: message_id=' . ($messageId ?? 'N/A'));
                
                // Registra a resposta do bot no Inbox (communication_events)
                try {
                    $outboundPayload = [
                        'id'       => $messageId,
                        'to'       => preg_replace('/[^0-9]/', '', $to),
                        'text'     => $content,
                        'body'     => $content,
                        'type'     => !empty($buttons) ? 'interactive' : 'text',
                        'fromMe'   => true,
                        'message'  => [
                            'id'     => $messageId,
                            'to'     => preg_replace('/[^0-9]/', '', $to),
                            'body'   => $content,
                            'fromMe' => true,
                        ],
                        '_meta'    => [
                            'phone_number_id' => $phoneNumberId,
                            'chatbot_response' => true,
                        ],
                    ];
                    if (!empty($buttons)) {
                        $outboundPayload['buttons'] = $buttons;
                    }
                    EventIngestionService::ingest([
                        'event_type'         => 'whatsapp.outbound.message',
                        'source_system'      => 'chatbot_flow',
                        'payload'            => $outboundPayload,
                        'tenant_id'          => $tenantId,
                        'process_media_sync' => false,
                        'metadata'           => [
                            'phone_number_id' => $phoneNumberId,
                            'provider_type'   => 'meta_official',
                            'message_id'      => $messageId,
                            'chatbot_flow'    => true,
                        ],
                    ]);
                    error_log('[MetaWebhook] Resposta do bot registrada no Inbox');
                } catch (\Exception $ingestEx) {
                    error_log('[MetaWebhook] Erro ao registrar resposta do bot no Inbox: ' . $ingestEx->getMessage());
                }
            } else {
                error_log('[MetaWebhook] Falha ao enviar resposta automática: ' . ($result['error'] ?? 'Erro desconhecido') . ' | http_code=' . ($result['http_code'] ?? 'N/A') . ' | raw=' . json_encode($result['raw'] ?? []));
            }
            
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao enviar resposta automática: ' . $e->getMessage());
            error_log('[MetaWebhook] Stack trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Normaliza mensagem Meta para formato interno (compatível com WPPConnect)
     */
    private function normalizeInboundMessage(array $message, array $value, array $entry): array
    {
        $type = $message['type'] ?? 'text';
        $from = $message['from'] ?? '';
        $timestamp = $message['timestamp'] ?? time();

        $normalized = [
            'id' => $message['id'] ?? null,
            'from' => $from,
            'timestamp' => $timestamp,
            'type' => $type,
            'message' => [
                'from' => $from,
                'timestamp' => $timestamp,
                'id' => $message['id'] ?? null
            ]
        ];

        // Extrai conteúdo baseado no tipo
        switch ($type) {
            case 'text':
                $normalized['text'] = $message['text']['body'] ?? '';
                $normalized['message']['body'] = $normalized['text'];
                break;

            case 'image':
                $normalized['image'] = $message['image'] ?? [];
                $normalized['caption'] = $message['image']['caption'] ?? null;
                break;

            case 'audio':
                $normalized['audio'] = $message['audio'] ?? [];
                break;

            case 'video':
                $normalized['video'] = $message['video'] ?? [];
                $normalized['caption'] = $message['video']['caption'] ?? null;
                break;

            case 'document':
                $normalized['document'] = $message['document'] ?? [];
                $normalized['caption'] = $message['document']['caption'] ?? null;
                break;
                
            case 'interactive':
            case 'button':
                // Extrai informação do botão clicado
                $buttonReply = $message['interactive']['button_reply'] ?? $message['button'] ?? [];
                $normalized['text'] = $buttonReply['title'] ?? $buttonReply['text'] ?? 'Botão clicado';
                $normalized['button_id'] = $buttonReply['id'] ?? $buttonReply['payload'] ?? null;
                $normalized['message']['body'] = $normalized['text'];
                break;
        }

        // Adiciona metadata do Meta
        $normalized['_meta'] = [
            'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
            'display_phone_number' => $value['metadata']['display_phone_number'] ?? null,
            'profile_name' => $value['contacts'][0]['profile']['name'] ?? null
        ];

        return $normalized;
    }
    
    /**
     * Processa atualização de status de template
     * 
     * @param array $value Dados do webhook
     */
    private function processTemplateStatusUpdate(array $value): void
    {
        $event = $value['event'] ?? null;
        $messageTemplateId = $value['message_template_id'] ?? null;
        $messageTemplateName = $value['message_template_name'] ?? null;
        $messageTemplateLanguage = $value['message_template_language'] ?? null;
        $reason = $value['reason'] ?? null;
        
        error_log('[MetaWebhook] Template status update: ' . json_encode([
            'event' => $event,
            'template_id' => $messageTemplateId,
            'template_name' => $messageTemplateName,
            'language' => $messageTemplateLanguage,
            'reason' => $reason
        ]));
        
        if (!$messageTemplateId || !$event) {
            error_log('[MetaWebhook] Template status update incompleto - ignorando');
            return;
        }
        
        // Busca template no banco pelo meta_template_id
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT id, status 
            FROM whatsapp_message_templates 
            WHERE meta_template_id = ?
            LIMIT 1
        ");
        $stmt->execute([$messageTemplateId]);
        $template = $stmt->fetch();
        
        if (!$template) {
            error_log('[MetaWebhook] Template não encontrado no banco: meta_template_id=' . $messageTemplateId);
            return;
        }
        
        // Processa evento
        switch ($event) {
            case 'APPROVED':
                \PixelHub\Services\MetaTemplateService::markAsApproved($template['id'], $messageTemplateId);
                error_log('[MetaWebhook] Template ID ' . $template['id'] . ' aprovado pelo Meta');
                break;
                
            case 'REJECTED':
                $rejectionReason = $reason ?? 'Rejeitado pelo Meta sem motivo especificado';
                \PixelHub\Services\MetaTemplateService::markAsRejected($template['id'], $rejectionReason);
                error_log('[MetaWebhook] Template ID ' . $template['id'] . ' rejeitado: ' . $rejectionReason);
                break;
                
            case 'PENDING':
                // Template ainda em análise - não faz nada
                error_log('[MetaWebhook] Template ID ' . $template['id'] . ' ainda em análise');
                break;
                
            case 'DISABLED':
                // Template foi desabilitado pelo Meta
                $stmt = $db->prepare("
                    UPDATE whatsapp_message_templates 
                    SET status = 'rejected',
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute(['Template desabilitado pelo Meta', $template['id']]);
                error_log('[MetaWebhook] Template ID ' . $template['id'] . ' desabilitado pelo Meta');
                break;
                
            default:
                error_log('[MetaWebhook] Evento de template desconhecido: ' . $event);
        }
    }
}
