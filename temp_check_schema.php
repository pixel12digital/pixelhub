<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== VERIFICANDO ESTRUTURA DO BANCO ===\n\n";

$db = DB::getConnection();

// 1. Estrutura da tabela leads
echo "1. ESTRUTURA DA TABELA 'leads':\n";
echo str_repeat('-', 80) . "\n";
$columns = $db->query("DESCRIBE leads")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}

// 2. Estrutura da tabela opportunities
echo "\n2. ESTRUTURA DA TABELA 'opportunities':\n";
echo str_repeat('-', 80) . "\n";
$columns = $db->query("DESCRIBE opportunities")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  {$col['Field']} ({$col['Type']})\n";
}

// 3. Dados da oportunidade ID 29
echo "\n3. OPORTUNIDADE ID 29:\n";
echo str_repeat('-', 80) . "\n";
$opp = $db->query("
    SELECT o.*, l.name as lead_name, l.phone as lead_phone
    FROM opportunities o
    LEFT JOIN leads l ON o.lead_id = l.id
    WHERE o.id = 29
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if ($opp) {
    echo "  ID: {$opp['id']}\n";
    echo "  Nome: {$opp['name']}\n";
    echo "  lead_id: " . ($opp['lead_id'] ?: 'NULL') . "\n";
    echo "  tenant_id: " . ($opp['tenant_id'] ?: 'NULL') . "\n";
    echo "  Lead Nome: " . ($opp['lead_name'] ?: 'NULL') . "\n";
    echo "  Lead Phone: " . ($opp['lead_phone'] ?: 'NULL') . "\n";
}

// 4. Como Meta API identifica o tenant?
echo "\n4. CONFIGURAÇÕES META API:\n";
echo str_repeat('-', 80) . "\n";
$metaConfigs = $db->query("
    SELECT id, tenant_id, meta_phone_number_id, is_active
    FROM whatsapp_provider_configs
    WHERE provider_type = 'meta_official'
    AND is_active = 1
")->fetchAll(PDO::FETCH_ASSOC);

if (count($metaConfigs) > 0) {
    foreach ($metaConfigs as $config) {
        echo "  Config ID: {$config['id']}\n";
        echo "  tenant_id: {$config['tenant_id']}\n";
        echo "  Phone Number ID: {$config['meta_phone_number_id']}\n\n";
    }
} else {
    echo "  Nenhuma configuração Meta API ativa encontrada\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "ANÁLISE CONCLUÍDA\n";
echo str_repeat('=', 80) . "\n";
