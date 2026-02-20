<?php
require_once 'config/database.php';
$pdo = getPDO();

// Verificar estrutura da tabela communication_events
$sql = 'DESCRIBE communication_events';
$stmt = $pdo->query($sql);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ESTRUTURA DA TABELA COMMUNICATION_EVENTS ===\n";
foreach ($columns as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}

echo "\n=== ESTRUTURA DA TABELA CONVERSATIONS ===\n";
$sql2 = 'DESCRIBE conversations';
$stmt2 = $pdo->query($sql2);
$columns2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns2 as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}
