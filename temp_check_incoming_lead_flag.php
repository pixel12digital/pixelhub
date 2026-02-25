<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO FLAG is_incoming_lead ===\n\n";

// Verifica a conversa ID 194
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.contact_name,
        c.lead_id,
        c.tenant_id,
        c.is_incoming_lead,
        c.status,
        l.name as lead_name
    FROM conversations c
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.id = 194
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

echo "CONVERSA ID 194:\n";
echo "  Contact External ID: {$conv['contact_external_id']}\n";
echo "  Contact Name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
echo "  Lead ID: " . ($conv['lead_id'] ?: 'NULL') . "\n";
echo "  Lead Name: " . ($conv['lead_name'] ?: 'NULL') . "\n";
echo "  Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
echo "  is_incoming_lead: " . ($conv['is_incoming_lead'] ?? 'NULL') . "\n";
echo "  Status: " . ($conv['status'] ?: 'NULL') . "\n\n";

echo "PROBLEMA IDENTIFICADO:\n";
if ($conv['is_incoming_lead'] == 1 && $conv['lead_id'] !== null) {
    echo "  ❌ is_incoming_lead = 1 MAS lead_id = {$conv['lead_id']}\n";
    echo "  ❌ Isso faz a conversa aparecer em 'Conversas não vinculadas'\n";
    echo "  ✅ CORREÇÃO: is_incoming_lead deve ser 0 quando lead_id não é NULL\n\n";
    
    // Corrige o flag
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET is_incoming_lead = 0 
        WHERE id = 194
    ");
    $updateStmt->execute();
    
    echo "✅ Flag is_incoming_lead atualizado para 0\n\n";
    
    // Verifica resultado
    $stmt->execute();
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "RESULTADO APÓS CORREÇÃO:\n";
    echo "  is_incoming_lead: {$updated['is_incoming_lead']}\n";
    echo "  Lead ID: {$updated['lead_id']}\n\n";
    
    echo "✅ CORREÇÃO CONCLUÍDA!\n";
    echo "Agora a conversa deve aparecer na seção principal do Inbox,\n";
    echo "vinculada ao Lead #6, e NÃO em 'Conversas não vinculadas'\n";
} else {
    echo "  ✅ Flag is_incoming_lead está correto\n";
}
