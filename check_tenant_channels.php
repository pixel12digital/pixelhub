<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICANDO CANAIS DO TENANT ===\n";

// Verificar canais configurados no tenant
$stmt = $db->prepare("
    SELECT id, tenant_id, provider, channel_id, is_enabled, created_at
    FROM tenant_message_channels 
    ORDER BY provider
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($channels) {
    echo "\nCanais configurados no tenant:\n";
    foreach ($channels as $ch) {
        echo sprintf(
            "- ID:%d | Tenant:%d | Channel ID:%s | Provider:%s | Ativo:%s\n",
            $ch['id'],
            $ch['tenant_id'],
            $ch['channel_id'],
            $ch['provider'],
            $ch['is_enabled'] ? 'SIM' : 'NÃO'
        );
    }
} else {
    echo "\n❌ Nenhum canal encontrado no tenant!\n";
}

// Verificar se existe canal para a sessão orsegups
$stmt = $db->prepare("
    SELECT * FROM tenant_message_channels 
    WHERE channel_id LIKE '%orsegups%' OR provider LIKE '%orsegups%'
");
$stmt->execute();
$orsegups = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($orsegups) {
    echo "\nCanais encontrados para 'orsegups':\n";
    foreach ($orsegups as $ch) {
        echo sprintf("- %s (ID:%d)\n", $ch['channel_id'], $ch['id']);
    }
} else {
    echo "\n❌ Nenhum canal configurado para a sessão 'orsegups'!\n";
}

// Verificar tenants disponíveis
$stmt = $db->prepare("SELECT id, name FROM tenants ORDER BY name");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($tenants) {
    echo "\nTenants disponíveis:\n";
    foreach ($tenants as $t) {
        echo sprintf("- ID:%d | Nome:%s\n", $t['id'], $t['name']);
    }
}

echo "\n=== SOLUÇÃO ===\n";
echo "Para adicionar a sessão 'orsegups' ao Inbox:\n";
echo "1. Acesse: /settings/channels\n";
echo "2. Clique em 'Adicionar Canal'\n";
echo "3. Configure:\n";
echo "   - Nome: Orsegups\n";
echo "   - Provider: Whapi\n";
echo "   - Sessão: orsegups\n";
echo "4. Salve\n\n";

echo "Ou execute o SQL direto (substitua TENANT_ID):\n";
echo "INSERT INTO tenant_message_channels \n";
echo "(tenant_id, provider, channel_id, is_enabled, created_at)\n";
echo "VALUES (TENANT_ID, 'whapi', 'orsegups', 1, NOW());\n";

echo "\n=== FIM ===\n";
