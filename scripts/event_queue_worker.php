<?php
/**
 * Worker Assíncrono - Processador de Eventos Queued
 * 
 * Processa eventos em status 'queued' da tabela communication_events.
 * Deve rodar continuamente via cron ou supervisor.
 * 
 * Uso:
 *   php scripts/event_queue_worker.php
 * 
 * Cron (a cada minuto):
 *   * * * * * cd /path/to/pixelhub && php scripts/event_queue_worker.php >> logs/event_worker.log 2>&1
 */

define('ROOT_PATH', __DIR__ . '/../');
require_once ROOT_PATH . 'src/Core/Env.php';
require_once ROOT_PATH . 'src/Core/DB.php';
require_once ROOT_PATH . 'src/Services/EventRouterService.php';
require_once ROOT_PATH . 'src/Services/ConversationService.php';
require_once ROOT_PATH . 'src/Services/MediaProcessQueueService.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\EventRouterService;
use PixelHub\Services\ConversationService;
use PixelHub\Services\MediaProcessQueueService;

Env::load();

// Configurações
$batchSize = 10; // Processar 10 eventos por execução
$maxRetries = 3;
$lockTimeout = 300; // 5 minutos de timeout para eventos em processing

echo "[" . date('Y-m-d H:i:s') . "] Event Queue Worker iniciado\n";

try {
    $db = DB::getConnection();
    
    // 1. Liberar eventos travados em 'processing' há mais de 5 minutos
    $releaseStmt = $db->prepare("
        UPDATE communication_events
        SET status = 'queued',
            updated_at = NOW()
        WHERE status = 'processing'
            AND updated_at < DATE_SUB(NOW(), INTERVAL :timeout SECOND)
    ");
    $releaseStmt->execute(['timeout' => $lockTimeout]);
    $released = $releaseStmt->rowCount();
    
    if ($released > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Liberados {$released} eventos travados em 'processing'\n";
    }
    
    // 2. Buscar eventos queued para processar (ordenar por created_at para FIFO)
    $stmt = $db->prepare("
        SELECT 
            id,
            event_id,
            event_type,
            source_system,
            tenant_id,
            payload,
            metadata,
            retry_count,
            max_retries
        FROM communication_events
        WHERE status = 'queued'
            AND (next_retry_at IS NULL OR next_retry_at <= NOW())
            AND retry_count < max_retries
        ORDER BY created_at ASC
        LIMIT :batch_size
    ");
    $stmt->bindValue('batch_size', $batchSize, PDO::PARAM_INT);
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) === 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Nenhum evento queued para processar\n";
        exit(0);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Processando " . count($events) . " evento(s)\n";
    
    $processed = 0;
    $failed = 0;
    
    foreach ($events as $event) {
        $eventId = $event['event_id'];
        $eventDbId = $event['id'];
        
        try {
            // Marca como processing
            $updateStmt = $db->prepare("
                UPDATE communication_events
                SET status = 'processing',
                    updated_at = NOW()
                WHERE id = :id AND status = 'queued'
            ");
            $updateStmt->execute(['id' => $eventDbId]);
            
            if ($updateStmt->rowCount() === 0) {
                // Outro worker pegou este evento
                echo "[" . date('Y-m-d H:i:s') . "] Evento {$eventId} já está sendo processado por outro worker\n";
                continue;
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Processando evento {$eventId} (tipo: {$event['event_type']}, tenant: {$event['tenant_id']})\n";
            
            // Decodifica payload e metadata
            $payload = json_decode($event['payload'], true);
            $metadata = json_decode($event['metadata'], true);
            
            // Processa o evento baseado no tipo
            $success = processEvent($event, $payload, $metadata, $db);
            
            if ($success) {
                // Marca como processed
                $doneStmt = $db->prepare("
                    UPDATE communication_events
                    SET status = 'processed',
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $doneStmt->execute(['id' => $eventDbId]);
                
                echo "[" . date('Y-m-d H:i:s') . "] ✅ Evento {$eventId} processado com sucesso\n";
                $processed++;
            } else {
                throw new Exception("Falha no processamento do evento");
            }
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            $retryCount = (int)$event['retry_count'] + 1;
            
            echo "[" . date('Y-m-d H:i:s') . "] ❌ Erro ao processar evento {$eventId}: {$errorMsg}\n";
            
            if ($retryCount >= $event['max_retries']) {
                // Excedeu tentativas, marca como failed
                $failStmt = $db->prepare("
                    UPDATE communication_events
                    SET status = 'failed',
                        error_message = :error,
                        retry_count = :retry_count,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $failStmt->execute([
                    'id' => $eventDbId,
                    'error' => substr($errorMsg, 0, 1000),
                    'retry_count' => $retryCount
                ]);
                
                echo "[" . date('Y-m-d H:i:s') . "] ⚠️  Evento {$eventId} marcado como FAILED após {$retryCount} tentativas\n";
                $failed++;
            } else {
                // Volta para queued com backoff exponencial
                $backoffSeconds = min(300, pow(2, $retryCount) * 10); // Max 5 minutos
                
                $retryStmt = $db->prepare("
                    UPDATE communication_events
                    SET status = 'queued',
                        error_message = :error,
                        retry_count = :retry_count,
                        next_retry_at = DATE_ADD(NOW(), INTERVAL :backoff SECOND),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $retryStmt->execute([
                    'id' => $eventDbId,
                    'error' => substr($errorMsg, 0, 1000),
                    'retry_count' => $retryCount,
                    'backoff' => $backoffSeconds
                ]);
                
                echo "[" . date('Y-m-d H:i:s') . "] 🔄 Evento {$eventId} voltou para fila (tentativa {$retryCount}/{$event['max_retries']}, próxima em {$backoffSeconds}s)\n";
            }
        }
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Worker finalizado: {$processed} processados, {$failed} falharam\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO CRÍTICO no worker: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Processa um evento baseado no tipo
 */
function processEvent(array $event, array $payload, array $metadata, PDO $db): bool
{
    $eventType = $event['event_type'];
    $tenantId = $event['tenant_id'];
    
    // Eventos WhatsApp: criar/atualizar conversa
    if (strpos($eventType, 'whatsapp.') === 0) {
        return processWhatsAppEvent($event, $payload, $metadata, $db);
    }
    
    // Eventos Asaas: processar via EventRouter
    if (strpos($eventType, 'asaas.') === 0) {
        return processAsaasEvent($event, $payload, $metadata, $db);
    }
    
    // Eventos Billing: processar via EventRouter
    if (strpos($eventType, 'billing.') === 0) {
        return processBillingEvent($event, $payload, $metadata, $db);
    }
    
    // Outros eventos: apenas marca como processed
    echo "[processEvent] Tipo de evento '{$eventType}' não requer processamento adicional\n";
    return true;
}

/**
 * Processa eventos WhatsApp (inbound/outbound)
 */
function processWhatsAppEvent(array $event, array $payload, array $metadata, PDO $db): bool
{
    $eventType = $event['event_type'];
    $tenantId = $event['tenant_id'];
    $channelId = $metadata['channel_id'] ?? null;
    
    // Extrai informações da mensagem (suporta múltiplas estruturas de payload)
    $from = $payload['from'] 
        ?? $payload['data']['from'] 
        ?? $payload['message']['from']
        ?? $payload['raw']['payload']['from']
        ?? null;
    
    $to = $payload['to'] 
        ?? $payload['data']['to'] 
        ?? $payload['message']['to']
        ?? $payload['raw']['payload']['to']
        ?? null;
    
    $body = $payload['body'] 
        ?? $payload['data']['body'] 
        ?? $payload['message']['body']
        ?? $payload['message']['text']
        ?? $payload['raw']['payload']['body']
        ?? null;
    
    $messageType = $payload['type'] 
        ?? $payload['data']['type'] 
        ?? $payload['message']['type']
        ?? $payload['raw']['payload']['type']
        ?? 'text';
    
    // chatId pode ser usado como fallback para contact_external_id
    $chatId = $payload['chatId'] 
        ?? $payload['data']['chatId']
        ?? $payload['raw']['payload']['chatId']
        ?? null;
    
    // Determina direção
    $direction = (strpos($eventType, 'inbound') !== false) ? 'inbound' : 'outbound';
    
    // Para inbound, o contato é o 'from'; para outbound, é o 'to'
    $contactExternalId = ($direction === 'inbound') ? ($from ?? $chatId) : ($to ?? $chatId);
    
    if (empty($contactExternalId)) {
        throw new Exception("Não foi possível extrair contact_external_id do payload (from/to/chatId estão vazios)");
    }
    
    // Resolve ou cria conversa
    try {
        // ConversationService::resolveConversation espera estrutura de evento completa
        $conversation = ConversationService::resolveConversation([
            'event_type' => $event['event_type'],
            'source_system' => $event['source_system'],
            'tenant_id' => $tenantId,
            'payload' => $payload,
            'metadata' => $metadata
        ]);
        
        if ($conversation && isset($conversation['id'])) {
            echo "[processWhatsAppEvent] Conversa resolvida/criada: ID {$conversation['id']}\n";
            
            // Se houver mídia, enfileira para processamento
            if (in_array($messageType, ['image', 'video', 'audio', 'document', 'ptt'])) {
                MediaProcessQueueService::enqueue($event['event_id']);
                
                echo "[processWhatsAppEvent] Mídia enfileirada para processamento: tipo {$messageType}\n";
            }
            
            return true;
        } else {
            echo "[processWhatsAppEvent] AVISO: ConversationService retornou null ou sem ID\n";
            return true; // Não falha o evento, mas loga o aviso
        }
        
    } catch (Exception $e) {
        throw new Exception("Erro ao processar conversa WhatsApp: " . $e->getMessage());
    }
}

/**
 * Processa eventos Asaas
 */
function processAsaasEvent(array $event, array $payload, array $metadata, PDO $db): bool
{
    // Delega para EventRouterService
    try {
        EventRouterService::route($event['event_id']);
        return true;
    } catch (Exception $e) {
        throw new Exception("Erro ao rotear evento Asaas: " . $e->getMessage());
    }
}

/**
 * Processa eventos Billing
 */
function processBillingEvent(array $event, array $payload, array $metadata, PDO $db): bool
{
    // Delega para EventRouterService
    try {
        EventRouterService::route($event['event_id']);
        return true;
    } catch (Exception $e) {
        throw new Exception("Erro ao rotear evento Billing: " . $e->getMessage());
    }
}
