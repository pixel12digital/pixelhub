<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Estrutura agenda_manual_items ===\n";
$cols = $db->query('DESCRIBE agenda_manual_items')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== Estrutura scheduled_messages ===\n";
$cols2 = $db->query('DESCRIBE scheduled_messages')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== Verificando agenda_manual_items (18/02/2026) ===\n";
$sql = "SELECT * FROM agenda_manual_items WHERE DATE(scheduled_for) = '2026-02-18' ORDER BY scheduled_for";
$stmt = $db->prepare($sql);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    echo "Nenhum item encontrado.\n";
} else {
    foreach ($items as $item) {
        echo "ID: {$item['id']} | Agendado: {$item['scheduled_for']} | Status: {$item['status']}\n";
        echo "Título: {$item['title']}\n";
        echo "Contato: {$item['contact_name']}\n\n";
    }
}

echo "\n=== scheduled_messages completo ===\n";
$sql2 = "SELECT * FROM scheduled_messages WHERE id = 2";
$stmt2 = $db->prepare($sql2);
$stmt2->execute();
$msg = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($msg) {
    echo "ID: {$msg['id']}\n";
    echo "Agendado: {$msg['scheduled_at']}\n";
    echo "Status: {$msg['status']}\n";
    echo "Recipient: " . ($msg['recipient_phone'] ?? 'N/A') . "\n";
    echo "Conteúdo: " . ($msg['message_content'] ?? 'N/A') . "\n";
    print_r($msg);
}
?>
