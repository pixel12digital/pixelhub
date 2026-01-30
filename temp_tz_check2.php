<?php
$pdo = new PDO('mysql:host=r225us.hmservers.net;dbname=pixel12digital_pixelhub;charset=utf8mb4', 'pixel12digital_pixelhub', 'Los@ngo#081081');

echo "=== COMPARAÇÃO DE TIMESTAMPS ===\n\n";

// Último evento do Charles
$stmt = $pdo->query("
    SELECT id, event_id, created_at, payload 
    FROM communication_events 
    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%554796164699%'
    ORDER BY id DESC 
    LIMIT 1
");
$event = $stmt->fetch(PDO::FETCH_ASSOC);
echo "ÚLTIMO EVENTO DE CHARLES:\n";
echo "  Event ID: {$event['id']}\n";
echo "  created_at: {$event['created_at']}\n";

// Conversa do Charles
$stmt = $pdo->query("
    SELECT id, last_message_at, updated_at, created_at 
    FROM conversations 
    WHERE contact_name LIKE '%Charles%' OR contact_external_id LIKE '%554796164699%'
    ORDER BY id DESC LIMIT 1
");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nCONVERSA DE CHARLES:\n";
echo "  Conversation ID: {$conv['id']}\n";
echo "  last_message_at: {$conv['last_message_at']}\n";
echo "  updated_at: {$conv['updated_at']}\n";
echo "  created_at: {$conv['created_at']}\n";

// NOW do MySQL
$stmt = $pdo->query("SELECT NOW() as now_time");
$now = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\nMYSQL NOW(): {$now['now_time']}\n";

// PHP time
echo "PHP time (default tz): " . date('Y-m-d H:i:s') . " (" . date_default_timezone_get() . ")\n";

// Diferença
$eventTs = strtotime($event['created_at']);
$convTs = strtotime($conv['last_message_at']);
$diff = ($convTs - $eventTs) / 3600;
echo "\nDIFERENÇA (last_message_at - event.created_at): " . round($diff, 2) . " horas\n";

// Se a diferença for ~3h, o problema é conversão de timezone
if (abs($diff - 3) < 0.5 || abs($diff + 3) < 0.5) {
    echo ">>> PROVÁVEL PROBLEMA DE TIMEZONE! <<<\n";
}
