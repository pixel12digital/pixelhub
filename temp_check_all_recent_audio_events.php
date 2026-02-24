<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO TODOS OS EVENTOS DE ÁUDIO HOJE ===\n\n";

// Busca eventos com áudio hoje
$stmt = $db->prepare("
    SELECT id, event_id, event_type, created_at,
           JSON_EXTRACT(payload, '$.message.media.type') as media_type,
           JSON_EXTRACT(payload, '$.message.type') as msg_type,
           JSON_EXTRACT(payload, '$.raw.payload.type') as raw_type
    FROM communication_events 
    WHERE DATE(created_at) = '2026-02-24'
    AND event_type = 'whatsapp.inbound.message'
    AND (
        JSON_EXTRACT(payload, '$.message.media.type') = 'audio'
        OR JSON_EXTRACT(payload, '$.message.type') = 'ptt'
        OR JSON_EXTRACT(payload, '$.raw.payload.type') = 'ptt'
    )
    ORDER BY created_at DESC
");
$stmt->execute();
$audioEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos de áudio hoje: " . count($audioEvents) . "\n\n";

foreach ($audioEvents as $event) {
    echo "Event ID: {$event['event_id']}\n";
    echo "  DB ID: {$event['id']}\n";
    echo "  Criado: {$event['created_at']}\n";
    echo "  Media Type: " . ($event['media_type'] ?: 'NULL') . "\n";
    echo "  Msg Type: " . ($event['msg_type'] ?: 'NULL') . "\n";
    echo "  Raw Type: " . ($event['raw_type'] ?: 'NULL') . "\n";
    
    // Verifica se foi enfileirado
    $queueStmt = $db->prepare("
        SELECT id, status, created_at, error_message
        FROM media_process_queue
        WHERE event_id = ?
    ");
    $queueStmt->execute([$event['event_id']]);
    $queueItem = $queueStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($queueItem) {
        echo "  ✓ Enfileirado: Queue ID {$queueItem['id']}, Status: {$queueItem['status']}, Criado: {$queueItem['created_at']}\n";
        if ($queueItem['error_message']) {
            echo "    Erro: {$queueItem['error_message']}\n";
        }
    } else {
        echo "  ✗ NÃO enfileirado para processamento\n";
    }
    echo "\n";
}

// Verifica configuração do MediaProcessQueueService
echo "=== VERIFICANDO FILA DE MÍDIA (GERAL) ===\n\n";
$stmt = $db->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
           SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
           SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
           SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM media_process_queue
    WHERE DATE(created_at) = '2026-02-24'
");
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estatísticas da fila hoje:\n";
echo "  Total: {$stats['total']}\n";
echo "  Pending: {$stats['pending']}\n";
echo "  Processing: {$stats['processing']}\n";
echo "  Completed: {$stats['completed']}\n";
echo "  Failed: {$stats['failed']}\n";
