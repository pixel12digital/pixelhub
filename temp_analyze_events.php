<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;port=3306;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== Eventos WhatsApp dos últimos 7 dias ===\n";

// Extrair channel_account_id do metadata
$stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as data,
        event_type,
        COUNT(*) as eventos,
        MIN(created_at) as primeiro,
        MAX(created_at) as ultimo
    FROM communication_events 
    WHERE created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)
        AND JSON_EXTRACT(metadata, '$.channel_type') = '\"whatsapp\"'
    GROUP BY DATE(created_at), event_type
    ORDER BY data DESC, eventos DESC
    LIMIT 50
");
$stmt->execute();

echo "data\tevent_type\teventos\tprimeiro\tultimo\n";
echo str_repeat("-", 100) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['data']}\t{$row['event_type']}\t{$row['eventos']}\t{$row['primeiro']}\t{$row['ultimo']}\n";
}

echo "\n=== Últimos eventos de conexão (últimas 72h) ===\n";

// Buscar eventos de conexão
$stmt2 = $pdo->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')) as channel_account_id,
        event_type,
        payload,
        created_at
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
        AND JSON_EXTRACT(metadata, '$.channel_type') = '\"whatsapp\"'
        AND (event_type LIKE '%connection%' OR event_type LIKE '%disconnect%' OR event_type LIKE '%unpaired%' OR event_type LIKE '%qr%')
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt2->execute();

while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    $payload = json_decode($row['payload'], true);
    $status = $payload['status'] ?? 'N/A';
    $reason = $payload['reason'] ?? 'N/A';
    echo "{$row['created_at']}\t{$row['channel_account_id']}\t{$row['event_type']}\tstatus={$status}\treason={$reason}\n";
}

echo "\n=== Padrão de mensagens por hora (últimas 48h) ===\n";

// Verificar padrão de mensagens por hora
$stmt3 = $pdo->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')) as channel_account_id,
        DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hora,
        SUM(CASE WHEN event_type = 'message.received' THEN 1 ELSE 0 END) as mensagens_recebidas,
        SUM(CASE WHEN event_type = 'message.sent' THEN 1 ELSE 0 END) as mensagens_enviadas,
        COUNT(*) as total_eventos
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND JSON_EXTRACT(metadata, '$.channel_type') = '\"whatsapp\"'
    GROUP BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')), DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')
    ORDER BY hora DESC, channel_account_id
    LIMIT 100
");
$stmt3->execute();

echo "hora\tchannel\trecebidas\tenviadas\ttotal\n";
echo str_repeat("-", 80) . "\n";

while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['hora']}\t{$row['channel_account_id']}\t{$row['mensagens_recebidas']}\t{$row['mensagens_enviadas']}\t{$row['total_eventos']}\n";
}

echo "\n=== Últimos eventos por sessão ===\n";

// Verificar eventos mais recentes por sessão
$stmt4 = $pdo->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')) as channel_account_id,
        event_type,
        payload,
        created_at
    FROM communication_events 
    WHERE JSON_EXTRACT(metadata, '$.channel_type') = '\"whatsapp\"'
        AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')) IS NOT NULL
    ORDER BY JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')), created_at DESC
");
$stmt4->execute();

$sessions = [];
while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
    $channel = $row['channel_account_id'];
    if (!isset($sessions[$channel])) {
        $sessions[$channel] = $row;
    }
}

foreach ($sessions as $channel => $lastEvent) {
    $payload = json_decode($lastEvent['payload'], true);
    $status = $payload['status'] ?? 'N/A';
    echo "{$channel}\t{$lastEvent['event_type']}\t{$status}\t{$lastEvent['created_at']}\n";
}

echo "\n=== Eventos de erro (últimas 48h) ===\n";

// Buscar eventos de erro
$stmt5 = $pdo->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_account_id')) as channel_account_id,
        event_type,
        status,
        error_message,
        created_at
    FROM communication_events 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        AND JSON_EXTRACT(metadata, '$.channel_type') = '\"whatsapp\"'
        AND (status = 'failed' OR error_message IS NOT NULL)
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt5->execute();

while ($row = $stmt5->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['created_at']}\t{$row['channel_account_id']}\t{$row['event_type']}\t{$row['status']}\t{$row['error_message']}\n";
}
