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
        header('Content-Type: application/json');

        // Valida secret se configurado
        $expectedSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET');
        if (!empty($expectedSecret)) {
            $secretHeader = $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? $_SERVER['HTTP_X_GATEWAY_SECRET'] ?? null;
            if ($secretHeader !== $expectedSecret) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid webhook secret'
                ]);
                return;
            }
        }

        // Lê payload JSON
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ]);
            return;
        }

        // Extrai tipo de evento
        $eventType = $payload['event'] ?? $payload['type'] ?? null;
        if (empty($eventType)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Event type is required'
            ]);
            return;
        }

        // Mapeia evento do gateway para evento interno
        $internalEventType = $this->mapEventType($eventType);
        if (empty($internalEventType)) {
            // Evento desconhecido, mas responde 200 para não causar retry
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Event type not handled']);
            return;
        }

        // Extrai channel (para identificar tenant)
        $channelId = $payload['channel'] ?? $payload['channelId'] ?? null;

        // Tenta resolver tenant_id pelo channel
        $tenantId = $this->resolveTenantByChannel($channelId);

        // Cria evento normalizado
        try {
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
                'event_id' => $eventId
            ]);
        } catch (\Exception $e) {
            error_log("[WhatsAppWebhook] Erro ao processar evento: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error processing event'
            ]);
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
            return null;
        }

        $db = DB::getConnection();
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

        return $result ? (int) $result['tenant_id'] : null;
    }
}

