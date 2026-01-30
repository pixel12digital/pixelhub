<?php
/**
 * Diagnóstico: Áudios recentes
 */
require_once __DIR__ . '/../vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\DB;
Env::load(__DIR__ . '/../.env');
$db = DB::getConnection();

echo "=== Eventos de Audio (ultimas 2h) ===\n";
$sql = "SELECT id, event_type, created_at,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) AS raw_type,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.mimetype')) AS mimetype
FROM communication_events 
WHERE event_type = 'whatsapp.inbound.message'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
  AND (payload LIKE '%audio%' OR payload LIKE '%ptt%' OR payload LIKE '%OggS%')
ORDER BY created_at DESC
LIMIT 10";
$stmt = $db->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as $e) {
    echo "ID: {$e['id']}, From: {$e['msg_from']}, Type: {$e['raw_type']}, Mime: {$e['mimetype']}, Created: {$e['created_at']}\n";
}
if (empty($events)) echo "Nenhum evento de audio encontrado\n";

echo "\n=== Midias salvas (ultimas 2h) ===\n";
$sql2 = "SELECT id, event_id, media_type, mime_type, stored_path, created_at FROM communication_media WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) ORDER BY created_at DESC LIMIT 10";
$stmt2 = $db->query($sql2);
$medias = $stmt2->fetchAll(PDO::FETCH_ASSOC);
foreach ($medias as $m) {
    echo "ID: {$m['id']}, Event: {$m['event_id']}, Type: {$m['media_type']}, Mime: {$m['mime_type']}, Path: {$m['stored_path']}, Created: {$m['created_at']}\n";
}
if (empty($medias)) echo "Nenhuma midia salva\n";

echo "\n=== Ultimos 5 eventos INBOUND (qualquer tipo) ===\n";
$sql3 = "SELECT id, event_type, created_at,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
    JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) AS raw_type
FROM communication_events 
WHERE event_type = 'whatsapp.inbound.message'
ORDER BY created_at DESC
LIMIT 5";
$stmt3 = $db->query($sql3);
$recent = $stmt3->fetchAll(PDO::FETCH_ASSOC);
foreach ($recent as $r) {
    echo "ID: {$r['id']}, From: {$r['msg_from']}, Type: {$r['raw_type']}, Created: {$r['created_at']}\n";
}
