<?php
/**
 * Verifica payload de um evento específico
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

// Busca um evento inbound da conversa 8
$stmt = $db->query("
    SELECT event_id, event_type, payload, metadata
    FROM communication_events
    WHERE conversation_id = 8
    AND event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 1
");
$event = $stmt->fetch();

if ($event) {
    echo "=== EVENTO INBOUND ===\n";
    echo "event_id: {$event['event_id']}\n";
    echo "event_type: {$event['event_type']}\n\n";
    
    echo "METADATA:\n";
    $metadata = json_decode($event['metadata'], true);
    echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "PAYLOAD (resumido):\n";
    $payload = json_decode($event['payload'], true);
    // Remove campos muito longos
    if (isset($payload['raw'])) {
        $payload['raw'] = '[RAW OMITIDO]';
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "\n=== CAMPOS RELEVANTES ===\n";
    echo "metadata.channel_id: " . ($metadata['channel_id'] ?? 'NULL') . "\n";
    echo "payload.session.id: " . ($payload['session']['id'] ?? 'NULL') . "\n";
    echo "payload.sessionId: " . ($payload['sessionId'] ?? 'NULL') . "\n";
    echo "payload.channelId: " . ($payload['channelId'] ?? 'NULL') . "\n";
}

// Busca evento outbound
$stmt = $db->query("
    SELECT event_id, event_type, payload, metadata
    FROM communication_events
    WHERE conversation_id = 8
    AND event_type = 'whatsapp.outbound.message'
    ORDER BY created_at DESC
    LIMIT 1
");
$event = $stmt->fetch();

if ($event) {
    echo "\n\n=== EVENTO OUTBOUND ===\n";
    echo "event_id: {$event['event_id']}\n";
    echo "event_type: {$event['event_type']}\n\n";
    
    echo "METADATA:\n";
    $metadata = json_decode($event['metadata'], true);
    echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "PAYLOAD (resumido):\n";
    $payload = json_decode($event['payload'], true);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    echo "\n=== CAMPOS RELEVANTES ===\n";
    echo "metadata.channel_id: " . ($metadata['channel_id'] ?? 'NULL') . "\n";
    echo "payload.channel_id: " . ($payload['channel_id'] ?? 'NULL') . "\n";
}
