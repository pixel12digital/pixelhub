<?php
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Debug do envio da mensagem ID 2 ===\n\n";

// 1. Verificar dados completos da mensagem
echo "1. Dados da scheduled_message ID 2:\n";
$sql = "SELECT sm.*, 
       o.name as opportunity_name,
       o.lead_id as opp_lead_id,
       l.name as lead_name, l.phone as lead_phone,
       t.name as tenant_name, t.phone as tenant_phone
FROM scheduled_messages sm
LEFT JOIN opportunities o ON sm.opportunity_id = o.id
LEFT JOIN leads l ON (sm.lead_id = l.id OR o.lead_id = l.id)
LEFT JOIN tenants t ON sm.tenant_id = t.id
WHERE sm.id = 2";
$stmt = $db->prepare($sql);
$stmt->execute();
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
    echo "Mensagem ID 2 não encontrada!\n";
    exit(1);
}

echo "   ID: {$msg['id']}\n";
echo "   Agendado: {$msg['scheduled_at']}\n";
echo "   Status: {$msg['status']}\n";
echo "   Opportunity ID: {$msg['opportunity_id']} | Nome: {$msg['opportunity_name']}\n";
echo "   Lead ID (msg): {$msg['lead_id']} | Lead ID (opp): {$msg['opp_lead_id']}\n";
echo "   Lead Name: {$msg['lead_name']}\n";
echo "   Lead Phone: {$msg['lead_phone']}\n";
echo "   Tenant: {$msg['tenant_name']} | Fone: {$msg['tenant_phone']}\n";
echo "   Conversation ID: {$msg['conversation_id']}\n";

// 2. Verificar conversations deste lead/opportunity
echo "\n2. Conversas existentes:\n";
$sql2 = "SELECT * FROM conversations 
WHERE (lead_id = ? OR lead_id = ?) 
ORDER BY created_at DESC
LIMIT 5";
$stmt2 = $db->prepare($sql2);
$stmt2->execute([$msg['lead_id'], $msg['opp_lead_id']]);
$convs = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($convs)) {
    echo "   Nenhuma conversa encontrada para este lead.\n";
} else {
    foreach ($convs as $conv) {
        echo "   ID: {$conv['id']} | Channel: {$conv['channel_type']} | Contact: {$conv['contact_external_id']}\n";
        echo "   Lead ID: {$conv['lead_id']} | Tenant ID: {$conv['tenant_id']}\n";
        echo "   Criada: {$conv['created_at']}\n\n";
    }
}

// 3. Verificar canais WhatsApp disponíveis
echo "\n3. Canais WhatsApp configurados:\n";
$sql3 = "SELECT * FROM tenant_message_channels WHERE channel_type = 'whatsapp'";
$stmt3 = $db->prepare($sql3);
$stmt3->execute();
$channels = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   Nenhum canal WhatsApp configurado!\n";
} else {
    foreach ($channels as $ch) {
        echo "   Tenant ID: {$ch['tenant_id']} | Session: {$ch['session_id']} | Ativo: " . ($ch['is_active'] ? 'Sim' : 'Não') . "\n";
    }
}

// 4. Tentar enviar manualmente para ver o erro
echo "\n4. Tentativa de envio manual:\n";
$phone = $msg['lead_phone'] ?? $msg['tenant_phone'] ?? null;
if (!$phone) {
    echo "   ❌ Telefone não encontrado!\n";
} else {
    echo "   Telefone encontrado: $phone\n";
    
    // Normaliza telefone
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (!str_starts_with($phone, '55')) {
        $phone = '55' . $phone;
    }
    echo "   Telefone normalizado: $phone\n";
    
    // Verificar se existe conversation_id válido
    $conversationId = null;
    if (!empty($convs)) {
        $conversationId = $convs[0]['id'];
        echo "   Usando conversation_id: $conversationId\n";
    }
    
    try {
        require_once __DIR__ . '/src/Controllers/CommunicationHubController.php';
        $controller = new \PixelHub\Controllers\CommunicationHubController();
        
        // Simula POST
        $_POST = [
            'channel_id' => 'pixel12digital',
            'to' => $phone,
            'message' => $msg['message_text'],
            'conversation_id' => $conversationId,
        ];
        
        echo "   Enviando mensagem...\n";
        ob_start();
        $result = $controller->send();
        $output = ob_get_clean();
        
        echo "   Resultado: $output\n";
        echo "   ✅ Envio aparentemente bem-sucedido!\n";
        
        // Atualiza status
        $update = $db->prepare("UPDATE scheduled_messages SET status = 'sent', sent_at = NOW() WHERE id = 2");
        $update->execute();
        echo "   Status atualizado para 'sent'\n";
        
    } catch (\Exception $e) {
        echo "   ❌ Erro no envio: " . $e->getMessage() . "\n";
        
        // Atualiza status com falha
        $update = $db->prepare("UPDATE scheduled_messages SET status = 'failed', failed_reason = ? WHERE id = 2");
        $update->execute([$e->getMessage()]);
        echo "   Status atualizado para 'failed'\n";
    }
}

echo "\n=== Fim do debug ===\n";
?>
