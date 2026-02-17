<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== Webhooks recebidos (últimas 48h) ===\n";
$stmt = $pdo->prepare("
    SELECT 
        received_at,
        event_type,
        processed,
        error_message
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ORDER BY received_at DESC
    LIMIT 20
");
$stmt->execute();

echo "received_at\tevent_type\tprocessed\terror_message\n";
echo str_repeat("-", 120) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['received_at']}\t{$row['event_type']}\t{$row['processed']}\t{$row['error_message']}\n";
}

echo "\n=== Contagem de webhooks por tipo (últimos 7 dias) ===\n";
$stmt = $pdo->prepare("
    SELECT 
        DATE(received_at) as data,
        event_type,
        COUNT(*) as total,
        SUM(processed) as processados,
        SUM(CASE WHEN processed = 0 THEN 1 ELSE 0 END) as falhados
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
    GROUP BY DATE(received_at), event_type
    ORDER BY data DESC, total DESC
    LIMIT 50
");
$stmt->execute();

echo "data\tevent_type\ttotal\tprocessados\tfalhados\n";
echo str_repeat("-", 80) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['data']}\t{$row['event_type']}\t{$row['total']}\t{$row['processados']}\t{$row['falhados']}\n";
}

echo "\n=== Webhooks com erro (últimas 72h) ===\n";
$stmt = $pdo->prepare("
    SELECT 
        received_at,
        event_type,
        error_message,
        LEFT(payload_json, 200) as payload_preview
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
        AND processed = 0
        AND error_message IS NOT NULL
    ORDER BY received_at DESC
    LIMIT 10
");
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['received_at']}\t{$row['event_type']}\t{$row['error_message']}\n";
    echo "Payload: {$row['payload_preview']}...\n";
    echo "---\n";
}

echo "\n=== Verificando sessões no payload ===\n";
$stmt = $pdo->prepare("
    SELECT 
        received_at,
        event_type,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.session')) as session,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.id')) as whatsapp_id
    FROM webhook_raw_logs 
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND (payload_json LIKE '%session%' OR payload_json LIKE '%id%')
    ORDER BY received_at DESC
    LIMIT 20
");
$stmt->execute();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['received_at']}\t{$row['event_type']}\t{$row['session']}\t{$row['whatsapp_id']}\n";
}
