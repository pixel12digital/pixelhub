<?php

/**
 * Script para verificar se os eventos têm channel_id no metadata
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO CHANNEL_ID NOS EVENTOS ===\n\n";

// Busca eventos recentes
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        created_at,
        JSON_EXTRACT(metadata, '$.channel_id') as metadata_channel_id,
        JSON_EXTRACT(payload, '$.channel_id') as payload_channel_id,
        JSON_EXTRACT(payload, '$.channel') as payload_channel,
        JSON_EXTRACT(payload, '$.session.id') as payload_session_id,
        metadata,
        payload
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY created_at DESC
    LIMIT 5
");
$events = $stmt->fetchAll();

echo "Eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo "--- Evento ID: {$event['event_id']} ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "metadata.channel_id: " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
    echo "payload.channel_id: " . ($event['payload_channel_id'] ?: 'NULL') . "\n";
    echo "payload.channel: " . ($event['payload_channel'] ?: 'NULL') . "\n";
    echo "payload.session.id: " . ($event['payload_session_id'] ?: 'NULL') . "\n";
    
    // Verifica metadata completo
    $metadata = json_decode($event['metadata'] ?? '{}', true);
    echo "metadata completo: " . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    // Verifica payload para channel_id
    $payload = json_decode($event['payload'] ?? '{}', true);
    $channelIdFromPayload = $payload['channel_id'] 
        ?? $payload['channel'] 
        ?? $payload['session']['id'] 
        ?? $payload['session']['session']
        ?? $payload['data']['session']['id'] ?? null
        ?? $payload['data']['session']['session'] ?? null
        ?? $payload['data']['channel'] ?? null
        ?? null;
    
    echo "channel_id extraído do payload: " . ($channelIdFromPayload ?: 'NULL') . "\n";
    echo "created_at: {$event['created_at']}\n";
    echo "\n";
}

