<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Verificando status atual ===\n";

// 1. Verificar scheduled_messages
$stmt = $db->prepare('SELECT id, status, sent_at FROM scheduled_messages WHERE id = 2');
$stmt->execute();
$msg = $stmt->fetch();
echo "Scheduled Message ID 2: " . $msg['status'] . " - " . $msg['sent_at'] . "\n";

// 2. Verificar agenda_manual_items
$stmt2 = $db->prepare('SELECT id, status, updated_at FROM agenda_manual_items WHERE id = 2');
$stmt2->execute();
$task = $stmt2->fetch();
echo "Agenda Item ID 2: " . $task['status'] . " - " . $task['updated_at'] . "\n";

// 3. Verificar communication_events
$stmt3 = $db->prepare('SELECT COUNT(*) as total FROM communication_events WHERE event_type = "followup_completed" AND JSON_EXTRACT(event_data, "$.scheduled_message_id") = 2');
$stmt3->execute();
$events = $stmt3->fetch();
echo "Events followup_completed: " . $events['total'] . "\n";

// 4. Verificar se há algum erro nos logs
echo "\n=== Verificando se implementação foi chamada ===\n";
$stmt4 = $db->prepare('SELECT COUNT(*) as total FROM communication_events WHERE source_system = "scheduled_messages_worker"');
$stmt4->execute();
$workerEvents = $stmt4->fetch();
echo "Total eventos do worker: " . $workerEvents['total'] . "\n";

// 5. Verificar estrutura de agenda_manual_items
echo "\n=== Estrutura agenda_manual_items ===\n";
$cols = $db->query('DESCRIBE agenda_manual_items')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    if (strpos($col['Field'], 'status') !== false) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
}
?>
