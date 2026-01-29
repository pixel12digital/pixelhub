<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');
$pdo = DB::getConnection();

$eventId = 'caaa9046-0eed-40e6-92b2-9ea180e0efd8';
echo "=== Verificando evento: {$eventId} ===\n\n";

$sql = "SELECT id, event_id, event_type, status, error_message, payload, metadata, created_at
        FROM communication_events 
        WHERE event_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "Evento não encontrado!\n";
    exit;
}

echo "ID: {$event['id']}\n";
echo "Type: {$event['event_type']}\n";
echo "Status: {$event['status']}\n";
echo "Error: " . ($event['error_message'] ?: 'N/A') . "\n";
echo "Created: {$event['created_at']}\n\n";

echo "=== PAYLOAD ===\n";
$payload = json_decode($event['payload'], true);
print_r($payload);

echo "\n=== METADATA ===\n";
$metadata = json_decode($event['metadata'], true);
print_r($metadata);

// Verificar últimos eventos outbound com imagem
echo "\n\n=== Últimos 3 eventos outbound de imagem ===\n";
$sql = "SELECT event_id, status, error_message,
               JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as msg_type,
               JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.gateway_response')) as gw_response,
               created_at
        FROM communication_events 
        WHERE event_type LIKE '%outbound%' OR event_type = 'message_sent'
        ORDER BY created_at DESC
        LIMIT 5";
$stmt = $pdo->query($sql);
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\n[{$row['created_at']}] {$row['event_id']}\n";
    echo "  status: {$row['status']}, type: {$row['msg_type']}\n";
    echo "  error: " . ($row['error_message'] ?: 'N/A') . "\n";
    if ($row['gw_response']) {
        echo "  gw_response: " . substr($row['gw_response'], 0, 200) . "\n";
    }
}
