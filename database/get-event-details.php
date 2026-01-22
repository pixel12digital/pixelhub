<?php
/**
 * Script para buscar detalhes completos de um evento
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$eventId = $argv[1] ?? 'b22f0721-1055-45c9-b0be-0b28444886fc';

echo "=== DETALHES DO EVENTO ===\n\n";
echo "Event ID: {$eventId}\n\n";

$stmt = $db->prepare("
    SELECT *
    FROM communication_events
    WHERE event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("❌ Evento não encontrado!\n");
}

echo "ID: {$event['id']}\n";
echo "Event Type: {$event['event_type']}\n";
echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
echo "Created At: {$event['created_at']}\n";
echo "\n";

echo "PAYLOAD:\n";
echo str_repeat("-", 70) . "\n";
$payload = json_decode($event['payload'], true);
if ($payload) {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo $event['payload'];
}
echo "\n\n";

echo "METADATA:\n";
echo str_repeat("-", 70) . "\n";
$metadata = json_decode($event['metadata'], true);
if ($metadata) {
    echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo $event['metadata'] ?? 'NULL';
}
echo "\n\n";

