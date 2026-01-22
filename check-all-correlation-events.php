<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Busca TODOS os eventos com correlation_id similar (últimas 24h)
echo "=== Todos os eventos com correlation_id (últimas 24h) ===\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id,
        JSON_EXTRACT(payload, '$.raw.payload.message.id') as raw_message_id,
        JSON_EXTRACT(payload, '$.event_id') as payload_event_id
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND correlation_id IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 50
");

$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo sprintf(
        "[%s] correlation_id: %s | event_id: %s | message_id: %s | status: %s\n",
        $event['created_at'],
        substr($event['correlation_id'], 0, 20) . '...',
        substr($event['event_id'], 0, 8) . '...',
        $event['message_id'] ?: ($event['raw_message_id'] ?: 'NULL'),
        $event['status']
    );
}

// Verifica se há eventos com message.id = "gwtest-123"
echo "\n=== Eventos com message.id = 'gwtest-123' ===\n";
$stmt2 = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id
    FROM communication_events 
    WHERE JSON_EXTRACT(payload, '$.message.id') = ?
    OR JSON_EXTRACT(payload, '$.raw.payload.message.id') = ?
    ORDER BY created_at DESC
");

$stmt2->execute(['gwtest-123', 'gwtest-123']);
$testEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if ($testEvents) {
    echo "Total: " . count($testEvents) . "\n\n";
    foreach ($testEvents as $e) {
        echo sprintf(
            "[%s] event_id: %s | correlation_id: %s | status: %s\n",
            $e['created_at'],
            $e['event_id'],
            substr($e['correlation_id'] ?: 'NULL', 0, 20) . '...',
            $e['status']
        );
    }
} else {
    echo "Nenhum evento encontrado com message.id = 'gwtest-123'\n";
}

