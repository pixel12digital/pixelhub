<?php
require_once __DIR__ . '/../public/index.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

$sql = "SELECT 
    ce.event_id,
    ce.created_at,
    JSON_EXTRACT(ce.payload, '$.type') as msg_type,
    JSON_EXTRACT(ce.payload, '$.message.type') as msg_type2,
    JSON_EXTRACT(ce.payload, '$.from') as from_num,
    cm.stored_path,
    cm.media_type
FROM communication_events ce
LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.payload LIKE '%5511965221349%'
ORDER BY ce.created_at DESC
LIMIT 20";

$stmt = $db->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Resultados: " . count($results) . "\n\n";

foreach ($results as $r) {
    $type = trim($r['msg_type'], '"') ?: trim($r['msg_type2'], '"');
    if (in_array(strtolower($type), ['audio','ptt','voice','image','video'])) {
        echo "MIDIA: {$r['event_id']}\n";
        echo "Tipo: {$type}\n";
        echo "Data: {$r['created_at']}\n";
        echo "From: " . trim($r['from_num'], '"') . "\n";
        if ($r['stored_path']) {
            echo "Processada: SIM - {$r['stored_path']}\n";
        } else {
            echo "Processada: NAO\n";
        }
        echo "\n";
    }
}








