<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

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
