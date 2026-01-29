<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

$stmt = $db->query("
    SELECT ce.payload 
    FROM communication_media cm
    LEFT JOIN communication_events ce ON cm.event_id = ce.event_id
    WHERE cm.id = 291
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['payload']) {
    $p = json_decode($row['payload'], true);
    
    echo "=== URLS DISPONÍVEIS NO PAYLOAD 291 ===\n\n";
    
    $deprecatedUrl = $p['raw']['payload']['deprecatedMms3Url'] ?? null;
    $directPath = $p['raw']['payload']['directPath'] ?? null;
    $mediaKey = $p['raw']['payload']['mediaKey'] ?? null;
    
    echo "deprecatedMms3Url:\n{$deprecatedUrl}\n\n";
    echo "directPath:\n{$directPath}\n\n";
    echo "mediaKey:\n{$mediaKey}\n\n";
    
    // Tenta construir URL completa do WhatsApp CDN
    if ($directPath) {
        $cdnUrl = "https://mmg.whatsapp.net" . $directPath;
        echo "URL CDN construída:\n{$cdnUrl}\n";
    }
}
