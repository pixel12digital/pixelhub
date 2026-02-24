<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== CORRIGINDO VINCULAÇÃO DO LUIZ CARLOS ===\n\n";

// Atualiza conversa 459 para vincular ao lead 10
$conversationId = 459;
$leadId = 10;

$stmt = $db->prepare("
    UPDATE conversations 
    SET lead_id = ?,
        is_incoming_lead = 0,
        contact_name = 'Luis Carlos'
    WHERE id = ?
");

$result = $stmt->execute([$leadId, $conversationId]);

if ($result) {
    echo "✓ Conversa ID {$conversationId} vinculada ao Lead ID {$leadId} (Luis Carlos)\n";
    echo "✓ is_incoming_lead definido como 0\n";
    echo "✓ contact_name atualizado para 'Luis Carlos'\n\n";
    
    // Verifica resultado
    $checkStmt = $db->prepare("
        SELECT id, contact_external_id, contact_name, lead_id, tenant_id, 
               channel_id, status, is_incoming_lead
        FROM conversations 
        WHERE id = ?
    ");
    $checkStmt->execute([$conversationId]);
    $conv = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Estado atual da conversa:\n";
    echo "  - ID: {$conv['id']}\n";
    echo "  - External ID: {$conv['contact_external_id']}\n";
    echo "  - Nome: {$conv['contact_name']}\n";
    echo "  - Lead ID: " . ($conv['lead_id'] ?: 'NULL') . "\n";
    echo "  - Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "  - Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
    echo "  - Status: {$conv['status']}\n";
    echo "  - Is Incoming Lead: {$conv['is_incoming_lead']}\n";
} else {
    echo "✗ Erro ao atualizar conversa\n";
}

echo "\n=== TESTE: Executando resolveLeadByPhone ===\n";
// Simula a lógica do resolveLeadByPhone para verificar se agora funciona

$contactExternalId = '555599235045';
$cleaned = preg_replace('/@.*$/', '', $contactExternalId);
$contactDigits = preg_replace('/[^0-9]/', '', $cleaned);

echo "Contato normalizado: {$contactDigits}\n\n";

$stmt = $db->query("SELECT id, name, phone FROM leads WHERE phone IS NOT NULL AND phone != '' ORDER BY id DESC LIMIT 20");
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Testando match com leads cadastrados:\n";
foreach ($leads as $lead) {
    $leadPhone = preg_replace('/[^0-9]/', '', $lead['phone']);
    if (empty($leadPhone)) continue;
    
    // Garante prefixo 55
    if (substr($leadPhone, 0, 2) !== '55' && (strlen($leadPhone) === 10 || strlen($leadPhone) === 11)) {
        $leadPhone = '55' . $leadPhone;
    }
    
    // Testa últimos 8 dígitos
    $contactLast8 = substr($contactDigits, -8);
    $leadLast8 = substr($leadPhone, -8);
    
    if ($contactLast8 === $leadLast8) {
        echo "  ✓ MATCH: Lead ID {$lead['id']} - {$lead['name']} - {$lead['phone']}\n";
        echo "    Contact: {$contactDigits} (últimos 8: {$contactLast8})\n";
        echo "    Lead: {$leadPhone} (últimos 8: {$leadLast8})\n";
        break;
    }
}

echo "\n✓ Correção concluída!\n";
