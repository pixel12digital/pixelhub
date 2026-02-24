<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VINCULANDO LUIZ CARLOS A UM TENANT ===\n\n";

// IMPORTANTE: Defina aqui o ID do tenant correto
// Baseado na imagem, parece ser a Pixel12 Digital (você mencionou "Haiti igual a 8")
// Vou buscar tenants que podem ser a Pixel12 Digital

echo "Buscando tenant 'Pixel12 Digital' ou similar...\n\n";

$stmt = $db->prepare("
    SELECT id, name, phone 
    FROM tenants 
    WHERE name LIKE '%pixel%' OR name LIKE '%haiti%'
    ORDER BY id
");
$stmt->execute();
$possibleTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($possibleTenants) > 0) {
    echo "Tenants encontrados:\n";
    foreach ($possibleTenants as $t) {
        echo "  ID: {$t['id']} - {$t['name']} - Tel: " . ($t['phone'] ?: 'NULL') . "\n";
    }
    echo "\n";
}

// Você mencionou "Haiti igual a 8" - vou verificar se existe tenant ID 8
$stmt = $db->prepare("SELECT id, name, phone FROM tenants WHERE id = 8");
$stmt->execute();
$tenant8 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant8) {
    echo "Tenant ID 8:\n";
    echo "  Nome: {$tenant8['name']}\n";
    echo "  Telefone: " . ($tenant8['phone'] ?: 'NULL') . "\n\n";
}

echo "=== ESCOLHA O TENANT CORRETO ===\n\n";
echo "Edite este script e defina \$tenantId = [ID_CORRETO] na linha abaixo:\n\n";

// DEFINA AQUI O TENANT_ID CORRETO
$tenantId = null; // <<<< ALTERE AQUI para o ID do tenant correto

if ($tenantId === null) {
    echo "⚠️ ATENÇÃO: Defina o \$tenantId antes de executar!\n";
    echo "\nExemplo: \$tenantId = 8; (se for o tenant ID 8)\n";
    exit(1);
}

// Verifica se o tenant existe
$stmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
$stmt->execute([$tenantId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    echo "✗ Tenant ID {$tenantId} não encontrado!\n";
    exit(1);
}

echo "✓ Tenant selecionado: ID {$tenant['id']} - {$tenant['name']}\n\n";

// Atualiza conversa
echo "Atualizando conversa 459...\n";
$stmt = $db->prepare("UPDATE conversations SET tenant_id = ? WHERE id = 459");
$result = $stmt->execute([$tenantId]);

if ($result) {
    echo "✓ Conversa 459 atualizada com tenant_id = {$tenantId}\n";
} else {
    echo "✗ Erro ao atualizar conversa\n";
}

// Atualiza oportunidade
echo "Atualizando oportunidade 8...\n";
$stmt = $db->prepare("UPDATE opportunities SET tenant_id = ? WHERE id = 8");
$result = $stmt->execute([$tenantId]);

if ($result) {
    echo "✓ Oportunidade 8 atualizada com tenant_id = {$tenantId}\n";
} else {
    echo "✗ Erro ao atualizar oportunidade\n";
}

echo "\n✓ CORREÇÃO CONCLUÍDA!\n";
echo "Recarregue o Inbox para ver a conversa vinculada corretamente.\n";
