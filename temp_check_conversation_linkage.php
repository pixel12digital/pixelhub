<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO VINCULAÇÃO DA CONVERSA 459 ===\n\n";

// Busca conversa completa
$stmt = $db->prepare("
    SELECT c.*, l.name as lead_name, l.phone as lead_phone, t.name as tenant_name
    FROM conversations c
    LEFT JOIN leads l ON c.lead_id = l.id
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.id = 459
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo "✗ Conversa 459 não encontrada!\n";
    exit(1);
}

echo "CONVERSA:\n";
echo "  ID: {$conv['id']}\n";
echo "  Contact External ID: {$conv['contact_external_id']}\n";
echo "  Contact Name: {$conv['contact_name']}\n";
echo "  Lead ID: " . ($conv['lead_id'] ?: 'NULL') . "\n";
echo "  Lead Name: " . ($conv['lead_name'] ?: 'NULL') . "\n";
echo "  Lead Phone: " . ($conv['lead_phone'] ?: 'NULL') . "\n";
echo "  Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
echo "  Tenant Name: " . ($conv['tenant_name'] ?: 'NULL') . "\n";
echo "  Is Incoming Lead: {$conv['is_incoming_lead']}\n";
echo "  Status: {$conv['status']}\n";
echo "  Channel ID: {$conv['channel_id']}\n\n";

// Verifica o Lead
if ($conv['lead_id']) {
    echo "LEAD VINCULADO:\n";
    $leadStmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
    $leadStmt->execute([$conv['lead_id']]);
    $lead = $leadStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lead) {
        echo "  ID: {$lead['id']}\n";
        echo "  Nome: {$lead['name']}\n";
        echo "  Telefone: {$lead['phone']}\n";
        echo "  Email: " . ($lead['email'] ?: 'NULL') . "\n\n";
        
        // Verifica se há oportunidade vinculada
        $oppStmt = $db->prepare("SELECT id, name, status, tenant_id FROM opportunities WHERE lead_id = ? ORDER BY id DESC LIMIT 1");
        $oppStmt->execute([$lead['id']]);
        $opp = $oppStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($opp) {
            echo "OPORTUNIDADE VINCULADA:\n";
            echo "  ID: {$opp['id']}\n";
            echo "  Nome: {$opp['name']}\n";
            echo "  Status: {$opp['status']}\n";
            echo "  Tenant ID: " . ($opp['tenant_id'] ?: 'NULL') . "\n\n";
        }
    }
}

echo "=== DIAGNÓSTICO ===\n\n";

if (!$conv['tenant_id']) {
    echo "⚠️ PROBLEMA: tenant_id está NULL\n";
    echo "   A conversa tem lead_id mas não tem tenant_id.\n";
    echo "   O Inbox filtra conversas por tenant_id, por isso aparece como 'não vinculada'.\n\n";
    
    // Busca tenant_id através da oportunidade vinculada ao lead
    if ($conv['lead_id']) {
        $oppTenantStmt = $db->prepare("
            SELECT o.tenant_id, t.name as tenant_name
            FROM opportunities o
            LEFT JOIN tenants t ON o.tenant_id = t.id
            WHERE o.lead_id = ?
            ORDER BY o.id DESC
            LIMIT 1
        ");
        $oppTenantStmt->execute([$conv['lead_id']]);
        $oppTenant = $oppTenantStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($oppTenant && $oppTenant['tenant_id']) {
            echo "✓ Encontrado tenant através da oportunidade:\n";
            echo "  Tenant ID: {$oppTenant['tenant_id']}\n";
            echo "  Tenant Name: {$oppTenant['tenant_name']}\n\n";
            echo "✓ SOLUÇÃO: Atualizar conversation.tenant_id = {$oppTenant['tenant_id']}\n\n";
        } else {
            echo "⚠️ Não foi possível encontrar tenant_id através da oportunidade\n";
        }
    }
} else {
    echo "✓ tenant_id está definido: {$conv['tenant_id']}\n";
    echo "   A conversa deveria aparecer vinculada.\n";
}

if ($conv['is_incoming_lead'] == 1) {
    echo "\n⚠️ is_incoming_lead = 1 (lead novo não vinculado)\n";
    echo "   Isso pode fazer o Inbox mostrar como 'não vinculada'.\n";
    echo "   SOLUÇÃO: UPDATE conversations SET is_incoming_lead = 0 WHERE id = 459;\n";
}
