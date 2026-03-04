<?php
require 'vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== Verificando Lead #12 ===\n\n";

// Verifica na tabela leads
$stmt = $db->prepare("SELECT id, name, phone, email, company, status FROM leads WHERE id = 12");
$stmt->execute();
$lead = $stmt->fetch();

if ($lead) {
    echo "Lead encontrado na tabela 'leads':\n";
    foreach ($lead as $k => $v) {
        echo "  $k: " . ($v ?? 'NULL') . "\n";
    }
} else {
    echo "Lead #12 NÃO encontrado na tabela 'leads'\n";
}

echo "\n";

// Verifica na tabela tenants (novo modelo unificado)
$stmt = $db->prepare("SELECT id, name, phone, email, company, contact_type, status FROM tenants WHERE id = 12 OR name LIKE '%Lead #12%'");
$stmt->execute();
$tenants = $stmt->fetchAll();

if ($tenants) {
    echo "Registros encontrados na tabela 'tenants':\n";
    foreach ($tenants as $t) {
        echo "  ID: {$t['id']}, Nome: {$t['name']}, Tipo: {$t['contact_type']}, Status: {$t['status']}\n";
    }
} else {
    echo "Nenhum registro relacionado ao Lead #12 na tabela 'tenants'\n";
}

echo "\n";

// Busca usando ContactService
echo "=== Testando busca via ContactService ===\n";
$results = \PixelHub\Services\ContactService::searchLeads('Lead #12', 20);
echo "Resultados para 'Lead #12': " . count($results) . " encontrado(s)\n";
foreach ($results as $r) {
    echo "  ID: {$r['id']}, Nome: {$r['name']}\n";
}

echo "\n";

// Busca por '12'
$results = \PixelHub\Services\ContactService::searchLeads('12', 20);
echo "Resultados para '12': " . count($results) . " encontrado(s)\n";
foreach ($results as $r) {
    echo "  ID: {$r['id']}, Nome: {$r['name']}\n";
}
