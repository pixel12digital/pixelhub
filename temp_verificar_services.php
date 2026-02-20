<?php
// Verificar estrutura da tabela services
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

// Carrega .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, '"\'');
        
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

$db = \PixelHub\Core\DB::getConnection();

echo "=== ESTRUTURA DA TABELA SERVICES ===\n";
$stmt = $db->query("DESCRIBE services");
$columns = $stmt->fetchAll();

foreach ($columns as $col) {
    echo "- {$col['Field']}: {$col['Type']}\n";
}

echo "\n=== AMOSTRA DE DADOS ===\n";
$stmt = $db->query("SELECT * FROM services LIMIT 3");
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo "ID: {$row['id']}, Nome: " . ($row['name'] ?? 'NULL') . "\n";
}
