<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== CORRIGINDO DEFINIÇÃO DE CANAIS ===\n";

// 1. Remover canal imobsites (inativo)
echo "\n1. Removendo canal 'imobsites' (inativo)...\n";
$stmt = $db->prepare("DELETE FROM tenant_message_channels WHERE channel_id = 'imobsites'");
$stmt->execute();
$affected = $stmt->rowCount();
echo "✅ Removido! Linhas afetadas: {$affected}\n";

// 2. Verificar se pixel12digital tem Channel ID
echo "\n2. Verificando Channel ID da sessão pixel12digital...\n";
$stmt = $db->prepare("
    SELECT whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'pixel12digital'
");
$stmt->execute();
$pixel = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pixel && $pixel['whapi_channel_id']) {
    echo "✅ Channel ID já configurado: " . $pixel['whapi_channel_id'] . "\n";
} else {
    echo "❌ Channel ID não configurado!\n";
    echo "   Para configurar, acesse o painel Whapi.Cloud e pegue o Channel ID\n";
    echo "   Depois execute: UPDATE whatsapp_provider_configs SET whapi_channel_id = 'CHANNEL_ID' WHERE session_name = 'pixel12digital';\n";
}

// 3. Verificar canais duplicados Meta
echo "\n3. Verificando canais Meta duplicados...\n";
$stmt = $db->prepare("
    SELECT id, meta_phone_number_id, is_active 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'meta_official'
");
$stmt->execute();
$metaChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($metaChannels) > 1) {
    echo "⚠️ Encontrados " . count($metaChannels) . " canais Meta:\n";
    foreach ($metaChannels as $c) {
        echo sprintf("- ID:%d | Phone:%s | Ativo:%s\n", 
            $c['id'], $c['meta_phone_number_id'], $c['is_active'] ? 'SIM' : 'NÃO');
    }
    
    // Manter apenas o primeiro
    $firstId = $metaChannels[0]['id'];
    echo "\nRemovendo duplicados (mantendo ID: {$firstId})...\n";
    
    $stmt = $db->prepare("
        DELETE FROM whatsapp_provider_configs 
        WHERE provider_type = 'meta_official' AND id != ?
    ");
    $stmt->execute([$firstId]);
    $affected = $stmt->rowCount();
    echo "✅ Removidos {$affected} canais duplicados!\n";
} else {
    echo "✅ Sem duplicações Meta\n";
}

// 4. Verificar tenant_message_channels
echo "\n4. Verificando canais no tenant...\n";
$stmt = $db->prepare("
    SELECT id, provider, channel_id, is_enabled 
    FROM tenant_message_channels 
    ORDER BY provider, channel_id
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Canais atuais:\n";
foreach ($channels as $c) {
    $status = $c['is_enabled'] ? '✅' : '❌';
    echo sprintf("%s ID:%d | %s | %s\n", 
        $status, $c['id'], $c['provider'], $c['channel_id']);
}

// 5. Adicionar pixel12digital se não existir
$pixelExists = false;
foreach ($channels as $c) {
    if ($c['channel_id'] === 'pixel12digital') {
        $pixelExists = true;
        break;
    }
}

if (!$pixelExists) {
    echo "\n5. Adicionando canal pixel12digital ao tenant...\n";
    $stmt = $db->prepare("
        INSERT INTO tenant_message_channels 
        (tenant_id, provider, channel_id, is_enabled, created_at, updated_at)
        VALUES (2, 'whapi', 'pixel12digital', 1, NOW(), NOW())
    ");
    $stmt->execute();
    echo "✅ Canal pixel12digital adicionado!\n";
} else {
    echo "\n5. Canal pixel12digital já existe no tenant\n";
}

// 6. Resumo final
echo "\n=== RESUMO FINAL ===\n";
$stmt = $db->prepare("
    SELECT id, provider, channel_id, is_enabled 
    FROM tenant_message_channels 
    WHERE is_enabled = 1
    ORDER BY provider, channel_id
");
$stmt->execute();
$activeChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Canais ATIVOS (que aparecerão no Inbox):\n";
foreach ($activeChannels as $c) {
    echo sprintf("- %s (%s)\n", $c['channel_id'], $c['provider']);
}

echo "\nPróximos passos:\n";
echo "1. Configure o Channel ID da sessão pixel12digital no painel Whapi.Cloud\n";
echo "2. Recarregue a página de Novas Mensagens/Inbox\n";
echo "3. Verifique se apenas os canais corretos aparecem\n";

echo "\n=== FIM ===\n";
