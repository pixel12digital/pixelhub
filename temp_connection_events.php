<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== Eventos connection.update (últimos 7 dias) ===\n";
$stmt = $pdo->prepare("
    SELECT 
        received_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.session.id')) as session_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.state')) as state,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.reason')) as reason,
        payload_json
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        AND event_type = 'connection.update'
    ORDER BY received_at DESC
    LIMIT 30
");
$stmt->execute();

echo "received_at\tsession_id\tstate\treason\n";
echo str_repeat("-", 120) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['received_at']}\t{$row['session_id']}\t{$row['state']}\t{$row['reason']}\n";
}

echo "\n=== Últimos eventos por sessão ===\n";
$stmt = $pdo->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.session.id')) as session_id,
        event_type,
        received_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.state')) as state,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.reason')) as reason
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND event_type IN ('connection.update', 'message', 'message.ack')
        AND JSON_EXTRACT(payload_json, '$.session.id') IS NOT NULL
    ORDER BY session_id, received_at DESC
");
$stmt->execute();

$sessions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $session = $row['session_id'];
    if (!isset($sessions[$session])) {
        $sessions[$session] = [];
    }
    $sessions[$session][] = $row;
}

foreach ($sessions as $session => $events) {
    echo "\n=== Sessão: $session ===\n";
    foreach (array_slice($events, 0, 10) as $event) {
        $state = isset($event['state']) ? $event['state'] : 'N/A';
        $reason = isset($event['reason']) ? $event['reason'] : 'N/A';
        echo "{$event['received_at']}\t{$event['event_type']}\t{$state}\t{$reason}\n";
    }
}
