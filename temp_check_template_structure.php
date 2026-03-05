<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== VERIFICANDO ESTRUTURA DE TEMPLATES E RELAÇÕES ===\n\n";

$db = DB::getConnection();

// 1. Estrutura da tabela whatsapp_templates
echo "1. ESTRUTURA DA TABELA 'whatsapp_templates':\n";
echo str_repeat('-', 80) . "\n";
$columns = $db->query("DESCRIBE whatsapp_templates")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}

// 2. Verificar template usado no envio
echo "\n2. TEMPLATE 'prospeccao_sistema_corretores_v2':\n";
echo str_repeat('-', 80) . "\n";
$template = $db->query("
    SELECT id, name, tenant_id, status, language
    FROM whatsapp_templates 
    WHERE name LIKE '%prospeccao_sistema_corretores%'
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

if (count($template) > 0) {
    foreach ($template as $t) {
        echo "  ID: {$t['id']}\n";
        echo "  Nome: {$t['name']}\n";
        echo "  tenant_id: " . ($t['tenant_id'] ?: 'NULL') . "\n";
        echo "  Status: {$t['status']}\n";
        echo "  Language: {$t['language']}\n\n";
    }
} else {
    echo "  Nenhum template encontrado\n";
}

// 3. Estrutura da tabela tenant_message_channels
echo "\n3. CANAIS WHATSAPP (tenant_message_channels):\n";
echo str_repeat('-', 80) . "\n";
$channels = $db->query("
    SELECT id, tenant_id, channel_id, provider, is_enabled
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($channels as $ch) {
    echo "  ID: {$ch['id']}\n";
    echo "  tenant_id: " . ($ch['tenant_id'] ?: 'NULL') . "\n";
    echo "  channel_id: {$ch['channel_id']}\n";
    echo "  provider: {$ch['provider']}\n";
    echo "  is_enabled: {$ch['is_enabled']}\n\n";
}

// 4. Configurações Meta API
echo "\n4. CONFIGURAÇÕES META API (whatsapp_provider_configs):\n";
echo str_repeat('-', 80) . "\n";
$metaConfigs = $db->query("
    SELECT id, tenant_id, provider_type, meta_phone_number_id, is_active
    FROM whatsapp_provider_configs
    WHERE provider_type = 'meta_official'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($metaConfigs as $config) {
    echo "  ID: {$config['id']}\n";
    echo "  tenant_id: " . ($config['tenant_id'] ?: 'NULL') . "\n";
    echo "  provider_type: {$config['provider_type']}\n";
    echo "  meta_phone_number_id: {$config['meta_phone_number_id']}\n";
    echo "  is_active: {$config['is_active']}\n\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "ANÁLISE CONCLUÍDA\n";
echo str_repeat('=', 80) . "\n";
