<?php
/**
 * Investigação: Conta Pixel12 Digital e vinculação
 */

// Carregar .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
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

echo "=== INVESTIGAÇÃO: CONTA PIXEL12 DIGITAL ===\n\n";

// 1. Buscar conta Pixel12 Digital
echo "1. Buscando conta 'Pixel12 Digital':\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT 
        id,
        name,
        phone,
        email,
        status,
        created_at,
        updated_at
    FROM tenants
    WHERE name LIKE '%Pixel%' OR name LIKE '%pixel%'
    ORDER BY created_at DESC
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "Nenhuma conta Pixel encontrada.\n";
} else {
    foreach ($tenants as $tenant) {
        echo "Tenant ID: {$tenant['id']}\n";
        echo "  Nome: {$tenant['name']}\n";
        echo "  Telefone: {$tenant['phone']}\n";
        echo "  Email: {$tenant['email']}\n";
        echo "  Status: {$tenant['status']}\n";
        echo "  Criado em: {$tenant['created_at']}\n";
        echo "  Atualizado em: {$tenant['updated_at']}\n";
        echo "\n";
    }
}

// 2. Testar a query de busca do autocomplete (simulando busca por "pixel")
echo "\n2. Testando query de autocomplete com termo 'pixel':\n";
echo str_repeat('-', 80) . "\n";

$query = 'pixel';
$searchTerm = '%' . $query . '%';
$searchDigits = preg_replace('/[^0-9]/', '', $query);

if (!empty($searchDigits)) {
    $stmt = $db->prepare("
        SELECT id, name, phone, email
        FROM tenants
        WHERE status = 'active'
        AND (name LIKE ? OR email LIKE ? OR REPLACE(REPLACE(REPLACE(REPLACE(phone, '(', ''), ')', ''), '-', ''), ' ', '') LIKE ?)
        ORDER BY name ASC
        LIMIT 20
    ");
    $stmt->execute([$searchTerm, $searchTerm, '%' . $searchDigits . '%']);
} else {
    $stmt = $db->prepare("
        SELECT id, name, phone, email
        FROM tenants
        WHERE status = 'active'
        AND (name LIKE ? OR email LIKE ?)
        ORDER BY name ASC
        LIMIT 20
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
}

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Resultados da busca por 'pixel':\n";
if (empty($results)) {
    echo "  Nenhum resultado encontrado.\n";
} else {
    foreach ($results as $r) {
        echo "  - ID {$r['id']}: {$r['name']} ({$r['phone']}) - {$r['email']}\n";
    }
}

// 3. Verificar todas as contas ativas
echo "\n\n3. Todas as contas ATIVAS no sistema:\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT id, name, phone, email, status
    FROM tenants
    WHERE status = 'active'
    ORDER BY name ASC
");
$allActive = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de contas ativas: " . count($allActive) . "\n\n";
foreach ($allActive as $t) {
    echo "  - ID {$t['id']}: {$t['name']} (status: {$t['status']})\n";
}

// 4. Verificar se existe alguma conta com status diferente de 'active'
echo "\n\n4. Contas com status DIFERENTE de 'active':\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("
    SELECT id, name, phone, email, status
    FROM tenants
    WHERE status != 'active'
    ORDER BY name ASC
");
$inactive = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($inactive)) {
    echo "Nenhuma conta inativa encontrada.\n";
} else {
    echo "Total de contas inativas: " . count($inactive) . "\n\n";
    foreach ($inactive as $t) {
        echo "  - ID {$t['id']}: {$t['name']} (status: {$t['status']})\n";
    }
}

// 5. Verificar estrutura da tabela tenants
echo "\n\n5. Estrutura da tabela 'tenants':\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->query("DESCRIBE tenants");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $col) {
    echo "  - {$col['Field']}: {$col['Type']} (Null: {$col['Null']}, Default: {$col['Default']})\n";
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
