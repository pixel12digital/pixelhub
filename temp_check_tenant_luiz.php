<?php
$host = 'r225us.hmservers.net';
$dbname = 'pixel12digital_pixelhub';
$user = 'pixel12digital_pixelhub';
$pass = 'Los@ngo#081081';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== VERIFICANDO TENANT DO LUIZ ===\n\n";

// 1. Buscar lead/contato com o telefone do Luiz
echo "1. BUSCANDO LEAD COM TELEFONE +55 16 98140-4507:\n";
$variations = [
    '5516981404507',
    '16981404507',
    '98140-4507',
    '981404507',
    '(16) 98140-4507'
];

foreach ($variations as $var) {
    $stmt = $pdo->prepare("
        SELECT id, nome, telefone, tenant_id
        FROM leads
        WHERE telefone LIKE ?
        LIMIT 5
    ");
    $stmt->execute(["%{$var}%"]);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($leads)) {
        echo "   ✓ Encontrado com variação '{$var}':\n";
        foreach ($leads as $lead) {
            echo "      Lead ID: {$lead['id']}\n";
            echo "      Nome: {$lead['nome']}\n";
            echo "      Telefone: {$lead['telefone']}\n";
            echo "      Tenant ID: {$lead['tenant_id']}\n";
            
            // Buscar nome do tenant
            $stmt2 = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
            $stmt2->execute([$lead['tenant_id']]);
            $tenant = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                echo "      Tenant: {$tenant['name']}\n";
            }
            echo "\n";
        }
    }
}

// 2. Listar todos os tenants
echo "\n2. TODOS OS TENANTS:\n";
$stmt = $pdo->query("
    SELECT id, name, is_active
    FROM tenants
    ORDER BY is_active DESC, id
");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($tenants as $t) {
    $status = $t['is_active'] ? '✓ ATIVO' : '✗ INATIVO';
    echo "   [{$t['id']}] {$status} | {$t['name']}\n";
}

// 3. Verificar se existe algum canal configurado (qualquer provider)
echo "\n3. CANAIS CONFIGURADOS (TODOS OS PROVIDERS):\n";
$stmt = $pdo->query("
    SELECT c.*, t.name as tenant_name
    FROM tenant_message_channels c
    LEFT JOIN tenants t ON t.id = c.tenant_id
    ORDER BY c.is_enabled DESC, c.id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($channels)) {
    echo "   ❌ NENHUM canal configurado em nenhum tenant!\n";
} else {
    foreach ($channels as $ch) {
        $status = $ch['is_enabled'] ? '✓ ATIVO' : '✗ INATIVO';
        echo "   [{$ch['id']}] {$status} | Tenant: {$ch['tenant_name']} (ID: {$ch['tenant_id']})\n";
        echo "      Provider: {$ch['provider']} ({$ch['provider_type']})\n";
        echo "      Channel ID: {$ch['channel_id']}\n";
        echo "\n";
    }
}

// 4. Verificar últimas conversas ativas
echo "\n4. ÚLTIMAS CONVERSAS ATIVAS:\n";
$stmt = $pdo->query("
    SELECT id, conversation_key, contact_external_id, 
           last_message_at, status,
           SUBSTRING(contact_external_id, 1, 20) as contact_preview
    FROM conversations
    WHERE status = 'active'
    ORDER BY last_message_at DESC
    LIMIT 10
");
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($convs as $c) {
    echo "   Conv [{$c['id']}] | {$c['contact_preview']}... | Última: {$c['last_message_at']}\n";
}

echo "\n=== FIM ===\n";
