<?php
/**
 * Script para verificar o payload completo de uma mensagem vazia
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

// Busca uma mensagem vazia
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.event_id = 'ab74b93d-c909-4a64-906b-6dc6a527aa0f'
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "=== PAYLOAD COMPLETO DA MENSAGEM VAZIA ===\n\n";
    echo "Event ID: {$event['event_id']}\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "Created: {$event['created_at']}\n\n";
    
    $payload = json_decode($event['payload'], true);
    $metadata = json_decode($event['metadata'] ?? '{}', true);
    
    echo "=== PAYLOAD (JSON formatado) ===\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    echo "=== METADATA ===\n";
    echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    
    echo "=== CAMPOS EXTRAÍDOS ===\n";
    echo "from: " . ($payload['from'] ?? 'NULL') . "\n";
    echo "to: " . ($payload['to'] ?? 'NULL') . "\n";
    echo "text: " . ($payload['text'] ?? 'NULL') . "\n";
    echo "body: " . ($payload['body'] ?? 'NULL') . "\n";
    echo "type: " . ($payload['type'] ?? 'NULL') . "\n";
    echo "message.from: " . ($payload['message']['from'] ?? 'NULL') . "\n";
    echo "message.to: " . ($payload['message']['to'] ?? 'NULL') . "\n";
    echo "message.text: " . ($payload['message']['text'] ?? 'NULL') . "\n";
    echo "message.body: " . ($payload['message']['body'] ?? 'NULL') . "\n";
    echo "message.type: " . ($payload['message']['type'] ?? 'NULL') . "\n";
    
} else {
    echo "Evento não encontrado!\n";
}

