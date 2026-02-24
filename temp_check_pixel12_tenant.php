<?php
/**
 * Verificar se existe conta Pixel12 Digital
 */

// Carregar .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Conectar ao banco
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'pixel_hub';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== VERIFICAÇÃO: CONTA PIXEL12 DIGITAL ===\n\n";

// Buscar conta exata "Pixel12 Digital"
$stmt = $db->prepare("
    SELECT id, name, status, contact_type
    FROM tenants
    WHERE name = 'Pixel12 Digital'
");
$stmt->execute();
$exact = $stmt->fetch(PDO::FETCH_ASSOC);

if ($exact) {
    echo "Conta 'Pixel12 Digital' ENCONTRADA:\n";
    echo "  ID: {$exact['id']}\n";
    echo "  Nome: {$exact['name']}\n";
    echo "  Status: {$exact['status']}\n";
    echo "  Contact Type: {$exact['contact_type']}\n";
} else {
    echo "Conta 'Pixel12 Digital' NÃO ENCONTRADA.\n";
}

// Buscar variações
echo "\n\nBuscando variações de 'Pixel':\n";
$stmt = $db->prepare("
    SELECT id, name, status, contact_type
    FROM tenants
    WHERE name LIKE '%Pixel%'
    ORDER BY name
");
$stmt->execute();
$variations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($variations)) {
    echo "Nenhuma variação encontrada.\n";
} else {
    foreach ($variations as $v) {
        echo "  - ID {$v['id']}: '{$v['name']}' (status: {$v['status']}, type: {$v['contact_type']})\n";
    }
}

// Verificar se existe algum tenant com contact_type diferente de 'client'
echo "\n\nTenants com contact_type diferente de 'client':\n";
$stmt = $db->prepare("
    SELECT id, name, status, contact_type
    FROM tenants
    WHERE contact_type != 'client'
    ORDER BY name
    LIMIT 20
");
$stmt->execute();
$nonClients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($nonClients)) {
    echo "Todos os tenants têm contact_type = 'client'.\n";
} else {
    foreach ($nonClients as $nc) {
        echo "  - ID {$nc['id']}: '{$nc['name']}' (status: {$nc['status']}, type: {$nc['contact_type']})\n";
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";
