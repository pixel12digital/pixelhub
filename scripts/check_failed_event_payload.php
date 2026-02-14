<?php
/**
 * Script para verificar payload dos eventos failed
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== Verificando payload dos eventos failed ===\n\n";

$db = DB::getConnection();

$sql = "
    SELECT id, event_id, payload, metadata, created_at 
    FROM communication_events 
    WHERE status = 'failed' 
    AND event_type = 'whatsapp.inbound.message'
    AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY created_at DESC
    LIMIT 2
";

$stmt = $db->query($sql);
$events = $stmt->fetchAll();

foreach ($events as $event) {
    echo "Event ID: {$event['event_id']}\n";
    echo "Created: {$event['created_at']}\n";
    echo "\nPayload:\n";
    $payload = json_decode($event['payload'], true);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\nMetadata:\n";
    $metadata = json_decode($event['metadata'], true);
    echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n" . str_repeat("=", 80) . "\n\n";
}
