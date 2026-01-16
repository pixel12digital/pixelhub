<?php
require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$phone = '5511965221349';
$db = DB::getConnection();

echo "=== Buscando Mídia de {$phone} ===\n\n";

// Busca eventos desse número
$stmt = $db->query("
    SELECT event_id, created_at, payload 
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message'
    AND payload LIKE '%{$phone}%'
    ORDER BY created_at DESC 
    LIMIT 20
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado\n";
    exit(0);
}

echo "✅ Encontrados " . count($events) . " eventos\n\n";

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    if (!$payload) continue;
    
    $type = $payload['type'] ?? $payload['message']['type'] ?? 'text';
    $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
    
    // Verifica se é mídia
    if (in_array(strtolower($type), ['audio', 'ptt', 'voice', 'image', 'video', 'document', 'sticker'])) {
        echo "📎 Evento: {$event['event_id']}\n";
        echo "   Data: {$event['created_at']}\n";
        echo "   Tipo: {$type}\n";
        echo "   From: {$from}\n";
        
        // Verifica se foi processado
        $stmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
        $stmt->execute([$event['event_id']]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($media) {
            echo "   ✅ Mídia processada: {$media['stored_path']}\n";
            echo "   URL: " . pixelhub_url('/communication-hub/media?path=' . urlencode($media['stored_path'])) . "\n";
        } else {
            echo "   ❌ Mídia NÃO processada\n";
        }
        
        // Mostra onde está o mediaId
        $mediaId = $payload['mediaId'] ?? $payload['media_id'] ?? $payload['id'] ?? $payload['message']['id'] ?? null;
        if ($mediaId) {
            echo "   MediaId: {$mediaId}\n";
        }
        
        echo "\n";
        break; // Mostra apenas o primeiro
    }
}

