<?php
/**
 * Lista mídias de áudio em communication_media.
 * Uso: php database/check-media-audio.php [--limit=10]
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($c) {
        if (strncmp('PixelHub\\', $c, 9) !== 0) return;
        $f = __DIR__ . '/../src/' . str_replace('\\', '/', substr($c, 9)) . '.php';
        if (file_exists($f)) require $f;
    });
}
\PixelHub\Core\Env::load();

$opt = getopt('', ['limit:']);
$limit = isset($opt['limit']) ? (int)$opt['limit'] : 10;

$db = \PixelHub\Core\DB::getConnection();

$stmt = $db->prepare("
    SELECT event_id, media_type, stored_path, file_name, created_at
    FROM communication_media
    WHERE media_type IN ('audio','voice','ptt')
    ORDER BY created_at DESC
    LIMIT ?
");
$stmt->execute([$limit]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== MÍDIAS DE ÁUDIO (últimos registros) ===\n\n";
echo count($rows) . " encontrados\n\n";
foreach ($rows as $r) {
    $path = $r['stored_path'] ?? '-';
    echo "  {$r['created_at']} | event_id={$r['event_id']} | type={$r['media_type']}\n";
    echo "    stored_path={$path}\n";
}
