<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$correlationId = '9858a507-cc4c-4632-8f92-462535eab504';

$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        source_system,
        tenant_id,
        status,
        error_message,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.session.id') as session_id,
        JSON_EXTRACT(payload, '$.raw.payload.message.id') as raw_message_id
    FROM communication_events 
    WHERE correlation_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$correlationId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Eventos com correlation_id: $correlationId ===\n";
echo "Total encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo "ID: {$event['id']}\n";
    echo "event_id: {$event['event_id']}\n";
    echo "event_type: {$event['event_type']}\n";
    echo "source_system: {$event['source_system']}\n";
    echo "status: {$event['status']}\n";
    echo "created_at: {$event['created_at']}\n";
    echo "message_id (payload.message.id): {$event['message_id']}\n";
    echo "message_from: {$event['message_from']}\n";
    echo "session_id: {$event['session_id']}\n";
    echo "raw_message_id: {$event['raw_message_id']}\n";
    if ($event['error_message']) {
        echo "ERROR: {$event['error_message']}\n";
    }
    echo "\n---\n\n";
}

// Verifica timezone do servidor
echo "=== Informações do Servidor ===\n";
echo "Hora atual PHP: " . date('Y-m-d H:i:s') . "\n";
echo "Timezone PHP: " . date_default_timezone_get() . "\n";

$stmt2 = $db->query("SELECT NOW() as db_time, @@session.time_zone as db_timezone");
$dbTime = $stmt2->fetch(PDO::FETCH_ASSOC);
echo "Hora do banco: {$dbTime['db_time']}\n";
echo "Timezone do banco: {$dbTime['db_timezone']}\n";

