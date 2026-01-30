<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');
$stmt = $pdo->query('SELECT @@session.time_zone as tz, NOW() as now_time');
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Timezone: {$r['tz']}, NOW: {$r['now_time']}\n";

$stmt = $pdo->query("SELECT id, contact_name, last_message_at, updated_at FROM conversations WHERE contact_name LIKE '%Charles%' OR contact_external_id LIKE '%554796164699%' ORDER BY id DESC LIMIT 1");
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if ($c) {
    echo "Charles: ID={$c['id']}, last_message_at='{$c['last_message_at']}', updated_at='{$c['updated_at']}'\n";
    if (preg_match('/(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/', $c['last_message_at'], $m)) {
        echo "Regex result: {$m[3]}/{$m[2]} {$m[4]}:{$m[5]}\n";
    } else {
        echo "Regex: NO MATCH (timestamp='{$c['last_message_at']}')\n";
    }
}
