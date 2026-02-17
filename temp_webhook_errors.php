<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== Webhooks com erro (últimas 72h) ===\n";
$stmt = $pdo->prepare("
    SELECT 
        received_at,
        event_type,
        error_message,
        payload_json
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
        AND processed = 0
        AND error_message IS NOT NULL
    ORDER BY received_at DESC
    LIMIT 20
");
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "\n=== {$row['received_at']} ===\n";
    echo "Event: {$row['event_type']}\n";
    echo "Error: {$row['error_message']}\n";
    echo "Payload: " . substr($row['payload_json'], 0, 500) . "...\n";
    echo "---\n";
}

echo "\n=== Todos os webhooks não processados (últimas 24h) ===\n";
$stmt = $pdo->prepare("
    SELECT 
        received_at,
        event_type,
        LEFT(payload_json, 300) as payload_preview
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND processed = 0
    ORDER BY received_at DESC
    LIMIT 30
");
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['received_at']}\t{$row['event_type']}\n";
    echo "Preview: {$row['payload_preview']}\n";
    echo "---\n";
}
