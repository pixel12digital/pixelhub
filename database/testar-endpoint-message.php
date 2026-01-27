<?php
/**
 * Testa o que o endpoint /message retorna para um evento de áudio
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/WhatsAppMediaService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\WhatsAppMediaService;

Env::load(__DIR__ . '/../.env');

$eventId = '3321fa98-e9dd-49d4-96b4-a32a9d2ef781'; // Evento de áudio mais recente

echo "=== TESTE DO ENDPOINT /message ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca evento
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.payload,
            ce.metadata
        FROM communication_events ce
        WHERE ce.event_id = ?
    ");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo "❌ Evento não encontrado!\n";
        exit;
    }
    
    echo "Evento encontrado:\n";
    echo "  ID: {$event['event_id']}\n";
    echo "  Created: {$event['created_at']}\n\n";
    
    // Simula o que o endpoint faz
    $payload = json_decode($event['payload'], true);
    $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
    
    $content = $payload['body'] 
        ?? $payload['text'] 
        ?? $payload['message']['text'] 
        ?? $payload['message']['body'] 
        ?? '';
    
    echo "Content extraído: " . ($content ?: '[VAZIO]') . "\n";
    echo "Direction: $direction\n\n";
    
    // Busca mídia
    echo "Buscando mídia via WhatsAppMediaService::getMediaByEventId()...\n";
    $mediaInfo = WhatsAppMediaService::getMediaByEventId($event['event_id']);
    
    if ($mediaInfo) {
        echo "✅ Mídia encontrada:\n";
        echo json_encode($mediaInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        
        // Verifica URL
        echo "URL da mídia: {$mediaInfo['url']}\n";
        
    } else {
        echo "❌ Mídia NÃO encontrada!\n";
    }
    
    // Monta mensagem como o endpoint faria
    $eventMetadata = json_decode($event['metadata'] ?? '{}', true);
    $message = [
        'id' => $event['event_id'],
        'direction' => $direction,
        'content' => $content,
        'timestamp' => $event['created_at'],
        'metadata' => $eventMetadata,
        'media' => $mediaInfo,
        'sent_by_name' => $eventMetadata['sent_by_name'] ?? null,
        'sent_by' => $eventMetadata['sent_by'] ?? null
    ];
    
    echo "\n=== MENSAGEM QUE SERIA RETORNADA ===\n";
    echo json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
