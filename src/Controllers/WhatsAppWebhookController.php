<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\EventIngestionService;

/**
 * Controller para receber webhooks do WPP Gateway
 * 
 * Converte eventos do gateway em eventos normalizados do PixelHub
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Processa webhook do WPP Gateway
     * 
     * Rota: POST /api/whatsapp/webhook
     */
    public function handle(): void
    {
        // Limpa qualquer output anterior que possa corromper o JSON
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        // Sempre retorna JSON, mesmo em erro
        header('Content-Type: application/json; charset=utf-8');

        try {
            // 🔍 PASSO 1: LOG OBRIGATÓRIO NO WEBHOOK (ANTES DE QUALQUER LÓGICA)
            $rawPayload = file_get_contents('php://input');
            $payload = json_decode($rawPayload, true);
            
            // Log detalhado do payload e headers
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $headers[$headerName] = $value;
                }
            }
            
            // Extrai informações críticas do payload para log
            $from = $payload['from'] ?? $payload['message']['from'] ?? $payload['data']['from'] ?? null;
            $sessionId = $payload['session']['id'] ?? $payload['session']['session'] ?? $payload['channel'] ?? $payload['channelId'] ?? null;
            $eventType = $payload['event'] ?? $payload['type'] ?? null;
            
            error_log('[WHATSAPP INBOUND RAW] Payload recebido: ' . json_encode([
                'event' => $eventType,
                'from' => $from,
                'session_id' => $sessionId,
                'channel' => $payload['channel'] ?? $payload['channelId'] ?? null,
                'payload_keys' => array_keys($payload),
                'has_message' => isset($payload['message']),
                'has_data' => isset($payload['data']),
                'has_session' => isset($payload['session']),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            error_log('[WHATSAPP INBOUND RAW] Headers: ' . json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            error_log('[WHATSAPP INBOUND RAW] Payload completo (primeiros 2000 chars): ' . substr($rawPayload, 0, 2000));

            // Valida secret se configurado
            $expectedSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET');
            if (!empty($expectedSecret)) {
                $secretHeader = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_GATEWAY_SECRET'] ?? null;
                if ($secretHeader !== $expectedSecret) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Invalid webhook secret',
                        'code' => 'INVALID_SECRET'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON payload',
                    'code' => 'INVALID_JSON'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Extrai tipo de evento
            $eventType = $payload['event'] ?? $payload['type'] ?? null;
            if (empty($eventType)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Event type is required',
                    'code' => 'MISSING_EVENT_TYPE'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Mapeia evento do gateway para evento interno
            $internalEventType = $this->mapEventType($eventType);
            if (empty($internalEventType)) {
                // Evento desconhecido, mas responde 200 para não causar retry
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Event type not handled',
                    'code' => 'EVENT_NOT_HANDLED'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Extrai channel (para identificar tenant) - tenta múltiplas localizações
            $channelId = $payload['channel'] 
                ?? $payload['channelId'] 
                ?? $payload['session']['id'] 
                ?? $payload['session']['session']
                ?? $payload['data']['session']['id'] ?? null
                ?? $payload['data']['session']['session'] ?? null
                ?? $payload['data']['channel'] ?? null
                ?? null;
            
            error_log('[WHATSAPP INBOUND RAW] Channel ID extraído: ' . ($channelId ?: 'NULL'));
            if (!$channelId) {
                error_log('[WHATSAPP INBOUND RAW] AVISO: channel_id não encontrado. Payload keys: ' . implode(', ', array_keys($payload)));
                if (isset($payload['session'])) {
                    error_log('[WHATSAPP INBOUND RAW] payload[session] keys: ' . implode(', ', array_keys($payload['session'])));
                }
                if (isset($payload['data'])) {
                    error_log('[WHATSAPP INBOUND RAW] payload[data] keys: ' . implode(', ', array_keys($payload['data'])));
                }
            }

            // Tenta resolver tenant_id pelo channel
            $tenantId = $this->resolveTenantByChannel($channelId);
            
            error_log('[WHATSAPP INBOUND RAW] Tenant ID resolvido: ' . ($tenantId ?: 'NULL'));

            // Cria evento normalizado
            $eventId = EventIngestionService::ingest([
                'event_type' => $internalEventType,
                'source_system' => 'wpp_gateway',
                'payload' => $payload,
                'tenant_id' => $tenantId,
                'metadata' => [
                    'channel_id' => $channelId,
                    'raw_event_type' => $eventType
                ]
            ]);

            // Log
            if (function_exists('pixelhub_log')) {
                pixelhub_log(sprintf(
                    '[WhatsAppWebhook] Event received: %s -> %s (event_id: %s, tenant_id: %s)',
                    $eventType,
                    $internalEventType,
                    $eventId,
                    $tenantId ?: 'NULL'
                ));
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'event_id' => $eventId,
                'code' => 'SUCCESS'
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (\RuntimeException $e) {
            error_log("[WhatsAppWebhook::handle] RuntimeException: " . $e->getMessage());
            error_log("[WhatsAppWebhook::handle] Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Exception $e) {
            error_log("[WhatsAppWebhook::handle] Exception: " . $e->getMessage());
            error_log("[WhatsAppWebhook::handle] Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            error_log("[WhatsAppWebhook::handle] Throwable: " . $e->getMessage());
            error_log("[WhatsAppWebhook::handle] Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * Mapeia evento do gateway para tipo de evento interno
     * 
     * @param string $gatewayEventType Tipo de evento do gateway
     * @return string|null Tipo de evento interno ou null se não mapeado
     */
    private function mapEventType(string $gatewayEventType): ?string
    {
        $mapping = [
            'message' => 'whatsapp.inbound.message',
            'message.ack' => 'whatsapp.delivery.ack',
            'connection.update' => 'whatsapp.connection.update',
        ];

        return $mapping[$gatewayEventType] ?? null;
    }

    /**
     * Resolve tenant_id pelo channel_id
     * 
     * @param string|null $channelId ID do channel
     * @return int|null ID do tenant ou null se não encontrado
     */
    private function resolveTenantByChannel(?string $channelId): ?int
    {
        if (empty($channelId)) {
            error_log('[WHATSAPP INBOUND RAW] resolveTenantByChannel: channelId está vazio');
            return null;
        }

        error_log('[WHATSAPP INBOUND RAW] resolveTenantByChannel: buscando tenant_id para channel_id=' . $channelId);

        $db = DB::getConnection();
        
        // Log: lista todos os channels disponíveis para debug
        $debugStmt = $db->query("
            SELECT id, tenant_id, provider, channel_id, is_enabled 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway'
        ");
        $allChannels = $debugStmt->fetchAll();
        error_log('[WHATSAPP INBOUND RAW] Channels disponíveis no banco: ' . json_encode($allChannels, JSON_UNESCAPED_UNICODE));
        
        $stmt = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND channel_id = ? 
            AND is_enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$channelId]);
        $result = $stmt->fetch();

        $tenantId = $result ? (int) $result['tenant_id'] : null;
        error_log('[WHATSAPP INBOUND RAW] resolveTenantByChannel: resultado tenant_id=' . ($tenantId ?: 'NULL'));
        
        return $tenantId;
    }
}

