<?php
/**
 * Worker para processar eventos em status 'queued'
 * 
 * Este script processa eventos que estão aguardando processamento,
 * criando conversas (vinculadas ou não vinculadas) conforme necessário.
 * 
 * Uso: php scripts/process_queued_events.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Config\Database;
use PixelHub\Core\Env;
use PixelHub\Services\ConversationService;

Env::load(dirname(__DIR__));

echo "=== WORKER: PROCESSAR EVENTOS ENFILEIRADOS ===\n";
echo "Iniciado em: " . date('Y-m-d H:i:s') . "\n\n";

$db = Database::getInstance()->getConnection();

// Buscar eventos em 'queued'
$stmt = $db->query("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        payload,
        metadata,
        retry_count,
        created_at
    FROM communication_events
    WHERE status = 'queued'
    AND event_type LIKE '%message%'
    ORDER BY created_at ASC
    LIMIT 100
");

$queuedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos encontrados: " . count($queuedEvents) . "\n\n";

if (count($queuedEvents) === 0) {
    echo "Nenhum evento para processar.\n";
    exit(0);
}

$processed = 0;
$failed = 0;

foreach ($queuedEvents as $event) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "[{$event['id']}] Processando evento: {$event['event_type']}\n";
    echo "Tenant: " . ($event['tenant_id'] ?? 'NULL (não vinculado)') . "\n";
    echo "Criado: {$event['created_at']}\n";
    
    $payload = json_decode($event['payload'], true);
    $metadata = $event['metadata'] ? json_decode($event['metadata'], true) : null;
    
    // Marca como 'processing'
    $updateStmt = $db->prepare("
        UPDATE communication_events 
        SET status = 'processing', updated_at = NOW()
        WHERE event_id = ?
    ");
    $updateStmt->execute([$event['event_id']]);
    
    try {
        // Tenta resolver conversa
        $conversation = ConversationService::resolveConversation([
            'event_type' => $event['event_type'],
            'source_system' => $event['source_system'],
            'tenant_id' => $event['tenant_id'],
            'payload' => $payload,
            'metadata' => $metadata,
        ]);
        
        if ($conversation) {
            echo "✓ Conversa resolvida: ID {$conversation['id']}\n";
            
            if ($conversation['tenant_id']) {
                echo "  Tipo: VINCULADA (Tenant {$conversation['tenant_id']})\n";
            } else {
                echo "  Tipo: NÃO VINCULADA (aparecerá no Inbox para vinculação manual)\n";
            }
            
            // Atualiza evento
            $updateStmt = $db->prepare("
                UPDATE communication_events 
                SET conversation_id = ?, status = 'processed', processed_at = NOW()
                WHERE event_id = ?
            ");
            $updateStmt->execute([$conversation['id'], $event['event_id']]);
            
            $processed++;
            echo "✓ Evento processado com sucesso\n";
            
        } else {
            echo "✗ Conversa não pôde ser resolvida\n";
            
            // Marca como failed
            $updateStmt = $db->prepare("
                UPDATE communication_events 
                SET status = 'failed', 
                    error_message = 'conversation_not_resolved', 
                    processed_at = NOW(),
                    retry_count = retry_count + 1
                WHERE event_id = ?
            ");
            $updateStmt->execute([$event['event_id']]);
            
            $failed++;
        }
        
    } catch (Exception $e) {
        echo "✗ ERRO: " . $e->getMessage() . "\n";
        
        // Marca como failed
        $updateStmt = $db->prepare("
            UPDATE communication_events 
            SET status = 'failed', 
                error_message = ?, 
                processed_at = NOW(),
                retry_count = retry_count + 1
            WHERE event_id = ?
        ");
        $updateStmt->execute([
            substr($e->getMessage(), 0, 250),
            $event['event_id']
        ]);
        
        $failed++;
    }
    
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "RESUMO:\n";
echo "  Processados com sucesso: $processed\n";
echo "  Falhados: $failed\n";
echo "  Total: " . ($processed + $failed) . "\n";
echo "\nFinalizado em: " . date('Y-m-d H:i:s') . "\n";
