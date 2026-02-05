<?php
/**
 * Inspeciona payload de um evento PTT/áudio outbound para encontrar mediaId.
 * Uso: php database/check-payload-ptt-outbound.php [--event_id=xxx]
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

$opt = getopt('', ['event_id:']);
$eventId = $opt['event_id'] ?? null;

$db = \PixelHub\Core\DB::getConnection();

if ($eventId) {
    $stmt = $db->prepare("SELECT event_id, event_type, payload FROM communication_events WHERE event_id = ?");
    $stmt->execute([$eventId]);
} else {
    $stmt = $db->prepare("
        SELECT event_id, event_type, payload FROM communication_events
        WHERE event_type = 'whatsapp.outbound.message'
        AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) = 'ptt'
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
}

$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Nenhum evento PTT outbound encontrado.\n";
    exit(1);
}

$payload = json_decode($row['payload'], true);
echo "=== PAYLOAD PTT OUTBOUND (event_id={$row['event_id']}) ===\n\n";
echo "Keys principais: " . implode(', ', array_keys($payload)) . "\n\n";

// Busca recursiva por chaves que possam ser mediaId
$interestingKeys = ['id', 'mediaId', 'media_id', 'mediaUrl', 'media_url', 'mediaKey', 'directPath', 'key'];
function findPaths($arr, $prefix = '', $targets = []) {
    $found = [];
    foreach ($arr as $k => $v) {
        $path = $prefix ? "{$prefix}.{$k}" : $k;
        if (in_array($k, $targets) && $v !== null && $v !== '') {
            $found[$path] = is_string($v) ? substr($v, 0, 100) . (strlen($v) > 100 ? '...' : '') : json_encode($v);
        }
        if (is_array($v)) {
            $found = array_merge($found, findPaths($v, $path, $targets));
        }
    }
    return $found;
}
$paths = findPaths($payload, '', $interestingKeys);
echo "Caminhos com id/mediaId/mediaUrl/etc:\n";
foreach ($paths as $path => $val) echo "  {$path}: {$val}\n";

echo "\n=== Payload completo (JSON truncado 3000 chars) ===\n";
echo substr(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 3000) . "\n";
