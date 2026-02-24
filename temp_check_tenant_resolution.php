<?php
// Verificar resolução de tenant para os dois casos
$envFile = __DIR__ . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$host = $envVars['DB_HOST'] ?? 'localhost';
$dbname = $envVars['DB_NAME'] ?? '';
$username = $envVars['DB_USER'] ?? '';
$password = $envVars['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== ANÁLISE DE RESOLUÇÃO DE TENANT ===\n\n";

// 1. Buscar todos os tenants com telefone
echo "--- TENANTS COM TELEFONE CADASTRADO ---\n";
$stmt = $db->query("
    SELECT id, name, phone, email 
    FROM tenants 
    WHERE phone IS NOT NULL AND phone != '' 
    AND (is_archived IS NULL OR is_archived = 0)
    ORDER BY id
");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($tenants) . " tenants\n\n";
foreach ($tenants as $tenant) {
    $phoneDigits = preg_replace('/[^0-9]/', '', $tenant['phone']);
    echo sprintf(
        "ID: %3d | Nome: %-40s | Phone: %-20s | Digits: %s\n",
        $tenant['id'],
        substr($tenant['name'], 0, 40),
        $tenant['phone'],
        $phoneDigits
    );
}

// 2. Analisar os números específicos
echo "\n--- ANÁLISE DOS NÚMEROS ESPECÍFICOS ---\n\n";

$cases = [
    [
        'name' => 'DOUGLAS',
        'from' => '47953460858953@lid',
        'expected_tenant' => '?'
    ],
    [
        'name' => 'JOÃO MARQUES',
        'from' => '554196206584@c.us',
        'expected_tenant' => '29'
    ]
];

foreach ($cases as $case) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "CASO: {$case['name']}\n";
    echo "From: {$case['from']}\n";
    echo "Tenant esperado: {$case['expected_tenant']}\n\n";
    
    // Simula a lógica de resolveTenantByPhone
    $from = $case['from'];
    $cleaned = preg_replace('/@.*$/', '', $from);
    $contactDigits = preg_replace('/[^0-9]/', '', $cleaned);
    
    echo "Após limpeza: $cleaned\n";
    echo "Apenas dígitos: $contactDigits\n";
    echo "Comprimento: " . strlen($contactDigits) . " dígitos\n";
    
    // Garante prefixo 55 para números BR
    if (substr($contactDigits, 0, 2) !== '55' && (strlen($contactDigits) === 10 || strlen($contactDigits) === 11)) {
        $contactDigits = '55' . $contactDigits;
        echo "Após adicionar prefixo 55: $contactDigits\n";
    }
    
    // Busca matches
    $matches = [];
    foreach ($tenants as $tenant) {
        $tenantPhone = preg_replace('/[^0-9]/', '', $tenant['phone']);
        
        if (empty($tenantPhone)) continue;
        
        // Garante prefixo 55
        if (substr($tenantPhone, 0, 2) !== '55' && (strlen($tenantPhone) === 10 || strlen($tenantPhone) === 11)) {
            $tenantPhone = '55' . $tenantPhone;
        }
        
        // Comparação exata
        if ($contactDigits === $tenantPhone) {
            $matches[] = [
                'tenant' => $tenant,
                'match_type' => 'EXATO'
            ];
        }
    }
    
    echo "\nMatches encontrados: " . count($matches) . "\n";
    if (count($matches) > 0) {
        foreach ($matches as $match) {
            echo sprintf(
                "  ✓ Tenant ID: %d | Nome: %s | Tipo: %s\n",
                $match['tenant']['id'],
                $match['tenant']['name'],
                $match['match_type']
            );
        }
    } else {
        echo "  ✗ Nenhum match encontrado\n";
        echo "\nPOSSÍVEL CAUSA:\n";
        echo "  - Número não cadastrado em nenhum tenant\n";
        echo "  - Formato do número incompatível (ex: código de país diferente)\n";
        echo "  - Número é de um contato, não do tenant\n";
    }
    echo "\n";
}

// 3. Verificar se existe tenant com telefone 31 8231-3765 (Douglas)
echo "\n--- BUSCAR TENANT COM TELEFONE DO DOUGLAS ---\n";
$douglasPhone = '3182313765';
$stmt = $db->prepare("
    SELECT id, name, phone 
    FROM tenants 
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?
    AND (is_archived IS NULL OR is_archived = 0)
");
$stmt->execute(["%$douglasPhone%"]);
$douglasTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($douglasTenants) > 0) {
    echo "Tenants encontrados com telefone similar:\n";
    foreach ($douglasTenants as $t) {
        echo "  - ID: {$t['id']} | Nome: {$t['name']} | Phone: {$t['phone']}\n";
    }
} else {
    echo "Nenhum tenant encontrado com telefone (31) 8231-3765\n";
}

// 4. Verificar se existe tenant com telefone 41 9620-6584 (João)
echo "\n--- BUSCAR TENANT COM TELEFONE DO JOÃO MARQUES ---\n";
$joaoPhone = '4196206584';
$stmt = $db->prepare("
    SELECT id, name, phone 
    FROM tenants 
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?
    AND (is_archived IS NULL OR is_archived = 0)
");
$stmt->execute(["%$joaoPhone%"]);
$joaoTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($joaoTenants) > 0) {
    echo "Tenants encontrados com telefone similar:\n";
    foreach ($joaoTenants as $t) {
        echo "  - ID: {$t['id']} | Nome: {$t['name']} | Phone: {$t['phone']}\n";
    }
} else {
    echo "Nenhum tenant encontrado com telefone (41) 9620-6584\n";
}

echo "\n=== DIAGNÓSTICO FINAL ===\n\n";
echo "O sistema resolve tenant_id através do telefone do REMETENTE (from).\n";
echo "Isso significa que:\n";
echo "  1. Douglas (31 8231-3765) → Precisa ter um tenant com este telefone cadastrado\n";
echo "  2. João Marques (41 9620-6584) → Precisa ter um tenant com este telefone cadastrado\n\n";
echo "IMPORTANTE: O 'from' é o número de QUEM ENVIOU a mensagem, não o canal.\n";
echo "Se Douglas enviou mensagem PARA Pixel12Digital, o 'from' é o número do Douglas.\n";
echo "Para que a conversa seja vinculada, é necessário cadastrar o Douglas como tenant.\n\n";

echo "=== FIM DA ANÁLISE ===\n";
