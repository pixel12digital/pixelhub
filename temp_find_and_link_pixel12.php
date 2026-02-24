<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== BUSCANDO TENANT PIXEL12 DIGITAL ===\n\n";

// Busca tenant Pixel12
$stmt = $db->prepare("
    SELECT id, name, phone, cpf_cnpj 
    FROM tenants 
    WHERE name LIKE '%pixel%' 
    OR name LIKE '%pixel12%'
    ORDER BY id
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($tenants) === 0) {
    echo "✗ Nenhum tenant encontrado com 'pixel' no nome.\n";
    echo "Listando primeiros 10 tenants para referência:\n\n";
    
    $stmt = $db->query("SELECT id, name FROM tenants ORDER BY id LIMIT 10");
    $first10 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($first10 as $t) {
        echo "  ID: {$t['id']} - {$t['name']}\n";
    }
    exit(1);
}

echo "Tenants encontrados:\n";
foreach ($tenants as $t) {
    echo "  ID: {$t['id']} - {$t['name']}\n";
    echo "    CNPJ/CPF: " . ($t['cpf_cnpj'] ?: 'NULL') . "\n";
    echo "    Telefone: " . ($t['phone'] ?: 'NULL') . "\n\n";
}

// Se encontrou apenas 1, usa automaticamente
if (count($tenants) === 1) {
    $tenantId = $tenants[0]['id'];
    $tenantName = $tenants[0]['name'];
    echo "✓ Usando tenant ID {$tenantId} - {$tenantName}\n\n";
} else {
    echo "Múltiplos tenants encontrados. Usando o primeiro: ID {$tenants[0]['id']}\n\n";
    $tenantId = $tenants[0]['id'];
    $tenantName = $tenants[0]['name'];
}

echo "=== ATUALIZANDO CONVERSA E OPORTUNIDADE ===\n\n";

// Atualiza conversa 459
echo "Atualizando conversa 459...\n";
$stmt = $db->prepare("UPDATE conversations SET tenant_id = ? WHERE id = 459");
$result = $stmt->execute([$tenantId]);

if ($result) {
    echo "✓ Conversa 459 vinculada ao tenant {$tenantId} ({$tenantName})\n";
} else {
    echo "✗ Erro ao atualizar conversa\n";
}

// Atualiza oportunidade 8
echo "Atualizando oportunidade 8...\n";
$stmt = $db->prepare("UPDATE opportunities SET tenant_id = ? WHERE id = 8");
$result = $stmt->execute([$tenantId]);

if ($result) {
    echo "✓ Oportunidade 8 vinculada ao tenant {$tenantId} ({$tenantName})\n";
} else {
    echo "✗ Erro ao atualizar oportunidade\n";
}

echo "\n=== VERIFICAÇÃO FINAL ===\n\n";

// Verifica conversa
$stmt = $db->prepare("
    SELECT c.id, c.contact_name, c.tenant_id, t.name as tenant_name, c.lead_id, l.name as lead_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.id = 459
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Conversa 459:\n";
echo "  Contato: {$conv['contact_name']}\n";
echo "  Lead: {$conv['lead_name']} (ID: {$conv['lead_id']})\n";
echo "  Tenant: {$conv['tenant_name']} (ID: {$conv['tenant_id']})\n\n";

// Verifica oportunidade
$stmt = $db->prepare("
    SELECT o.id, o.name, o.tenant_id, t.name as tenant_name, o.lead_id, l.name as lead_name
    FROM opportunities o
    LEFT JOIN tenants t ON o.tenant_id = t.id
    LEFT JOIN leads l ON o.lead_id = l.id
    WHERE o.id = 8
");
$stmt->execute();
$opp = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Oportunidade 8:\n";
echo "  Nome: {$opp['name']}\n";
echo "  Lead: {$opp['lead_name']} (ID: {$opp['lead_id']})\n";
echo "  Tenant: {$opp['tenant_name']} (ID: {$opp['tenant_id']})\n\n";

echo "✓ CORREÇÃO CONCLUÍDA!\n";
echo "Recarregue o Inbox para ver a conversa do Luiz Carlos vinculada corretamente.\n";
