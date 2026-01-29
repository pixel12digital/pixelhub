<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== COMPARAÇÃO: MÍDIA 287 (funcionou) vs 291 (falhou) ===\n\n";

foreach ([287, 291] as $mediaId) {
    $stmt = $db->prepare("
        SELECT cm.*, ce.payload, ce.created_at as event_time
        FROM communication_media cm
        LEFT JOIN communication_events ce ON cm.event_id = ce.event_id
        WHERE cm.id = ?
    ");
    $stmt->execute([$mediaId]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "=== MÍDIA {$mediaId} " . ($m['stored_path'] ? '✅ FUNCIONOU' : '❌ FALHOU') . " ===\n";
    echo "stored_path: " . ($m['stored_path'] ?: '(vazio)') . "\n";
    echo "created_at: {$m['created_at']}\n";
    
    if ($m['payload']) {
        $p = json_decode($m['payload'], true);
        echo "raw.payload.id: " . ($p['raw']['payload']['id'] ?? 'N/A') . "\n";
        echo "raw.payload.type: " . ($p['raw']['payload']['type'] ?? 'N/A') . "\n";
        echo "raw.payload.mediaKey: " . (isset($p['raw']['payload']['mediaKey']) ? 'SIM (' . strlen($p['raw']['payload']['mediaKey']) . ' chars)' : 'N/A') . "\n";
        echo "raw.payload.deprecatedMms3Url: " . (isset($p['raw']['payload']['deprecatedMms3Url']) ? 'SIM (' . strlen($p['raw']['payload']['deprecatedMms3Url']) . ' chars)' : 'N/A') . "\n";
        echo "raw.payload.directPath: " . (isset($p['raw']['payload']['directPath']) ? 'SIM (' . strlen($p['raw']['payload']['directPath']) . ' chars)' : 'N/A') . "\n";
        echo "message.id: " . ($p['message']['id'] ?? 'N/A') . "\n";
    }
    echo "\n";
}
