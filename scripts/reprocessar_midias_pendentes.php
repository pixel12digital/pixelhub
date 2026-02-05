<?php
/**
 * Reprocessa mídias de eventos que não têm registro em communication_media.
 *
 * Encontra eventos whatsapp.inbound.message com tipo ptt/audio/image/video/document/sticker
 * sem registro em communication_media e tenta baixar/salvar a mídia.
 *
 * Uso: php scripts/reprocessar_midias_pendentes.php [--days=7] [--limit=50]
 *
 * Cron sugerido (diário): 0 3 * * * cd /path/to/project && php scripts/reprocessar_midias_pendentes.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

\PixelHub\Core\Env::load();

$options = getopt('', ['days:', 'limit:']);
$days = isset($options['days']) ? (int) $options['days'] : 7;
$limit = isset($options['limit']) ? (int) $options['limit'] : 50;

$db = \PixelHub\Core\DB::getConnection();

echo "=== REPROCESSAMENTO DE MÍDIAS PENDENTES ===\n";
echo "Período: últimos {$days} dias | Limite: {$limit} eventos\n\n";

$stmt = $db->prepare("
    SELECT ce.id, ce.event_id, ce.payload, ce.tenant_id, ce.created_at
    FROM communication_events ce
    LEFT JOIN communication_media cm ON cm.event_id = ce.event_id
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    AND cm.id IS NULL
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) IN ('audio', 'ptt', 'image', 'video', 'document', 'sticker')
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) IN ('audio', 'ptt', 'image', 'video', 'document', 'sticker')
    )
    ORDER BY ce.created_at DESC
    LIMIT ?
");
$stmt->execute([$days, $limit]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos sem mídia encontrados: " . count($events) . "\n\n";

if (empty($events)) {
    echo "Nada a processar.\n";
    exit(0);
}

$ok = 0;
$fail = 0;

foreach ($events as $e) {
    $eventId = $e['event_id'];
    $type = json_decode($e['payload'], true)['type'] ?? json_decode($e['payload'], true)['raw']['payload']['type'] ?? '?';
    echo "  [{$e['created_at']}] event_id={$eventId} type={$type} ... ";

    try {
        $result = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($e);
        if ($result && !empty($result['url'])) {
            echo "OK\n";
            $ok++;
        } else {
            echo "sem mídia (payload sem mediaId?)\n";
            $fail++;
        }
    } catch (\Exception $ex) {
        echo "ERRO: " . $ex->getMessage() . "\n";
        $fail++;
    }
}

echo "\nConcluído: {$ok} processados, {$fail} falhas/sem mídia.\n";
