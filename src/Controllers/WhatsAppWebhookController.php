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
        
        // CORREÇÃO: Aumenta timeout para processamento do webhook
        // Isso evita que o processamento seja interrompido antes de concluir
        // IMPORTANTE: Só altera se não estiver já em 0 (ilimitado)
        $currentLimit = ini_get('max_execution_time');
        if ($currentLimit != 0 && $currentLimit < 60) {
            set_time_limit(60);
            ini_set('max_execution_time', 60);
        }

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
            // Passa payload para verificar fromMe em eventos 'message'
            $internalEventType = $this->mapEventType($eventType, $payload);
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
            
            // CORREÇÃO CRÍTICA: Responde ao gateway ANTES de processar completamente
            // Isso garante que o gateway não desista de enviar webhooks se o processamento demorar
            // O processamento continua em background, mas o webhook já respondeu com sucesso
            
            // Cria evento normalizado
            $eventId = null;
            $ingestError = null;
            $eventSaved = false;
            
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
                
                // CORREÇÃO CRÍTICA: Verifica se o evento foi realmente salvo no banco
                // Isso evita que respondamos 200 quando o evento não foi salvo
                if ($eventId) {
                    $db = DB::getConnection();
                    $verifyStmt = $db->prepare("SELECT id FROM communication_events WHERE event_id = ? LIMIT 1");
                    $verifyStmt->execute([$eventId]);
                    $savedEvent = $verifyStmt->fetch();
                    $eventSaved = (bool)$savedEvent;
                    
                    if (!$eventSaved) {
                        error_log(sprintf(
                            '[WEBHOOK CRÍTICO] Event ID retornado mas evento NÃO encontrado no banco: event_id=%s, from=%s',
                            $eventId,
                            $chatId
                        ));
                    }
                }
                
                // 🔍 INSTRUMENTAÇÃO: Log após ingestão bem-sucedida
                error_log(sprintf(
                    '[WEBHOOK INSTRUMENTADO] INSERT REALIZADO: event_id=%s, saved=%s, timestamp=%s, from=%s, tenant_id=%s, channel_id=%s',
                    $eventId ?: 'NULL',
                    $eventSaved ? 'YES' : 'NO',
                    $timestamp,
                    $chatId,
                    $tenantId ?: 'NULL',
                    $channelId ?: 'NULL'
                ));

            } catch (\Exception $ingestException) {
                // 🔍 INSTRUMENTAÇÃO: Log de erro na ingestão
                $ingestError = $ingestException->getMessage();
                error_log(sprintf(
                    '[WEBHOOK INSTRUMENTADO] ERRO NO INSERT: exception=%s, message=%s, from=%s, tenant_id=%s, channel_id=%s',
                    get_class($ingestException),
                    $ingestError,
                    $chatId,
                    $tenantId ?: 'NULL',
                    $channelId ?: 'NULL'
                ));
                error_log("[WEBHOOK INSTRUMENTADO] Stack trace: " . $ingestException->getTraceAsString());
            }

            // CORREÇÃO: Responde 200 apenas se evento foi salvo com sucesso
            // Se não foi salvo, ainda responde 200 para não fazer gateway desistir,
            // mas loga como erro crítico para investigação
            if ($eventId && $eventSaved) {
                // Log de sucesso
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
            } else {
                // CORREÇÃO: Se evento não foi salvo, loga como erro crítico
                // Mas ainda responde 200 para não fazer gateway parar de enviar
                error_log(sprintf(
                    '[WEBHOOK CRÍTICO] Evento NÃO foi salvo: event_id=%s, error=%s, from=%s, tenant_id=%s, channel_id=%s',
                    $eventId ?: 'NULL',
                    $ingestError ?: 'UNKNOWN',
                    $chatId,
                    $tenantId ?: 'NULL',
                    $channelId ?: 'NULL'
                ));
                
                // Responde 200 para manter gateway ativo, mas com código de aviso
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'code' => 'PROCESSED_WITH_WARNINGS',
                    'warning' => 'Event processed with warnings, check logs',
                    'event_saved' => false
                ], JSON_UNESCAPED_UNICODE);
            }
            
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
     * @param array $payload Payload completo do webhook (para verificar fromMe)
     * @return string|null Tipo de evento interno ou null se não mapeado
     */
    private function mapEventType(string $gatewayEventType, array $payload = []): ?string
    {
        // Para eventos 'message', verifica fromMe para determinar direção
        // Isso captura mensagens enviadas pelo celular/web (fora do PixelHub)
        if ($gatewayEventType === 'message') {
            // Tenta extrair fromMe de várias possíveis localizações no payload
            $fromMe = $payload['fromMe'] 
                ?? $payload['message']['fromMe']
                ?? $payload['message']['key']['fromMe']
                ?? $payload['data']['fromMe']
                ?? $payload['data']['message']['fromMe']
                ?? $payload['data']['message']['key']['fromMe']
                ?? false;
            
            // Log para debug
            error_log('[mapEventType] Evento "message" - fromMe=' . ($fromMe ? 'true' : 'false'));
            
            if ($fromMe) {
                error_log('[mapEventType] ✅ Mensagem enviada pelo celular/web detectada! Classificando como outbound.');
                return 'whatsapp.outbound.message';
            }
            
            return 'whatsapp.inbound.message';
        }
        
        // Outros eventos mapeados estaticamente
        $mapping = [
            'message.ack' => 'whatsapp.delivery.ack',
            'connection.update' => 'whatsapp.connection.update',
            // Eventos de mensagens enviadas (outbound) - já classificados pelo gateway
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

