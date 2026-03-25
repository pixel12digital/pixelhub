<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== ADICIONANDO CANAL ORSEGUPS AO TENANT ===\n";

// Buscar o primeiro tenant disponível
$stmt = $db->prepare("SELECT id, name FROM tenants ORDER BY id LIMIT 1");
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    echo "❌ Nenhum tenant encontrado!\n";
    exit;
}

echo "Usando tenant: {$tenant['name']} (ID: {$tenant['id']})\n\n";

// Adicionar canal orsegups para o tenant encontrado
$stmt = $db->prepare("
    INSERT INTO tenant_message_channels 
    (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
    VALUES (?, 'whapi', 'orsegups', 1, NOW(), NOW())
");
$stmt->execute([$tenant['id']]);

$affected = $stmt->rowCount();
echo "✅ Canal adicionado! Linhas afetadas: {$affected}\n";

// Verificar se foi adicionado
$stmt = $db->prepare("
    SELECT id, tenant_id, provider, channel_id, is_enabled 
    FROM tenant_message_channels 
    WHERE channel_id = 'orsegups'
");
$stmt->execute();
$channel = $stmt->fetch(PDO::FETCH_ASSOC);

if ($channel) {
    echo "\nCanal adicionado com sucesso:\n";
    echo sprintf("- ID: %d\n", $channel['id']);
    echo sprintf("- Tenant: %d\n", $channel['tenant_id']);
    echo sprintf("- Provider: %s\n", $channel['provider']);
    echo sprintf("- Channel ID: %s\n", $channel['channel_id']);
    echo sprintf("- Ativo: %s\n", $channel['is_enabled'] ? 'SIM' : 'NÃO');
    
    echo "\n✅ Agora o canal 'orsegups' deve aparecer no Inbox!\n";
    echo "   Recarregue a página do Inbox para ver as sessões disponíveis.\n";
} else {
    echo "\n❌ Erro ao adicionar canal!\n";
}

echo "\n=== FIM ===\n";
