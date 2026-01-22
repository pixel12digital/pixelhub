<?php

/**
 * Script para verificar vínculo de conversas do Ponto do Golfe e Renato Silva
 * 
 * Uso: php database/check-ponto-golfe-tenant-vinculo.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando vínculo de conversas do Ponto do Golfe ===\n\n";

// 1. Busca o tenant "Renato Silva da Silva Júnior | Ponto do Golfe"
echo "1. Buscando tenant 'Renato Silva da Silva Júnior | Ponto do Golfe':\n";
$stmt = $db->prepare("
    SELECT id, name, phone, email, cpf_cnpj
    FROM tenants
    WHERE name LIKE '%Ponto do Golfe%' OR name LIKE '%Renato Silva%'
    ORDER BY id DESC
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tenants)) {
    echo "   ❌ NENHUM TENANT ENCONTRADO\n";
} else {
    echo "   ✅ Encontrados " . count($tenants) . " tenant(s):\n";
    foreach ($tenants as $tenant) {
        echo "   - ID: {$tenant['id']}\n";
        echo "     Nome: {$tenant['name']}\n";
        echo "     Email: " . ($tenant['email'] ?? 'NULL') . "\n";
        echo "     WhatsApp: " . ($tenant['phone'] ?? 'NULL') . "\n";
        echo "     CPF/CNPJ: " . ($tenant['cpf_cnpj'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

// 2. Busca conversa do "Ponto do Golfe" (130894027333804@lid)
echo "\n2. Buscando conversa do 'Ponto do Golfe' (130894027333804@lid):\n";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.is_incoming_lead,
        c.status,
        c.last_message_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_external_id LIKE '%130894027333804%'
       OR c.contact_name LIKE '%Ponto Do Golfe%'
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmt->execute();
$conversasPontoGolfe = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversasPontoGolfe)) {
    echo "   ❌ NENHUMA CONVERSA ENCONTRADA\n";
} else {
    echo "   ✅ Encontradas " . count($conversasPontoGolfe) . " conversa(s):\n";
    foreach ($conversasPontoGolfe as $conv) {
        echo "   - Conversation ID: {$conv['id']}\n";
        echo "     Contact: {$conv['contact_external_id']}\n";
        echo "     Nome: " . ($conv['contact_name'] ?? 'NULL') . "\n";
        echo "     Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "     Tenant Nome: " . ($conv['tenant_name'] ?? 'Sem tenant') . "\n";
        echo "     Incoming Lead: " . ($conv['is_incoming_lead'] ? 'SIM' : 'NÃO') . "\n";
        echo "     Status: {$conv['status']}\n";
        echo "     Última mensagem: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

// 3. Busca conversa do "Renato Silva" (5300140662784@lid)
echo "\n3. Buscando conversa do 'Renato Silva' (5300140662784@lid):\n";
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.is_incoming_lead,
        c.status,
        c.last_message_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_external_id LIKE '%5300140662784%'
       OR c.contact_name LIKE '%Renato Silva%'
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmt->execute();
$conversasRenato = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversasRenato)) {
    echo "   ❌ NENHUMA CONVERSA ENCONTRADA\n";
} else {
    echo "   ✅ Encontradas " . count($conversasRenato) . " conversa(s):\n";
    foreach ($conversasRenato as $conv) {
        echo "   - Conversation ID: {$conv['id']}\n";
        echo "     Contact: {$conv['contact_external_id']}\n";
        echo "     Nome: " . ($conv['contact_name'] ?? 'NULL') . "\n";
        echo "     Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "     Tenant Nome: " . ($conv['tenant_name'] ?? 'Sem tenant') . "\n";
        echo "     Incoming Lead: " . ($conv['is_incoming_lead'] ? 'SIM' : 'NÃO') . "\n";
        echo "     Status: {$conv['status']}\n";
        echo "     Última mensagem: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

// 4. Resumo
echo "\n=== RESUMO ===\n";
if (!empty($tenants)) {
    $tenantPrincipal = $tenants[0];
    echo "Tenant Principal: ID {$tenantPrincipal['id']} - {$tenantPrincipal['name']}\n\n";
    
    if (!empty($conversasPontoGolfe)) {
        $convPontoGolfe = $conversasPontoGolfe[0];
        $vinculadoPontoGolfe = ($convPontoGolfe['tenant_id'] == $tenantPrincipal['id']);
        echo "Conversa 'Ponto do Golfe':\n";
        echo "  - Tenant ID: " . ($convPontoGolfe['tenant_id'] ?? 'NULL') . "\n";
        echo "  - Vinculado ao tenant principal: " . ($vinculadoPontoGolfe ? '✅ SIM' : '❌ NÃO') . "\n";
        if (!$vinculadoPontoGolfe && $convPontoGolfe['tenant_id']) {
            echo "  - ⚠️  Está vinculado a outro tenant: {$convPontoGolfe['tenant_name']}\n";
        }
        echo "\n";
    }
    
    if (!empty($conversasRenato)) {
        $convRenato = $conversasRenato[0];
        $vinculadoRenato = ($convRenato['tenant_id'] == $tenantPrincipal['id']);
        echo "Conversa 'Renato Silva':\n";
        echo "  - Tenant ID: " . ($convRenato['tenant_id'] ?? 'NULL') . "\n";
        echo "  - Vinculado ao tenant principal: " . ($vinculadoRenato ? '✅ SIM' : '❌ NÃO') . "\n";
        if (!$vinculadoRenato && $convRenato['tenant_id']) {
            echo "  - ⚠️  Está vinculado a outro tenant: {$convRenato['tenant_name']}\n";
        }
        echo "\n";
    }
}

echo "\n=== FIM ===\n";

