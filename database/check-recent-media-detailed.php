<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== ÚLTIMAS 15 MÍDIAS (detalhado) ===\n\n";
$stmt = $db->query("
    SELECT 
        cm.id,
        cm.media_type,
        cm.mime_type,
        cm.stored_path,
        cm.file_size,
        cm.file_name,
        cm.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) as raw_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.mimetype')) as raw_mimetype
    FROM communication_media cm
    LEFT JOIN communication_events ce ON cm.event_id = ce.event_id
    ORDER BY cm.id DESC 
    LIMIT 15
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $hasPath = !empty($m['stored_path']) ? 'OK' : 'VAZIO';
    $hasSize = !empty($m['file_size']) && $m['file_size'] > 0 ? 'OK' : 'VAZIO';
    
    echo "[{$m['id']}] {$m['created_at']}\n";
    echo "    type: {$m['media_type']} | raw_type: {$m['raw_type']}\n";
    echo "    mime: {$m['mime_type']} | raw_mime: {$m['raw_mimetype']}\n";
    echo "    path: [{$hasPath}] {$m['stored_path']}\n";
    echo "    size: [{$hasSize}] {$m['file_size']} bytes\n";
    echo "\n";
}
