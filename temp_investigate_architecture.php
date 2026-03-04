<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== ARQUITETURA DO SISTEMA: LEADS vs TENANTS ===\n\n";

// 1. Verifica estrutura da tabela leads
echo "1. TABELA LEADS:\n";
$cols = $db->query("SHOW COLUMNS FROM leads")->fetchAll();
echo "   Colunas: " . implode(', ', array_column($cols, 'Field')) . "\n";
$count = $db->query("SELECT COUNT(*) FROM leads")->fetchColumn();
echo "   Total de registros: $count\n\n";

// 2. Verifica estrutura da tabela tenants
echo "2. TABELA TENANTS:\n";
$cols = $db->query("SHOW COLUMNS FROM tenants")->fetchAll();
$hasContactType = in_array('contact_type', array_column($cols, 'Field'));
echo "   Tem coluna contact_type? " . ($hasContactType ? 'SIM' : 'NÃO') . "\n";

if ($hasContactType) {
    $types = $db->query("SELECT contact_type, COUNT(*) as count FROM tenants GROUP BY contact_type")->fetchAll();
    echo "   Distribuição por contact_type:\n";
    foreach ($types as $t) {
        echo "     - {$t['contact_type']}: {$t['count']} registros\n";
    }
} else {
    $count = $db->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
    echo "   Total de registros: $count\n";
}

echo "\n3. MODELO ATUAL:\n";
if ($hasContactType) {
    echo "   ✓ Sistema usa MODELO UNIFICADO (tenants.contact_type)\n";
    echo "   ✓ Tabela 'leads' pode ser legada ou coexistir\n";
} else {
    echo "   ✓ Sistema usa MODELO SEPARADO (tabelas leads + tenants)\n";
}

// 4. Verifica qual tabela o modal de vinculação usa
echo "\n4. ENDPOINT DE BUSCA:\n";
echo "   URL: /leads/search-ajax\n";
echo "   Controller: OpportunitiesController@searchLeads\n";
echo "   Service: ContactService::searchLeads()\n";

// 5. Testa busca atual
echo "\n5. TESTE DE BUSCA:\n";
echo "   Buscando 'Lead #12'...\n";
$results = \PixelHub\Services\ContactService::searchLeads('Lead #12', 20);
echo "   Resultados: " . count($results) . "\n";
if (empty($results)) {
    echo "   ❌ Lead #12 NÃO aparece na busca\n";
} else {
    foreach ($results as $r) {
        echo "   ✓ Encontrado: ID={$r['id']}, Nome={$r['name']}\n";
    }
}

// 6. Verifica onde está o Lead #12
echo "\n6. LOCALIZAÇÃO DO LEAD #12:\n";
$inLeads = $db->query("SELECT COUNT(*) FROM leads WHERE id = 12")->fetchColumn();
$inTenants = $db->query("SELECT COUNT(*) FROM tenants WHERE id = 12")->fetchColumn();
echo "   Na tabela 'leads': " . ($inLeads ? 'SIM' : 'NÃO') . "\n";
echo "   Na tabela 'tenants': " . ($inTenants ? 'SIM' : 'NÃO') . "\n";

if ($inLeads) {
    $lead = $db->query("SELECT id, name, phone, status FROM leads WHERE id = 12")->fetch();
    echo "   Dados em 'leads': ID={$lead['id']}, Nome={$lead['name']}, Status={$lead['status']}\n";
}

if ($inTenants) {
    $tenant = $db->query("SELECT id, name, contact_type FROM tenants WHERE id = 12")->fetch();
    echo "   Dados em 'tenants': ID={$tenant['id']}, Nome={$tenant['name']}, Tipo={$tenant['contact_type']}\n";
}

echo "\n=== CONCLUSÃO ===\n";
if ($hasContactType) {
    echo "Sistema em TRANSIÇÃO para modelo unificado.\n";
    echo "ContactService::searchLeads() busca apenas em tenants.contact_type='lead'\n";
    echo "Leads antigos na tabela 'leads' NÃO aparecem na busca.\n";
    echo "\nSOLUÇÕES POSSÍVEIS:\n";
    echo "A) Migrar Lead #12 da tabela 'leads' para 'tenants'\n";
    echo "B) Ajustar ContactService::searchLeads() para buscar em AMBAS as tabelas\n";
} else {
    echo "Sistema usa modelo SEPARADO (leads + tenants).\n";
    echo "Verificar por que ContactService não busca na tabela 'leads'.\n";
}
