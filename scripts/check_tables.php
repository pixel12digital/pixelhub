<?php
$envFile = __DIR__ . '/../.env';
$envVars = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
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

echo "=== TABELAS DE COBRANÇA ===\n";
$stmt = $pdo->query("SHOW TABLES LIKE 'billing%'");
$tables = $stmt->fetchAll(PDO::FETCH_NUM);
foreach ($tables as $table) {
    echo $table[0] . "\n";
}

echo "\n=== ESTRUTURA billing_dispatch_rules (se existir) ===\n";
try {
    $stmt = $pdo->query("DESCRIBE billing_dispatch_rules");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Tabela não existe: " . $e->getMessage() . "\n";
}

echo "\n=== ESTRUTURA billing_dispatch_queue (se existir) ===\n";
try {
    $stmt = $pdo->query("DESCRIBE billing_dispatch_queue");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Tabela não existe: " . $e->getMessage() . "\n";
}

echo "\n=== ESTRUTURA billing_notifications ===\n";
try {
    $stmt = $pdo->query("DESCRIBE billing_notifications");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} catch (Exception $e) {
    echo "Tabela não existe: " . $e->getMessage() . "\n";
}
