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
            // HUB_WEBHOOK_IN - Log padrão conforme checklist técnico
            $rawPayload = file_get_contents('php://input');
            $payload = json_decode($rawPayload, true);
            
            // Extrai informações críticas do payload para log padrão
            $eventType = $payload['event'] ?? $payload['type'] ?? null;
            $channelId = $payload['channel'] 
                ?? $payload['channelId'] 
                ?? $payload['session']['id'] 
                ?? $payload['session']['session']
                ?? $payload['data']['session']['id'] ?? null
                ?? $payload['data']['session']['session'] ?? null
                ?? $payload['data']['channel'] ?? null
                ?? null;
            $tenantId = null; // Será resolvido depois, mas logamos se vier no payload
            // Extrai 'from' de múltiplos caminhos possíveis (melhorado para cobrir todos os formatos)
            $from = $payload['from'] 
                ?? $payload['message']['from'] 
                ?? $payload['data']['from']
                ?? $payload['raw']['payload']['from']
                ?? $payload['message']['key']['remoteJid']
                ?? $payload['data']['key']['remoteJid']
                ?? $payload['raw']['payload']['key']['remoteJid']
                ?? $payload['message']['key']['participant']
                ?? $payload['data']['key']['participant']
                ?? null;
            $messageId = $payload['id'] 
                ?? $payload['messageId'] 
                ?? $payload['message_id'] 
                ?? $payload['message']['id'] ?? null;
            $timestamp = $payload['timestamp'] 
                ?? $payload['message']['timestamp'] 
                ?? $payload['raw']['payload']['t'] ?? null;
            $correlationId = $payload['correlation_id'] 
                ?? $payload['correlationId'] 
                ?? $payload['trace_id'] 
                ?? $payload['traceId'] ?? null;
            
            // Normaliza from para log (antes da normalização completa)
            $normalizedFrom = null;
            if ($from) {
                // Remove sufixos @c.us, @s.whatsapp.net, etc. para log
                $fromForLog = preg_replace('/@.*$/', '', $from);
                $normalizedFrom = \PixelHub\Services\PhoneNormalizer::toE164OrNull($fromForLog);
            }
            
            // Hash curto do payload para deduplicação
            $payloadHash = substr(md5($rawPayload), 0, 8);
            
            // Log padrão HUB_WEBHOOK_IN
            error_log(sprintf(
                '[HUB_WEBHOOK_IN] eventType=%s channel_id=%s tenant_id=%s from=%s normalized_from=%s message_id=%s timestamp=%s correlationId=%s payload_hash=%s',
                $eventType ?: 'NULL',
                $channelId ?: 'NULL',
                $tenantId ?: 'NULL',
                $from ?: 'NULL',
                $normalizedFrom ?: 'NULL',
                $messageId ?: 'NULL',
                $timestamp ?: 'NULL',
                $correlationId ?: 'NULL',
                $payloadHash
            ));
            
            // Log detalhado do payload e headers (mantido para debug)
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                    $headers[$headerName] = $value;
                }
            }
            
            error_log('[WHATSAPP INBOUND RAW] Payload recebido: ' . json_encode([
                'event' => $eventType,
                'from' => $from,
                'session_id' => $channelId,
                'channel' => $channelId,
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

            // 🔍 INSTRUMENTAÇÃO CRÍTICA: Rastreamento detalhado da origem do channel_id
            // Extrai valores de todas as possíveis localizações para log
            $sessionIdFromPayload = $payload['sessionId'] ?? null;
            $sessionIdFromSession = $payload['session']['id'] ?? null;
            $sessionIdFromSessionSession = $payload['session']['session'] ?? null;
            $sessionIdFromData = $payload['data']['session']['id'] ?? null;
            $sessionIdFromDataSession = $payload['data']['session']['session'] ?? null;
            $channelIdFromPayload = $payload['channelId'] ?? null;
            $channelIdFromChannel = $payload['channel'] ?? null;
            $channelIdFromData = $payload['data']['channel'] ?? null;
            $channelIdFromMetadata = $payload['metadata']['channel_id'] ?? $payload['metadata']['sessionId'] ?? null;
            
            // PRIORIDADE: sessionId primeiro (sessão real do gateway), depois channelId
            // Ordem de prioridade conforme especificação
            $channelId = null;
            $channelIdSource = null;
            
            if ($sessionIdFromPayload) {
                $channelId = (string) $sessionIdFromPayload;
                $channelIdSource = 'payload.sessionId';
            } elseif ($sessionIdFromSession) {
                $channelId = (string) $sessionIdFromSession;
                $channelIdSource = 'payload.session.id';
            } elseif ($sessionIdFromSessionSession) {
                $channelId = (string) $sessionIdFromSessionSession;
                $channelIdSource = 'payload.session.session';
            } elseif ($sessionIdFromData) {
                $channelId = (string) $sessionIdFromData;
                $channelIdSource = 'payload.data.session.id';
            } elseif ($sessionIdFromDataSession) {
                $channelId = (string) $sessionIdFromDataSession;
                $channelIdSource = 'payload.data.session.session';
            } elseif ($channelIdFromMetadata) {
                $channelId = (string) $channelIdFromMetadata;
                $channelIdSource = 'payload.metadata.channel_id/sessionId';
            } elseif ($channelIdFromPayload) {
                $channelId = (string) $channelIdFromPayload;
                $channelIdSource = 'payload.channelId';
            } elseif ($channelIdFromChannel) {
                $channelId = (string) $channelIdFromChannel;
                $channelIdSource = 'payload.channel';
            } elseif ($channelIdFromData) {
                $channelId = (string) $channelIdFromData;
                $channelIdSource = 'payload.data.channel';
            }
            
            // 🔍 LOG DETALHADO: Origem do channel_id
            $fromId = $from ?? 'NULL';
            $toId = $payload['to'] ?? $payload['message']['to'] ?? $payload['data']['to'] ?? 'NULL';
            
            if ($channelId) {
                error_log(sprintf(
                    '[HUB_CHANNEL_ID_EXTRACTION] channel_id=%s | source=%s | from=%s | to=%s | eventType=%s',
                    $channelId,
                    $channelIdSource,
                    $fromId,
                    $toId,
                    $eventType
                ));
                error_log(sprintf(
                    '[HUB_CHANNEL_ID_EXTRACTION] valores_verificados: sessionId.payload=%s | session.id=%s | session.session=%s | data.session.id=%s | data.session.session=%s | metadata.channel_id=%s | channelId=%s | channel=%s | data.channel=%s',
                    $sessionIdFromPayload ?: 'NULL',
                    $sessionIdFromSession ?: 'NULL',
                    $sessionIdFromSessionSession ?: 'NULL',
                    $sessionIdFromData ?: 'NULL',
                    $sessionIdFromDataSession ?: 'NULL',
                    $channelIdFromMetadata ?: 'NULL',
                    $channelIdFromPayload ?: 'NULL',
                    $channelIdFromChannel ?: 'NULL',
                    $channelIdFromData ?: 'NULL'
                ));
            } else {
                error_log('[HUB_CHANNEL_ID_EXTRACTION] INBOUND_MISSING_CHANNEL_ID - Nenhum sessionId/channelId encontrado no payload');
                error_log('[HUB_CHANNEL_ID_EXTRACTION] payload_keys: ' . implode(', ', array_keys($payload)));
                if (isset($payload['session'])) {
                    error_log('[HUB_CHANNEL_ID_EXTRACTION] payload[session]_keys: ' . implode(', ', array_keys($payload['session'])));
                }
                if (isset($payload['data'])) {
                    error_log('[HUB_CHANNEL_ID_EXTRACTION] payload[data]_keys: ' . implode(', ', array_keys($payload['data'])));
                }
                if (isset($payload['metadata'])) {
                    error_log('[HUB_CHANNEL_ID_EXTRACTION] payload[metadata]_keys: ' . implode(', ', array_keys($payload['metadata'])));
                }
            }

            // Tenta resolver tenant_id pelo channel
            $tenantId = $this->resolveTenantByChannel($channelId);
            
            error_log('[WHATSAPP INBOUND RAW] Tenant ID resolvido: ' . ($tenantId ?: 'NULL'));

            // 🔍 INSTRUMENTAÇÃO COMPLETA: Log antes de ingerir
            $timestamp = date('Y-m-d H:i:s');
            $chatId = $from ?? 'NULL';
            $eventIdBeforeIngest = $payload['event_id'] ?? $payload['id'] ?? 'NULL';
            
            error_log(sprintf(
                '[WEBHOOK INSTRUMENTADO] ANTES DE INGERIR: timestamp=%s, from/chatId=%s, eventId=%s, tenant_id=%s, channel_id=%s, event_type=%s',
                $timestamp,
                $chatId,
                $eventIdBeforeIngest,
                $tenantId ?: 'NULL',
                $channelId ?: 'NULL',
                $internalEventType
            ));
            
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
                
                // 🔍 INSTRUMENTAÇÃO: Log após ingestão bem-sucedida
                error_log(sprintf(
                    '[WEBHOOK INSTRUMENTADO] INSERT REALIZADO: event_id=%s, id_pk=verificar_no_banco, timestamp=%s, from=%s, tenant_id=%s, channel_id=%s',
                    $eventId,
                    $timestamp,
                    $chatId,
                    $tenantId ?: 'NULL',
                    $channelId ?: 'NULL'
                ));
                
                // Busca ID (PK) criado para log completo
                $db = DB::getConnection();
                $stmt = $db->prepare("SELECT id FROM communication_events WHERE event_id = ? LIMIT 1");
                $stmt->execute([$eventId]);
                $createdEvent = $stmt->fetch();
                $idPk = $createdEvent ? $createdEvent['id'] : 'NULL';
                
                error_log(sprintf(
                    '[WEBHOOK INSTRUMENTADO] RESULTADO FINAL: event_id=%s, id_pk=%s, from=%s, tenant_id=%s, channel_id=%s, SUCCESS=true',
                    $eventId,
                    $idPk,
                    $chatId,
                    $tenantId ?: 'NULL',
                    $channelId ?: 'NULL'
                ));
                
            } catch (\Exception $ingestException) {
                // 🔍 INSTRUMENTAÇÃO: Log de erro na ingestão
                error_log(sprintf(
                    '[WEBHOOK INSTRUMENTADO] ERRO NO INSERT: exception=%s, message=%s, from=%s, tenant_id=%s, channel_id=%s, SUCCESS=false',
                    get_class($ingestException),
                    $ingestException->getMessage(),
                    $chatId,
                    $tenantId ?: 'NULL',
                    $channelId ?: 'NULL'
                ));
                throw $ingestException; // Re-lança para ser tratado pelo catch externo
            }

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
            // Eventos de mensagens enviadas (outbound)
            'message.sent' => 'whatsapp.outbound.message',
            'message_sent' => 'whatsapp.outbound.message',
            'sent' => 'whatsapp.outbound.message',
            'status' => 'whatsapp.delivery.status',
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
        
        // CORREÇÃO: ORDER BY id ASC garante ordem determinística
        // Prioriza registro mais antigo (tenant original) se houver duplicidade futura
        $stmt = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND channel_id = ? 
            AND is_enabled = 1
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute([$channelId]);
        $result = $stmt->fetch();

        $tenantId = $result ? (int) $result['tenant_id'] : null;
        error_log('[WHATSAPP INBOUND RAW] resolveTenantByChannel: resultado tenant_id=' . ($tenantId ?: 'NULL'));
        
        return $tenantId;
    }
}

