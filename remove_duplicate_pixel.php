<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== REMOVENDO DUPLICADO PIXEL12DIGITAL ===\n";

// Verificar duplicados
$stmt = $db->prepare("
    SELECT id, channel_id, provider 
    FROM tenant_message_channels 
    WHERE channel_id LIKE '%pixel12%'
    ORDER BY id
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Canais encontrados:\n";
foreach ($channels as $c) {
    echo sprintf("- ID:%d | %s | %s\n", $c['id'], $c['provider'], $c['channel_id']);
}

// Remover o duplicado (manter o primeiro)
if (count($channels) > 1) {
    $keepId = $channels[0]['id'];
    $removeId = $channels[1]['id'];
    
    echo "\nMantendo ID: {$keepId} (" . $channels[0]['channel_id'] . ")\n";
    echo "Removendo ID: {$removeId} (" . $channels[1]['channel_id'] . ")\n";
    
    $stmt = $db->prepare("DELETE FROM tenant_message_channels WHERE id = ?");
    $stmt->execute([$removeId]);
    
    echo "✅ Duplicado removido!\n";
} else {
    echo "\n✅ Sem duplicados para remover\n";
}

// Verificar configuração do Channel ID
echo "\nVerificando Channel ID da sessão pixel12digital...\n";
$stmt = $db->prepare("
    SELECT session_name, whapi_channel_id 
    FROM whatsapp_provider_configs 
    WHERE provider_type = 'whapi' AND session_name = 'pixel12digital'
");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

if ($config) {
    if ($config['whapi_channel_id']) {
        echo "✅ Channel ID configurado: " . $config['whapi_channel_id'] . "\n";
    } else {
        echo "❌ Channel ID NÃO configurado!\n";
        echo "   É preciso configurar o Channel ID no painel Whapi.Cloud\n";
    }
}

// Resumo final
echo "\n=== CANAIS FINAIS ===\n";
$stmt = $db->prepare("
    SELECT id, provider, channel_id, is_enabled 
    FROM tenant_message_channels 
    WHERE is_enabled = 1
    ORDER BY provider, channel_id
");
$stmt->execute();
$finalChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Canais ativos no Inbox:\n";
foreach ($finalChannels as $c) {
    echo sprintf("- %s (%s)\n", $c['channel_id'], $c['provider']);
}

echo "\n=== FIM ===\n";
