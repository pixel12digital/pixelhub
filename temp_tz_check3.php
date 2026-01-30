<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== COMPARAÇÃO DE TIMESTAMPS ===\n\n";

// Últimos eventos (qualquer tipo)
$stmt = $pdo->query("
    SELECT id, event_type, created_at 
    FROM communication_events 
    ORDER BY id DESC 
    LIMIT 5
");
echo "ÚLTIMOS 5 EVENTOS (communication_events.created_at):\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID {$row['id']}: {$row['created_at']} - {$row['event_type']}\n";
}

// Últimas conversas
$stmt = $pdo->query("
    SELECT id, contact_name, last_message_at, updated_at 
    FROM conversations 
    ORDER BY last_message_at DESC 
    LIMIT 5
");
echo "\nÚLTIMAS 5 CONVERSAS (conversations.last_message_at):\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "  ID {$row['id']}: last_message_at={$row['last_message_at']}, updated_at={$row['updated_at']} - {$row['contact_name']}\n";
}

// NOW do MySQL
$stmt = $pdo->query("SELECT NOW() as now_time");
$now = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nMYSQL NOW(): {$now['now_time']}\n";

echo "\n=== CONCLUSÃO ===\n";
echo "Se communication_events.created_at e conversations.last_message_at\n";
echo "têm diferença de ~3h, há inconsistência de timezone entre as tabelas.\n";
