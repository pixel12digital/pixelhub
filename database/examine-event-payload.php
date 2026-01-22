<?php
/**
 * Examina o payload completo de um evento específico
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$eventId = $argv[1] ?? 'b22f0721-1055-45c9-b0be-0b28444886fc';

echo "=== EXAMINANDO PAYLOAD DO EVENTO ===\n\n";
echo "Event ID: {$eventId}\n\n";

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata,
        ce.tenant_id
    FROM communication_events ce
    WHERE ce.event_id = ?
");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("❌ Evento não encontrado!\n");
}

echo "✅ Evento encontrado:\n";
echo "   Type: {$event['event_type']}\n";
echo "   Created: {$event['created_at']}\n";
echo "   Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
echo "\n";

echo "PAYLOAD COMPLETO:\n";
echo str_repeat("=", 80) . "\n";
$payload = json_decode($event['payload'], true);
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n\n";

echo "METADATA:\n";
echo str_repeat("=", 80) . "\n";
$metadata = json_decode($event['metadata'], true);
echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n\n";

echo "CAMPOS ESPECÍFICOS:\n";
echo str_repeat("=", 80) . "\n";
echo "From: " . ($payload['from'] ?? ($payload['message']['from'] ?? 'N/A')) . "\n";
echo "To: " . ($payload['to'] ?? ($payload['message']['to'] ?? 'N/A')) . "\n";
echo "Session ID: " . ($payload['sessionId'] ?? ($payload['session']['id'] ?? 'N/A')) . "\n";
echo "Channel ID (metadata): " . ($metadata['channel_id'] ?? 'N/A') . "\n";
echo "Message Body: " . ($payload['message']['body'] ?? 'N/A') . "\n";


