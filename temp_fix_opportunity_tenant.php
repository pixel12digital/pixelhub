<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== CORRIGINDO OPORTUNIDADE ID 29 ===\n\n";

$db = DB::getConnection();

// 1. Verificar oportunidade atual
$opp = $db->query("
    SELECT o.id, o.name, o.tenant_id, o.lead_id, l.name as lead_name, l.phone 
    FROM opportunities o
    LEFT JOIN leads l ON o.lead_id = l.id
    WHERE o.id = 29
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$opp) {
    echo "❌ Oportunidade ID 29 não encontrada\n";
    exit(1);
}

echo "Oportunidade atual:\n";
echo "  ID: {$opp['id']}\n";
echo "  Nome: {$opp['name']}\n";
echo "  tenant_id: " . ($opp['tenant_id'] ?: 'NULL (PROBLEMA!)') . "\n";
echo "  lead_id: " . ($opp['lead_id'] ?: 'NULL') . "\n";
echo "  Lead: " . ($opp['lead_name'] ?: 'N/A') . "\n";
echo "  Telefone: " . ($opp['phone'] ?: 'N/A') . "\n\n";

// 2. Se tenant_id está vazio, tentar preencher a partir do lead
if (empty($opp['tenant_id']) && !empty($opp['lead_id'])) {
    echo "⚠️ tenant_id vazio, tentando obter do lead...\n";
    
    $lead = $db->query("
        SELECT id, name, phone, tenant_id 
        FROM leads 
        WHERE id = {$opp['lead_id']}
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($lead && !empty($lead['tenant_id'])) {
        echo "✅ Lead tem tenant_id: {$lead['tenant_id']}\n";
        
        // Atualiza oportunidade
        $db->exec("UPDATE opportunities SET tenant_id = {$lead['tenant_id']} WHERE id = 29");
        echo "✅ Oportunidade atualizada com tenant_id = {$lead['tenant_id']}\n";
    } else {
        echo "❌ Lead também não tem tenant_id\n";
        echo "   Criando tenant a partir do lead...\n";
        
        // Cria tenant a partir do lead
        $stmt = $db->prepare("
            INSERT INTO tenants (name, phone, status, created_at, updated_at)
            VALUES (?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$lead['name'], $lead['phone']]);
        $newTenantId = $db->lastInsertId();
        
        echo "✅ Tenant criado: ID = {$newTenantId}\n";
        
        // Atualiza lead e oportunidade
        $db->exec("UPDATE leads SET tenant_id = {$newTenantId} WHERE id = {$opp['lead_id']}");
        $db->exec("UPDATE opportunities SET tenant_id = {$newTenantId} WHERE id = 29");
        
        echo "✅ Lead e Oportunidade atualizados com tenant_id = {$newTenantId}\n";
    }
} elseif (empty($opp['tenant_id'])) {
    echo "❌ Oportunidade sem tenant_id e sem lead_id\n";
    echo "   Não é possível corrigir automaticamente\n";
    exit(1);
} else {
    echo "✅ Oportunidade já tem tenant_id: {$opp['tenant_id']}\n";
}

// 3. Verificar resultado final
$oppFinal = $db->query("
    SELECT o.id, o.name, o.tenant_id, t.name as tenant_name, t.phone as tenant_phone
    FROM opportunities o
    LEFT JOIN tenants t ON o.tenant_id = t.id
    WHERE o.id = 29
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

echo "\nOportunidade corrigida:\n";
echo "  ID: {$oppFinal['id']}\n";
echo "  Nome: {$oppFinal['name']}\n";
echo "  tenant_id: {$oppFinal['tenant_id']}\n";
echo "  Cliente: {$oppFinal['tenant_name']}\n";
echo "  Telefone: {$oppFinal['tenant_phone']}\n";

echo "\n✅ CORREÇÃO CONCLUÍDA!\n";
echo "Agora o envio deve funcionar corretamente.\n";
