<?php
// Verificar estrutura das tabelas de cobrança
$envFile = __DIR__ . '/../.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$pdo = new PDO(
    "mysql:host={$envVars['DB_HOST']};dbname={$envVars['DB_NAME']};charset=utf8mb4",
    $envVars['DB_USER'],
    $envVars['DB_PASS'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "=== ESTRUTURA DA TABELA TENANTS ===\n";
$stmt = $pdo->query("DESCRIBE tenants");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (strpos($row['Field'], 'billing') !== false) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

echo "\n=== CLIENTES COM BILLING_AUTO_SEND = 1 ===\n";
$stmt = $pdo->query("SELECT id, nome_fantasia, billing_auto_send FROM tenants WHERE billing_auto_send = 1");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID {$row['id']}: {$row['nome_fantasia']}\n";
}
