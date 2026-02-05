<?php
/**
 * Lista eventos inbound sem registro em communication_media.
 * Útil para diagnosticar mídias pendentes.
 * Uso: php database/check-eventos-sem-midia.php [--days=7] [--limit=10]
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

$opt = getopt('', ['days:', 'limit:']);
$days = isset($opt['days']) ? (int)$opt['days'] : 7;
$limit = isset($opt['limit']) ? (int)$opt['limit'] : 10;

$db = \PixelHub\Core\DB::getConnection();

$stmt = $db->prepare("
    SELECT ce.event_id, ce.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) as tipo_direto,
           JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) as tipo_raw
    FROM communication_events ce
    LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    AND cm.id IS NULL
    ORDER BY ce.created_at DESC
    LIMIT ?
");
$stmt->execute([$days, $limit]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== EVENTOS INBOUND SEM MÍDIA (últimos {$days} dias) ===\n\n";
echo count($rows) . " encontrados\n\n";
foreach ($rows as $r) {
    $tipo = $r['tipo_raw'] ?: $r['tipo_direto'] ?: '-';
    echo "  {$r['created_at']} | event_id={$r['event_id']} | tipo={$tipo}\n";
}
