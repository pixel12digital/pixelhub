<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');
$pdo = DB::getConnection();

echo "=== Payload do último evento da conversa 115 ===\n\n";
$sql = "SELECT id, event_id, event_type, payload, created_at
        FROM communication_events 
        WHERE conversation_id = 115
        ORDER BY created_at DESC
        LIMIT 1";
$stmt = $pdo->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "ID: {$row['id']}\n";
echo "Event Type: {$row['event_type']}\n";
echo "Created: {$row['created_at']}\n";
echo "\nPayload:\n";
print_r(json_decode($row['payload'], true));

echo "\n\n=== Último evento inbound (message_received) global ===\n\n";
$sql = "SELECT id, conversation_id, event_type, payload, created_at
        FROM communication_events 
        WHERE event_type LIKE '%inbound%' OR event_type LIKE '%received%'
        ORDER BY created_at DESC
        LIMIT 1";
$stmt = $pdo->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "ID: {$row['id']}\n";
    echo "Conv: {$row['conversation_id']}\n";
    echo "Type: {$row['event_type']}\n";
    echo "Created: {$row['created_at']}\n";
} else {
    echo "Nenhum evento inbound encontrado\n";
}
