<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== BUSCANDO TENANT CORRETO PARA LUIZ CARLOS ===\n\n";

// Lista todos os tenants
echo "TENANTS DISPONÍVEIS:\n";
$stmt = $db->query("SELECT id, name, phone FROM tenants ORDER BY id");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenants as $tenant) {
    echo "  ID: {$tenant['id']} - {$tenant['name']} - Telefone: " . ($tenant['phone'] ?: 'NULL') . "\n";
}

echo "\n";

// Verifica se há alguma conversa ou oportunidade do Luiz Carlos com tenant_id definido
echo "VERIFICANDO OUTRAS CONVERSAS/OPORTUNIDADES DO LUIZ CARLOS:\n\n";

// Busca outras conversas do mesmo telefone
$stmt = $db->prepare("
    SELECT id, contact_external_id, tenant_id, channel_id, created_at
    FROM conversations
    WHERE contact_external_id LIKE '%99235045%'
    OR contact_external_id LIKE '%9923-5045%'
    ORDER BY id DESC
");
$stmt->execute();
$otherConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($otherConvs) > 0) {
    echo "Conversas encontradas:\n";
    foreach ($otherConvs as $conv) {
        echo "  Conv ID: {$conv['id']} | Tenant: " . ($conv['tenant_id'] ?: 'NULL') . " | Channel: {$conv['channel_id']} | {$conv['created_at']}\n";
    }
} else {
    echo "Nenhuma outra conversa encontrada.\n";
}

echo "\n";

// Busca oportunidades do Lead 10
echo "OPORTUNIDADES DO LEAD 10:\n";
$stmt = $db->prepare("
    SELECT id, name, tenant_id, status, created_at
    FROM opportunities
    WHERE lead_id = 10
    ORDER BY id DESC
");
$stmt->execute();
$opps = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($opps) > 0) {
    foreach ($opps as $opp) {
        echo "  Opp ID: {$opp['id']} - {$opp['name']} | Tenant: " . ($opp['tenant_id'] ?: 'NULL') . " | Status: {$opp['status']} | {$opp['created_at']}\n";
    }
} else {
    echo "Nenhuma oportunidade encontrada.\n";
}

echo "\n=== RECOMENDAÇÃO ===\n\n";
echo "Como não há tenant_id definido em nenhum lugar, você precisa escolher:\n\n";
echo "1. Qual tenant é responsável pelo Luiz Carlos?\n";
echo "2. Depois, execute:\n";
echo "   UPDATE conversations SET tenant_id = [ID_DO_TENANT] WHERE id = 459;\n";
echo "   UPDATE opportunities SET tenant_id = [ID_DO_TENANT] WHERE id = 8;\n";
