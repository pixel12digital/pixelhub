<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Estrutura opportunities ===\n";
$cols = $db->query('DESCRIBE opportunities')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== Opportunity ID 7 ===\n";
$sql = "SELECT * FROM opportunities WHERE id = 7";
$stmt = $db->prepare($sql);
$stmt->execute();
$opp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($opp) {
    echo "ID: {$opp['id']}\n";
    echo "Name: {$opp['name']}\n";
    echo "Contact Name: {$opp['contact_name']}\n";
    echo "Contact Phone: {$opp['contact_phone']}\n";
    echo "Contact Email: {$opp['contact_email']}\n";
    echo "Status: {$opp['status']}\n";
    echo "Tenant ID: {$opp['tenant_id']}\n";
    echo "Value: {$opp['value']}\n";
    echo "Created: {$opp['created_at']}\n";
} else {
    echo "Opportunity ID 7 não encontrada.\n";
}

echo "\n=== scheduled_message ID 2 (completo) ===\n";
$sql2 = "SELECT * FROM scheduled_messages WHERE id = 2";
$stmt2 = $db->prepare($sql2);
$stmt2->execute();
$msg = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($msg) {
    echo "ID: {$msg['id']}\n";
    echo "Agendado: {$msg['scheduled_at']}\n";
    echo "Status: {$msg['status']}\n";
    echo "Agenda Item ID: {$msg['agenda_item_id']}\n";
    echo "Opportunity ID: {$msg['opportunity_id']}\n";
    echo "Lead ID: {$msg['lead_id']}\n";
    echo "Tenant ID: {$msg['tenant_id']}\n";
    echo "Conversation ID: {$msg['conversation_id']}\n";
    echo "Mensagem: {$msg['message_text']}\n";
    echo "Enviado em: " . ($msg['sent_at'] ?? 'N/A') . "\n";
    echo "Falha: " . ($msg['failed_reason'] ?? 'N/A') . "\n";
    echo "Criado em: {$msg['created_at']}\n";
    echo "Updated em: {$msg['updated_at']}\n";
}

echo "\n=== Verificando workers ===\n";
$workers = [
    'scripts/scheduled_messages_worker.php',
    'scripts/process_scheduled_messages.php',
    'scripts/send_scheduled_messages.php'
];

foreach ($workers as $worker) {
    if (file_exists(__DIR__ . '/' . $worker)) {
        echo "✓ Encontrado: $worker\n";
    } else {
        echo "✗ Não encontrado: $worker\n";
    }
}

echo "\n=== Verificando logs ===\n";
$logDir = __DIR__ . '/logs';
if (is_dir($logDir)) {
    $files = scandir($logDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "Log: $file\n";
        }
    }
} else {
    echo "Diretório logs não encontrado.\n";
}
?>
