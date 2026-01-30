<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== EVENTOS DO CHARLES (últimos 5) ===\n\n";

$stmt = $pdo->query("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.t')) as payload_timestamp,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.timestamp')) as payload_timestamp2
    FROM communication_events ce
    WHERE ce.event_type LIKE '%message%'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.from')) LIKE '%554796164699%'
    )
    ORDER BY ce.id DESC
    LIMIT 5
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']}\n";
    echo "  event_type: {$row['event_type']}\n";
    echo "  created_at: {$row['created_at']}\n";
    echo "  payload.raw.payload.t: {$row['payload_timestamp']}\n";
    echo "  payload.timestamp: {$row['payload_timestamp2']}\n";
    
    // Converte timestamp Unix se existir
    if ($row['payload_timestamp'] && is_numeric($row['payload_timestamp'])) {
        $ts = (int)$row['payload_timestamp'];
        if ($ts > 10000000000) $ts = $ts / 1000;
        echo "  payload.t converted (UTC): " . gmdate('Y-m-d H:i:s', $ts) . "\n";
        echo "  payload.t converted (Brasilia): " . date('Y-m-d H:i:s', $ts - 3*3600) . " (UTC-3)\n";
    }
    echo "\n";
}

// Conversa
echo "=== CONVERSA DO CHARLES ===\n";
$stmt = $pdo->query("
    SELECT id, last_message_at, updated_at 
    FROM conversations 
    WHERE contact_name LIKE '%Charles%'
    LIMIT 1
");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  last_message_at: {$conv['last_message_at']}\n";
echo "  updated_at: {$conv['updated_at']}\n";

echo "\nMYSQL NOW(): ";
$stmt = $pdo->query("SELECT NOW() as n");
echo $stmt->fetch(PDO::FETCH_ASSOC)['n'] . "\n";
