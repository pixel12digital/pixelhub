<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;
use PixelHub\Services\EventIngestionService;
use PDO;

/**
 * Polling ativo de mensagens Whapi.Cloud
 *
 * Alternativa ao webhook quando o firewall do hosting bloqueia
 * conexões inbound da Whapi. Em vez de esperar o Whapi chamar
 * nossa URL, NÓS chamamos a API da Whapi periodicamente.
 *
 * Rota: GET /api/cron/whapi-poll  (protegida por token de cron)
 *
 * Endpoint Whapi: GET https://gate.whapi.cloud/messages
 * Cron sugerido na VPS (a cada 30s):
 *   * * * * * wget -q -O /dev/null "https://hub.pixel12digital.com.br/api/cron/whapi-poll?token=CRON_SECRET" &
 *   * * * * * sleep 30 && wget -q -O /dev/null "https://hub.pixel12digital.com.br/api/cron/whapi-poll?token=CRON_SECRET" &
 */
class WhapiPollerController extends Controller
{
    private const CACHE_KEY  = 'whapi_poller_last_ts';
    private const CACHE_FILE = '/tmp/pixelhub_whapi_poller.json';
    private const WHAPI_BASE = 'https://gate.whapi.cloud';

    public function poll(): void
    {
        // Proteção por token de cron (evita execução pública acidental)
        $cronSecret = \PixelHub\Core\Env::get('CRON_SECRET', '');
        $token = $_GET['token'] ?? '';
        if (!empty($cronSecret) && $token !== $cronSecret) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        set_time_limit(120);

        try {
            $db = DB::getConnection();

            // Obtém token Whapi do banco
            $apiToken = $this->getWhapiToken($db);
            if (!$apiToken) {
                echo json_encode(['success' => false, 'error' => 'Whapi token not configured']);
                return;
            }

            // Obtém channel_id ativo
            $channelId = $this->getWhapiChannelId($db);

            // Timestamp da última execução (para filtrar apenas novas mensagens)
            $lastTs = $this->getLastTimestamp();

            // Busca mensagens na API Whapi
            $messages = $this->fetchMessages($apiToken, $lastTs);

            if (empty($messages)) {
                echo json_encode(['success' => true, 'processed' => 0, 'message' => 'No new messages']);
                return;
            }

            $processed = 0;
            $skipped   = 0;
            $maxTs     = $lastTs;

            foreach ($messages as $message) {
                $msgTs = (int)($message['timestamp'] ?? 0);
                if ($msgTs > $maxTs) $maxTs = $msgTs;

                $result = $this->processMessage($message, $channelId, $db);
                if ($result['processed']) $processed++;
                else $skipped++;
            }

            // Atualiza timestamp do último processamento
            if ($maxTs > $lastTs) {
                $this->saveLastTimestamp($maxTs);
            }

            echo json_encode([
                'success'   => true,
                'processed' => $processed,
                'skipped'   => $skipped,
                'total'     => count($messages),
                'last_ts'   => $maxTs,
            ]);

        } catch (\Throwable $e) {
            error_log('[WhapiPoller] Exception: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function getWhapiToken(PDO $db): ?string
    {
        $stmt = $db->query("
            SELECT whapi_api_token
            FROM whatsapp_provider_configs
            WHERE provider_type = 'whapi'
              AND is_global = TRUE
              AND is_active = 1
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['whapi_api_token'])) return null;

        $token = $row['whapi_api_token'];
        if (strpos($token, 'encrypted:') === 0) {
            $token = CryptoHelper::decrypt(substr($token, 10));
        }
        return $token ?: null;
    }

    private function getWhapiChannelId(PDO $db): ?string
    {
        $stmt = $db->query("
            SELECT channel_id FROM tenant_message_channels
            WHERE provider = 'whapi' AND is_enabled = 1
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['channel_id'] ?? null;
    }

    private function fetchMessages(string $apiToken, int $sinceTs): array
    {
        $url = self::WHAPI_BASE . '/messages?count=100';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiToken,
                'Accept: application/json',
            ],
        ]);
        $body    = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err     = curl_error($ch);
        curl_close($ch);

        if ($err || $httpCode !== 200) {
            error_log("[WhapiPoller] fetchMessages failed: http={$httpCode} err={$err}");
            return [];
        }

        $data = json_decode($body, true);
        if (!is_array($data)) return [];

        // A API retorna {"messages": [...]} ou array direto
        $messages = $data['messages'] ?? (isset($data[0]) ? $data : []);

        // Filtra apenas mensagens mais novas que o último timestamp processado
        if ($sinceTs > 0) {
            $messages = array_filter($messages, function ($m) use ($sinceTs) {
                return (int)($m['timestamp'] ?? 0) > $sinceTs;
            });
        }

        return array_values($messages);
    }

    private function processMessage(array $message, ?string $channelId, PDO $db): array
    {
        $messageId = $message['id'] ?? null;
        $fromMe    = (bool)($message['from_me'] ?? false);
        $type      = $message['type'] ?? 'text';
        $chatId    = $message['chat_id'] ?? null;
        $from      = $message['from'] ?? null;
        $fromName  = $message['from_name'] ?? null;
        $timestamp = $message['timestamp'] ?? time();

        // Ignora grupos
        if ($chatId && strpos($chatId, '@g.us') !== false) {
            return ['processed' => false, 'reason' => 'group'];
        }

        // Ignora tipos de sistema
        $skipTypes = ['protocol', 'revoked', 'e2e_notification', 'ciphertext', 'system'];
        if (in_array($type, $skipTypes, true)) {
            return ['processed' => false, 'reason' => 'type_skipped'];
        }

        // Deduplicação: verifica se já foi processado
        if ($messageId) {
            $dedup = $db->prepare("
                SELECT event_id FROM communication_events
                WHERE (
                    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.message_id')) = ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message_id')) = ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.id')) = ?
                )
                LIMIT 1
            ");
            $dedup->execute([$messageId, $messageId, $messageId]);
            if ($dedup->fetch()) {
                return ['processed' => false, 'reason' => 'duplicate'];
            }
        }

        // Extrai corpo da mensagem
        $body = $message['text']['body']
            ?? $message['image']['caption']
            ?? $message['video']['caption']
            ?? $message['document']['caption']
            ?? $message['audio']['caption']
            ?? '';

        // Normaliza payload para formato interno (compatível com ConversationService)
        $normalizedPayload = [
            'id'         => $messageId,
            'message_id' => $messageId,
            'from'       => $fromMe ? null : $from,
            'to'         => $fromMe ? $chatId : null,
            'timestamp'  => $timestamp,
            'type'       => $type === 'text' ? 'chat' : $type,
            'text'       => $body,
            'body'       => $body,
            'fromMe'     => $fromMe,
            'message'    => [
                'id'          => $messageId,
                'from'        => $from,
                'to'          => $fromMe ? $chatId : null,
                'type'        => $type === 'text' ? 'chat' : $type,
                'body'        => $body,
                'text'        => $body,
                'timestamp'   => $timestamp,
                'fromMe'      => $fromMe,
                'notifyName'  => $fromName,
                'key'         => ['id' => $messageId, 'remoteJid' => $chatId, 'fromMe' => $fromMe],
            ],
            'whapi_original'   => $message,
            'whapi_channel_id' => $channelId,
        ];

        // Mídia
        $mediaLink = $message['image']['link']
            ?? $message['audio']['link']
            ?? $message['video']['link']
            ?? $message['document']['link']
            ?? $message['voice']['link']
            ?? null;
        if ($mediaLink) {
            $normalizedPayload['media'] = ['link' => $mediaLink, 'type' => $type];
            $normalizedPayload['mediaUrl'] = $mediaLink;
            $normalizedPayload['message']['mediaUrl'] = $mediaLink;
        }

        $eventType = $fromMe ? 'whatsapp.outbound.message' : 'whatsapp.inbound.message';

        // Resolve tenant
        $contactPhone = $fromMe ? $this->phoneFromChatId($chatId) : $from;
        $tenantId     = $this->resolveTenant($contactPhone, $db);

        try {
            $eventId = EventIngestionService::ingest([
                'event_type'         => $eventType,
                'source_system'      => 'whapi_cloud',
                'payload'            => $normalizedPayload,
                'tenant_id'          => $tenantId,
                'process_media_sync' => false,
                'metadata'           => [
                    'channel_id'     => $channelId,
                    'provider_type'  => 'whapi',
                    'message_id'     => $messageId,
                    'contact_name'   => $fromName,
                    'raw_event_type' => "whapi_poll_{$type}",
                    'via'            => 'polling',
                ],
            ]);

            if ($eventId) {
                error_log("[WhapiPoller] Ingested: event_id={$eventId} msg={$messageId} from={$from}");
                try {
                    \PixelHub\Services\MediaProcessQueueService::enqueue($eventId);
                } catch (\Throwable $e) { /* não crítico */ }
                return ['processed' => true, 'event_id' => $eventId];
            }

            return ['processed' => false, 'reason' => 'ingest_returned_null'];
        } catch (\Throwable $e) {
            error_log("[WhapiPoller] ingest error: " . $e->getMessage());
            return ['processed' => false, 'reason' => $e->getMessage()];
        }
    }

    private function phoneFromChatId(?string $chatId): ?string
    {
        if (!$chatId) return null;
        return preg_replace('/[^0-9]/', '', explode('@', $chatId)[0]);
    }

    private function resolveTenant(?string $phone, PDO $db): ?int
    {
        if (!$phone) return null;
        $digits = preg_replace('/[^0-9]/', '', $phone);
        $local  = strlen($digits) > 10 ? ltrim($digits, '55') : $digits;

        $stmt = $db->prepare("
            SELECT id FROM tenants
            WHERE REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', '') LIKE ?
              AND (is_archived IS NULL OR is_archived = 0)
            LIMIT 1
        ");
        $stmt->execute(['%' . substr($local, -8)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    private function getLastTimestamp(): int
    {
        if (!file_exists(self::CACHE_FILE)) return 0;
        $data = @json_decode(file_get_contents(self::CACHE_FILE), true);
        return (int)($data[self::CACHE_KEY] ?? 0);
    }

    private function saveLastTimestamp(int $ts): void
    {
        @file_put_contents(self::CACHE_FILE, json_encode([self::CACHE_KEY => $ts]));
    }
}
