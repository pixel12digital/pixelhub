<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

// Busca detalhes do evento que gerou media 291
$stmt = $db->query("
    SELECT cm.*, ce.id as ce_id, ce.payload, ce.created_at as event_time
    FROM communication_media cm
    LEFT JOIN communication_events ce ON cm.event_id = ce.event_id
    WHERE cm.id = 291
");
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if ($m) {
    echo "=== MÍDIA 291 ===\n";
    echo "event_id: {$m['event_id']}\n";
    echo "media_type: {$m['media_type']}\n";
    echo "mime_type: {$m['mime_type']}\n";
    echo "stored_path: " . ($m['stored_path'] ?: '(vazio)') . "\n";
    echo "created_at: {$m['created_at']}\n\n";
    
    if ($m['payload']) {
        $p = json_decode($m['payload'], true);
        echo "=== PAYLOAD DO EVENTO ===\n";
        echo "raw.payload.id: " . ($p['raw']['payload']['id'] ?? 'N/A') . "\n";
        echo "raw.payload.type: " . ($p['raw']['payload']['type'] ?? 'N/A') . "\n";
        echo "raw.payload.mediaKey: " . (isset($p['raw']['payload']['mediaKey']) ? substr($p['raw']['payload']['mediaKey'], 0, 30) . '...' : 'N/A') . "\n";
        echo "message.id: " . ($p['message']['id'] ?? 'N/A') . "\n";
    }
}
