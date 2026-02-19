<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGANDO O CONFLITO ===\n";

// Verificar quem é o lead 9 que está usando a conversa 196
$sql_lead9 = "SELECT l.id, l.name, l.phone, l.email, l.status, l.created_at FROM leads l WHERE l.id = 9";
$stmt_lead9 = $db->prepare($sql_lead9);
$stmt_lead9->execute();
$lead9 = $stmt_lead9->fetch(PDO::FETCH_ASSOC);

if ($lead9) {
    echo "Lead 9 (roubando a conversa):\n";
    echo "  Nome: {$lead9['name']}\n";
    echo "  Telefone: {$lead9['phone']}\n";
    echo "  Email: " . ($lead9['email'] ?: 'N/A') . "\n";
    echo "  Status: {$lead9['status']}\n";
    echo "  Criado: {$lead9['created_at']}\n";
} else {
    echo "Lead 9 não encontrado.\n";
}

echo "\n=== OPORTUNIDADE ID 7 ===\n";
$sql_opp7 = "SELECT o.id, o.name, o.status, o.stage, o.lead_id, o.conversation_id, o.created_at, o.updated_at FROM opportunities o WHERE o.id = 7";
$stmt_opp7 = $db->prepare($sql_opp7);
$stmt_opp7->execute();
$opp7 = $stmt_opp7->fetch(PDO::FETCH_ASSOC);

if ($opp7) {
    echo "Oportunidade 7:\n";
    echo "  Nome: {$opp7['name']}\n";
    echo "  Status: {$opp7['status']}\n";
    echo "  Stage: {$opp7['stage']}\n";
    echo "  Lead ID: {$opp7['lead_id']}\n";
    echo "  Conversation ID: {$opp7['conversation_id']}\n";
    echo "  Criada: {$opp7['created_at']}\n";
    echo "  Atualizada: {$opp7['updated_at']}\n";
}

echo "\n=== VERIFICANDO SE HÁ OUTRAS OPORTUNIDADES COM LEAD 7 ===\n";
$sql_opp_lead7 = "SELECT o.id, o.name, o.status, o.stage, o.conversation_id FROM opportunities o WHERE o.lead_id = 7";
$stmt_opp_lead7 = $db->prepare($sql_opp_lead7);
$stmt_opp_lead7->execute();
$opp_lead7 = $stmt_opp_lead7->fetchAll(PDO::FETCH_ASSOC);

if (empty($opp_lead7)) {
    echo "❌ Nenhuma oportunidade para o lead 7 (Viviane).\n";
} else {
    foreach ($opp_lead7 as $opp) {
        echo "Oportunidade {$opp['id']}: {$opp['name']} ({$opp['status']})\n";
        echo "  Conversation ID: {$opp['conversation_id']}\n";
    }
}

echo "\n=== SOLUÇÃO ===\n";
echo "1. A oportunidade 7 está com lead_id = 9 mas conversation_id = 196 (da Viviane)\n";
echo "2. O lead 7 (Viviane) não tem nenhuma oportunidade\n";
echo "3. Precisamos corrigir: atualizar opportunity 7 para lead_id = 7\n";

// Corrigir o problema
echo "\n=== CORRIGINDO... ===\n";
$sql_fix = "UPDATE opportunities SET lead_id = 7 WHERE id = 7";
$stmt_fix = $db->prepare($sql_fix);
$result = $stmt_fix->execute();

if ($result) {
    echo "✅ Oportunidade 7 atualizada para lead_id = 7 (Viviane)\n";
    
    // Verificar se funcionou
    $sql_check = "SELECT o.id, o.name, o.lead_id, l.name as lead_name FROM opportunities o LEFT JOIN leads l ON o.lead_id = l.id WHERE o.id = 7";
    $stmt_check = $db->prepare($sql_check);
    $stmt_check->execute();
    $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($check) {
        echo "Verificação: Oportunidade {$check['id']} ({$check['name']}) agora está vinculada ao lead {$check['lead_id']} ({$check['lead_name']})\n";
    }
} else {
    echo "❌ Erro ao atualizar oportunidade\n";
}

echo "\n=== VERIFICANDO SE LEAD 9 FICOU SEM CONVERSA ===\n";
if ($lead9) {
    $sql_conv9 = "SELECT c.id, c.conversation_key, c.contact_name FROM conversations c WHERE c.lead_id = 9";
    $stmt_conv9 = $db->prepare($sql_conv9);
    $stmt_conv9->execute();
    $conv9 = $stmt_conv9->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conv9)) {
        echo "Lead 9 ({$lead9['name']}) agora não tem nenhuma conversa vinculada.\n";
    } else {
        foreach ($conv9 as $c) {
            echo "Lead 9 ainda tem conversa ID: {$c['id']} ({$c['contact_name']})\n";
        }
    }
}
?>
