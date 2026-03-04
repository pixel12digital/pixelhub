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

echo "=== ESTRUTURA DA TABELA INVOICES ===\n";
$stmt = $pdo->query("DESCRIBE invoices");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== FATURAS DO CLIENTE 14 ===\n";
$stmt = $pdo->query("SELECT * FROM invoices WHERE tenant_id = 14 LIMIT 3");
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($invoices)) {
    print_r($invoices[0]);
} else {
    echo "Nenhuma fatura encontrada\n";
}
