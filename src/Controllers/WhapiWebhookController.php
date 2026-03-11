<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\EventIngestionService;

/**
 * Controller para receber webhooks do Whapi.Cloud
 * 
 * Substitui o WPPConnect Gateway como provider não-oficial.
 * Normaliza payload Whapi.Cloud → formato interno do PixelHub.
 * 
 * Rota: POST /api/whatsapp/whapi/webhook
 * 
 * Formato Whapi.Cloud:
 * {
 *   "messages": [{ "id": "...", "from_me": false, "type": "text", "chat_id": "55...@s.whatsapp.net", "from": "55...", "text": { "body": "..." } }],
 *   "event": { "type": "messages", "event": "post" },
 *   "channel_id": "CHANNEL-ID"
 * }
 */
class WhapiWebhookController extends Controller
{
    /**
     * Processa webhook do Whapi.Cloud
     * 
     * Rota: POST /api/whatsapp/whapi/webhook
     */
    public function handle(): void
    {
        // Limpa output anterior
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');

        $currentLimit = ini_get('max_execution_time');
        if ($currentLimit != 0 && $currentLimit < 60) {
            set_time_limit(60);
            ini_set('max_execution_time', 60);
        }

        try {
            $rawPayload = file_get_contents('php://input');
            $payload = json_decode($rawPayload, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid JSON', 'code' => 'INVALID_JSON']);
                exit;
            }

            // Identifica tipo de evento Whapi.Cloud
            $eventType = $payload['event']['type'] ?? null;
            $eventAction = $payload['event']['event'] ?? null;
            $whapiChannelId = $payload['channel_id'] ?? null;

            error_log(sprintf(
                '[WhapiWebhook] Received: event_type=%s, event_action=%s, channel_id=%s',
                $eventType ?? 'NULL',
                $eventAction ?? 'NULL',
                $whapiChannelId ?? 'NULL'
            ));

            // Persiste payload bruto para auditoria
            $payloadHash = substr(md5($rawPayload), 0, 16);
            try {
                $dbLog = DB::getConnection();
                $dbLog->prepare("
                    INSERT INTO webhook_raw_logs (event_type, payload_hash, payload_json, processed)
                    VALUES (?, ?, ?, 0)
                ")->execute(["whapi_{$eventType}_{$eventAction}", $payloadHash, $rawPayload]);
            } catch (\Throwable $e) {
                error_log("[WhapiWebhook] Erro ao persistir webhook_raw_logs (não crítico): " . $e->getMessage());
            }

            // SHORT-CIRCUIT: Eventos de status (read, delivered, etc.)
            if ($eventType === 'statuses') {
                http_response_code(200);
                echo json_encode(['success' => true, 'code' => 'STATUS_SKIPPED', 'message' => 'Status events not processed']);
                exit;
            }

            // Processa apenas mensagens
            if ($eventType !== 'messages' || empty($payload['messages'])) {
                http_response_code(200);
                echo json_encode(['success' => true, 'code' => 'EVENT_NOT_HANDLED', 'message' => "Event type '{$eventType}' not handled"]);
                exit;
            }

            // Processa cada mensagem no array
            $results = [];
            foreach ($payload['messages'] as $message) {
                $result = $this->processMessage($message, $whapiChannelId, $rawPayload);
                $results[] = $result;
            }

            $hasSuccess = !empty(array_filter($results, fn($r) => $r['saved'] ?? false));

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'code' => $hasSuccess ? 'SUCCESS' : 'PROCESSED_WITH_WARNINGS',
                'results' => $results
            ], JSON_UNESCAPED_UNICODE);

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } else {
                if (ob_get_level()) ob_end_flush();
                flush();
            }

            // Enfileira mídia para processamento async
            foreach ($results as $r) {
                if (!empty($r['event_id']) && ($r['saved'] ?? false)) {
                    try {
                        \PixelHub\Services\MediaProcessQueueService::enqueue($r['event_id']);
                    } catch (\Throwable $e) {
                        error_log("[WhapiWebhook] Erro ao enfileirar mídia: " . $e->getMessage());
                    }
                }
            }

            exit;

        } catch (\Throwable $e) {
            error_log("[WhapiWebhook] Exception: " . $e->getMessage());
            error_log("[WhapiWebhook] Stack: " . $e->getTraceAsString());

            http_response_code(200); // Responde 200 para não causar retry
            echo json_encode([
                'success' => true,
                'code' => 'PROCESSED_WITH_ERRORS',
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Processa uma mensagem individual do webhook Whapi.Cloud
     * 
     * Normaliza para formato compatível com ConversationService/EventIngestionService
     * 
     * @param array $message Objeto de mensagem Whapi.Cloud
     * @param string|null $whapiChannelId Channel ID do Whapi.Cloud
     * @param string $rawPayload Payload bruto completo
     * @return array ['event_id' => ?string, 'saved' => bool, 'error' => ?string]
     */
    private function processMessage(array $message, ?string $whapiChannelId, string $rawPayload): array
    {
        $messageId = $message['id'] ?? null;
        $fromMe = $message['from_me'] ?? false;
        $type = $message['type'] ?? 'text';
        $chatId = $message['chat_id'] ?? null;
        $from = $message['from'] ?? null;
        $fromName = $message['from_name'] ?? null;
        $timestamp = $message['timestamp'] ?? time();

        error_log(sprintf(
            '[WhapiWebhook] Processing message: id=%s, from_me=%s, type=%s, from=%s, chat_id=%s',
            $messageId ?? 'NULL',
            $fromMe ? 'true' : 'false',
            $type,
            $from ?? 'NULL',
            $chatId ?? 'NULL'
        ));

        // Ignora mensagens de grupo por enquanto
        if ($chatId && strpos($chatId, '@g.us') !== false) {
            error_log("[WhapiWebhook] Ignorando mensagem de grupo: {$chatId}");
            return ['event_id' => null, 'saved' => false, 'error' => 'GROUP_MESSAGE_SKIPPED', 'skipped' => true];
        }

        // Ignora tipos não processáveis
        $skipTypes = ['protocol', 'revoked', 'e2e_notification', 'ciphertext', 'system'];
        if (in_array($type, $skipTypes, true)) {
            return ['event_id' => null, 'saved' => false, 'error' => 'TYPE_SKIPPED', 'skipped' => true];
        }

        // Determina direção (inbound vs outbound)
        $internalEventType = $fromMe ? 'whatsapp.outbound.message' : 'whatsapp.inbound.message';

        // Extrai corpo da mensagem conforme o tipo
        $body = $this->extractMessageBody($message);

        // Extrai informação de mídia
        $mediaInfo = $this->extractMediaInfo($message);

        // Normaliza payload para formato compatível com ConversationService
        // ConversationService busca em: payload['from'], payload['message']['from'], etc.
        $normalizedPayload = [
            'id' => $messageId,
            'message_id' => $messageId,
            'from' => $from,
            'to' => $fromMe ? $chatId : null,
            'timestamp' => $timestamp,
            'type' => $this->mapMessageType($type),
            'text' => $body,
            'body' => $body,
            'fromMe' => $fromMe,
            'message' => [
                'id' => $messageId,
                'from' => $from,
                'to' => $fromMe ? $chatId : null,
                'type' => $this->mapMessageType($type),
                'body' => $body,
                'text' => $body,
                'timestamp' => $timestamp,
                'fromMe' => $fromMe,
                'notifyName' => $fromName,
                'key' => [
                    'id' => $messageId,
                    'remoteJid' => $chatId,
                    'fromMe' => $fromMe,
                ],
            ],
            // Dados originais do Whapi para referência
            'whapi_original' => $message,
            'whapi_channel_id' => $whapiChannelId,
        ];

        // Adiciona dados de mídia ao payload
        if ($mediaInfo) {
            $normalizedPayload['media'] = $mediaInfo;
            $normalizedPayload['message']['media'] = $mediaInfo;
            // Compatibilidade com WhatsAppMediaService
            if (!empty($mediaInfo['link'])) {
                $normalizedPayload['message']['mediaUrl'] = $mediaInfo['link'];
                $normalizedPayload['mediaUrl'] = $mediaInfo['link'];
            }
        }

        // Resolve tenant por telefone
        $contactPhone = $fromMe ? $this->extractPhoneFromChatId($chatId) : $from;
        $tenantId = $this->resolveTenantByPhone($contactPhone);

        error_log(sprintf(
            '[WhapiWebhook] Tenant resolved: phone=%s, tenant_id=%s, direction=%s',
            $contactPhone ?? 'NULL',
            $tenantId ?? 'NULL',
            $fromMe ? 'outbound' : 'inbound'
        ));

        // Deduplicação outbound
        if ($fromMe && $messageId) {
            try {
                $dbDedup = DB::getConnection();
                $dedupStmt = $dbDedup->prepare("
                    SELECT event_id FROM communication_events 
                    WHERE event_type = 'whatsapp.outbound.message'
                    AND (
                        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.message_id')) = ?
                        OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message_id')) = ?
                        OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id')) = ?
                    )
                    LIMIT 1
                ");
                $dedupStmt->execute([$messageId, $messageId, $messageId]);
                $existing = $dedupStmt->fetch();
                if ($existing) {
                    error_log("[WhapiWebhook] DEDUP: Outbound already registered: {$messageId}");
                    return ['event_id' => $existing['event_id'], 'saved' => true, 'error' => null, 'deduplicated' => true];
                }
            } catch (\Throwable $e) {
                error_log("[WhapiWebhook] Dedup error: " . $e->getMessage());
            }
        }

        // Ingere evento
        try {
            $eventId = EventIngestionService::ingest([
                'event_type' => $internalEventType,
                'source_system' => 'whapi_cloud',
                'payload' => $normalizedPayload,
                'tenant_id' => $tenantId,
                'process_media_sync' => false,
                'metadata' => [
                    'channel_id' => $whapiChannelId,
                    'raw_event_type' => "whapi_message_{$type}",
                    'provider_type' => 'whapi',
                    'message_id' => $messageId,
                    'contact_name' => $fromName,
                ]
            ]);

            // Verifica se foi salvo
            $eventSaved = false;
            if ($eventId) {
                $db = DB::getConnection();
                $verifyStmt = $db->prepare("SELECT id FROM communication_events WHERE event_id = ? LIMIT 1");
                $verifyStmt->execute([$eventId]);
                $eventSaved = (bool) $verifyStmt->fetch();
            }

            // Detecta resposta para mensagens agendadas (follow-ups)
            if ($eventSaved && $internalEventType === 'whatsapp.inbound.message') {
                try {
                    $db = DB::getConnection();
                    $convStmt = $db->prepare("SELECT conversation_id FROM communication_events WHERE event_id = ? LIMIT 1");
                    $convStmt->execute([$eventId]);
                    $convRow = $convStmt->fetch();
                    if ($convRow && $convRow['conversation_id']) {
                        \PixelHub\Services\ScheduledMessageService::detectResponse($convRow['conversation_id']);
                    }
                } catch (\Exception $e) {
                    error_log("[WhapiWebhook] Erro ao detectar resposta follow-up: " . $e->getMessage());
                }
            }

            error_log(sprintf(
                '[WhapiWebhook] Ingested: event_id=%s, saved=%s, type=%s',
                $eventId ?? 'NULL',
                $eventSaved ? 'YES' : 'NO',
                $internalEventType
            ));

            return ['event_id' => $eventId, 'saved' => $eventSaved, 'error' => null];

        } catch (\Throwable $e) {
            error_log("[WhapiWebhook] Ingest error: " . $e->getMessage());
            return ['event_id' => null, 'saved' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Extrai corpo da mensagem conforme o tipo
     */
    private function extractMessageBody(array $message): string
    {
        $type = $message['type'] ?? 'text';

        switch ($type) {
            case 'text':
            case 'link_preview':
                return $message['text']['body'] ?? $message['link_preview']['body'] ?? '';

            case 'image':
                return $message['image']['caption'] ?? '[Imagem]';

            case 'video':
                return $message['video']['caption'] ?? '[Vídeo]';

            case 'audio':
                return '[Áudio]';

            case 'voice':
                return '[Áudio]';

            case 'document':
                $filename = $message['document']['filename'] ?? 'documento';
                $caption = $message['document']['caption'] ?? '';
                return $caption ?: "[Documento: {$filename}]";

            case 'sticker':
                return '[Figurinha]';

            case 'location':
            case 'live_location':
                return '[Localização]';

            case 'contact':
            case 'contact_list':
                return '[Contato]';

            case 'poll':
                return '[Enquete]';

            case 'order':
                return '[Pedido]';

            default:
                return $message['text']['body'] ?? '';
        }
    }

    /**
     * Extrai informações de mídia da mensagem
     */
    private function extractMediaInfo(array $message): ?array
    {
        $type = $message['type'] ?? 'text';
        $mediaTypes = ['image', 'video', 'audio', 'voice', 'document', 'sticker'];

        if (!in_array($type, $mediaTypes, true)) {
            return null;
        }

        $mediaData = $message[$type] ?? null;
        if (!$mediaData || !is_array($mediaData)) {
            return null;
        }

        return [
            'type' => $type,
            'link' => $mediaData['link'] ?? null,
            'id' => $mediaData['id'] ?? null,
            'mime_type' => $mediaData['mime_type'] ?? null,
            'file_size' => $mediaData['file_size'] ?? null,
            'filename' => $mediaData['filename'] ?? null,
            'sha256' => $mediaData['sha256'] ?? null,
            'caption' => $mediaData['caption'] ?? null,
        ];
    }

    /**
     * Mapeia tipo de mensagem Whapi → tipo interno
     */
    private function mapMessageType(string $whapiType): string
    {
        $mapping = [
            'text' => 'chat',
            'link_preview' => 'chat',
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio',
            'voice' => 'ptt',
            'document' => 'document',
            'sticker' => 'sticker',
            'location' => 'location',
            'live_location' => 'location',
            'contact' => 'vcard',
            'contact_list' => 'vcard',
        ];

        return $mapping[$whapiType] ?? $whapiType;
    }

    /**
     * Extrai número de telefone do chat_id
     * chat_id format: "5511999999999@s.whatsapp.net"
     */
    private function extractPhoneFromChatId(?string $chatId): ?string
    {
        if (empty($chatId)) return null;
        return preg_replace('/@.*$/', '', $chatId);
    }

    /**
     * Resolve tenant_id pelo telefone (reutiliza lógica do WhatsAppWebhookController)
     */
    private function resolveTenantByPhone(?string $from): ?int
    {
        if (empty($from)) return null;

        $cleaned = preg_replace('/@.*$/', '', $from);
        $contactDigits = preg_replace('/[^0-9]/', '', $cleaned);

        if (empty($contactDigits) || strlen($contactDigits) < 8) return null;

        if (substr($contactDigits, 0, 2) !== '55' && (strlen($contactDigits) === 10 || strlen($contactDigits) === 11)) {
            $contactDigits = '55' . $contactDigits;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->query("SELECT id, name, phone FROM tenants WHERE phone IS NOT NULL AND phone != '' AND (is_archived IS NULL OR is_archived = 0) ORDER BY id ASC");
            $tenants = $stmt->fetchAll();
            $matches = [];

            foreach ($tenants as $tenant) {
                $tenantPhone = preg_replace('/[^0-9]/', '', $tenant['phone']);
                if (empty($tenantPhone)) continue;

                if (substr($tenantPhone, 0, 2) !== '55' && (strlen($tenantPhone) === 10 || strlen($tenantPhone) === 11)) {
                    $tenantPhone = '55' . $tenantPhone;
                }

                if ($contactDigits === $tenantPhone) {
                    $matches[] = $tenant;
                    continue;
                }

                // Tolerância 9º dígito
                if (strlen($contactDigits) >= 12 && strlen($tenantPhone) >= 12 &&
                    substr($contactDigits, 0, 2) === '55' && substr($tenantPhone, 0, 2) === '55') {
                    if ($this->removeNinthDigit($contactDigits) === $this->removeNinthDigit($tenantPhone)) {
                        $matches[] = $tenant;
                    }
                }
            }

            if (count($matches) === 1) {
                return (int) $matches[0]['id'];
            }

            return null;

        } catch (\Exception $e) {
            error_log('[WhapiWebhook] Erro ao resolver tenant: ' . $e->getMessage());
            return null;
        }
    }

    private function removeNinthDigit(string $digits): string
    {
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '55') {
            return substr($digits, 0, 4) . substr($digits, 5);
        }
        return $digits;
    }
}
