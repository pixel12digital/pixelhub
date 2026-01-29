<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== ÚLTIMAS 10 MÍDIAS ===\n";
$stmt = $db->query("
    SELECT id, media_type, mime_type, stored_path, file_size, created_at 
    FROM communication_media 
    ORDER BY id DESC 
    LIMIT 10
");

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $status = (strpos($m['mime_type'], 'audio') !== false || strpos($m['mime_type'], 'ogg') !== false) ? '✅' : '❌';
    echo "[{$m['id']}] {$status} mime={$m['mime_type']} size={$m['file_size']} path={$m['stored_path']}\n";
}
