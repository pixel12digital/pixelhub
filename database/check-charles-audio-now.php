<?php
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== EVENTOS RECENTES DE 554796164699 (última 1h) ===\n";
$stmt = $db->query("
    SELECT id, event_id, event_type, status, created_at,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.type')) as raw_type,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from
    FROM communication_events 
    WHERE (payload LIKE '%554796164699%' OR payload LIKE '%4796164699%')
      AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($events)) {
    echo "   NENHUM evento encontrado na última hora!\n";
} else {
    foreach ($events as $e) {
        echo "[{$e['created_at']}] id={$e['id']} raw_type={$e['raw_type']} status={$e['status']}\n";
    }
}

echo "\n=== ÚLTIMAS 5 MÍDIAS ===\n";
$stmt2 = $db->query("
    SELECT cm.id, cm.media_type, cm.mime_type, cm.stored_path, cm.created_at, ce.id as ce_id 
    FROM communication_media cm 
    LEFT JOIN communication_events ce ON cm.event_id = ce.event_id 
    ORDER BY cm.id DESC 
    LIMIT 5
");
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $m) {
    echo "[{$m['created_at']}] media_id={$m['id']} type={$m['media_type']} path=" . ($m['stored_path'] ?: '(vazio)') . "\n";
}

echo "\n=== FIM ===\n";
