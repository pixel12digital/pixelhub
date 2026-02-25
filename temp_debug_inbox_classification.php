<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== DEBUG: CLASSIFICAÇÃO DA CONVERSA NO INBOX ===\n\n";

// Simula a query que o CommunicationHubController usa
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.lead_id,
        c.is_incoming_lead,
        c.status,
        COALESCE(t.name, 'Sem tenant') as tenant_name,
        l.name as lead_name,
        l.phone as lead_phone
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    LEFT JOIN leads l ON c.lead_id = l.id
    WHERE c.id = 194
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

echo "DADOS DA CONVERSA 194 (como retornado pela query):\n";
foreach ($conv as $key => $value) {
    echo "  {$key}: " . ($value ?? 'NULL') . "\n";
}

echo "\n\nCLASSIFICAÇÃO:\n";
echo "  tenant_id: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
echo "  lead_id: " . ($conv['lead_id'] ?? 'NULL') . "\n";
echo "  is_incoming_lead: " . ($conv['is_incoming_lead'] ?? 'NULL') . "\n\n";

// Simula a lógica do código (linha 159 do CommunicationHubController)
$isIncomingLead = !empty($conv['is_incoming_lead']);

echo "LÓGICA DE SEPARAÇÃO (linha 159 do CommunicationHubController):\n";
echo "  !empty(\$thread['is_incoming_lead']) = " . ($isIncomingLead ? 'TRUE' : 'FALSE') . "\n\n";

if ($isIncomingLead) {
    echo "❌ PROBLEMA: Conversa será classificada como 'Conversas não vinculadas'\n";
    echo "   porque is_incoming_lead = {$conv['is_incoming_lead']}\n\n";
} else {
    echo "✅ OK: Conversa será classificada como conversa normal\n\n";
}

echo "ANÁLISE:\n";
if ($conv['lead_id'] && $conv['is_incoming_lead'] == 0) {
    echo "  ✅ Conversa ESTÁ vinculada ao Lead #{$conv['lead_id']}\n";
    echo "  ✅ is_incoming_lead = 0 (correto)\n";
    echo "  ✅ Deveria aparecer na lista principal do Inbox\n\n";
    
    echo "POSSÍVEL CAUSA:\n";
    echo "  O frontend pode estar em cache ou há outro filtro sendo aplicado.\n";
    echo "  Tente: Ctrl + Shift + R para recarregar sem cache\n";
} elseif ($conv['lead_id'] && $conv['is_incoming_lead'] == 1) {
    echo "  ❌ INCONSISTÊNCIA: lead_id existe MAS is_incoming_lead = 1\n";
    echo "  ❌ Isso faz a conversa aparecer em 'Conversas não vinculadas'\n";
    echo "  🔧 Precisa corrigir is_incoming_lead para 0\n";
}
