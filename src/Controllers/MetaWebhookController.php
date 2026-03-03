<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Services\EventIngestionService;

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

            // Persiste payload bruto para auditoria
            $this->persistRawPayload($payload);

            // Processa cada entrada do webhook
            $processed = 0;
            foreach ($payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    if ($this->processChange($change, $entry)) {
                        $processed++;
                    }
                }
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
     */
    private function persistRawPayload(array $payload): void
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
        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao persistir payload: ' . $e->getMessage());
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
    private function processChange(array $change, array $entry): bool
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

        error_log('[MetaWebhook] Campo não processado: ' . $field);
        return false;
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

        error_log('[MetaWebhook] Mensagem inbound: from=' . $from . ', id=' . $messageId . ', phone_number_id=' . $phoneNumberId);

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
                SELECT tenant_id 
                FROM whatsapp_provider_configs 
                WHERE provider_type = 'meta_official' 
                AND meta_phone_number_id = ? 
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$phoneNumberId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ? (int)$result['tenant_id'] : null;

        } catch (\Exception $e) {
            error_log('[MetaWebhook] Erro ao resolver tenant: ' . $e->getMessage());
            return null;
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
        }

        // Adiciona metadata do Meta
        $normalized['_meta'] = [
            'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
            'display_phone_number' => $value['metadata']['display_phone_number'] ?? null,
            'profile_name' => $value['contacts'][0]['profile']['name'] ?? null
        ];

        return $normalized;
    }
}
