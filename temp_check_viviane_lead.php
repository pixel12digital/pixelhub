<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGANDO LEAD DA VIVIANE ===\n";

$lead_id = 7;

// Buscar dados completos do lead
$sql_lead = "SELECT l.id, l.name, l.phone, l.email, l.status, l.created_at, l.updated_at FROM leads l WHERE l.id = ?";
$stmt_lead = $db->prepare($sql_lead);
$stmt_lead->execute([$lead_id]);
$lead = $stmt_lead->fetch(PDO::FETCH_ASSOC);

if ($lead) {
    echo "Lead encontrado:\n";
    echo "  ID: {$lead['id']}\n";
    echo "  Nome: {$lead['name']}\n";
    echo "  Telefone: {$lead['phone']}\n";
    echo "  Email: " . ($lead['email'] ?: 'N/A') . "\n";
    echo "  Status: {$lead['status']}\n";
    echo "  Criado: {$lead['created_at']}\n";
    echo "  Atualizado: {$lead['updated_at']}\n";
} else {
    echo "Lead não encontrado.\n";
    exit;
}

echo "\n=== OPORTUNIDADES VINCULADAS A ESTE LEAD ===\n";
$sql_opps = "SELECT o.id, o.name, o.status, o.stage, o.created_at, o.updated_at, o.lost_at, o.lost_reason FROM opportunities o WHERE o.lead_id = ? ORDER BY o.created_at DESC";
$stmt_opps = $db->prepare($sql_opps);
$stmt_opps->execute([$lead_id]);
$opportunities = $stmt_opps->fetchAll(PDO::FETCH_ASSOC);

if (empty($opportunities)) {
    echo "❌ NENHUMA OPORTUNIDADE VINCULADA A ESTE LEAD!\n";
    echo "Este é o problema: a Viviane existe como lead, mas não tem oportunidades.\n";
} else {
    foreach ($opportunities as $opp) {
        echo "Oportunidade ID: {$opp['id']}\n";
        echo "  Nome: {$opp['name']}\n";
        echo "  Status: {$opp['status']}\n";
        echo "  Stage: {$opp['stage']}\n";
        echo "  Criada: {$opp['created_at']}\n";
        echo "  Atualizada: {$opp['updated_at']}\n";
        
        if ($opp['lost_at']) {
            echo "  ⚠️ PERDIDA em: {$opp['lost_at']}\n";
            echo "  Motivo: " . ($opp['lost_reason'] ?: 'N/A') . "\n";
        }
        
        echo "---\n";
    }
}

echo "\n=== CONVERSAS VINCULADAS A ESTE LEAD ===\n";
$sql_conv = "SELECT c.id, c.conversation_key, c.contact_name, c.contact_external_id, c.created_at, c.last_message_at FROM conversations c WHERE c.lead_id = ? ORDER BY c.last_message_at DESC";
$stmt_conv = $db->prepare($sql_conv);
$stmt_conv->execute([$lead_id]);
$conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "Nenhuma conversa vinculada diretamente a este lead.\n";
} else {
    foreach ($conversations as $conv) {
        echo "Conversa ID: {$conv['id']}\n";
        echo "  Chave: {$conv['conversation_key']}\n";
        echo "  Contato: {$conv['contact_name']}\n";
        echo "  External ID: {$conv['contact_external_id']}\n";
        echo "  Última msg: {$conv['last_message_at']}\n";
        
        // Verificar se há opportunity_link
        $sql_link = "SELECT opportunity_id FROM conversation_opportunity_links WHERE conversation_id = ?";
        $stmt_link = $db->prepare($sql_link);
        $stmt_link->execute([$conv['id']]);
        $link = $stmt_link->fetch(PDO::FETCH_ASSOC);
        
        if ($link) {
            echo "  ⚠️ Opportunity ID vinculado: {$link['opportunity_id']} (mas não existe!)\n";
        }
        
        echo "---\n";
    }
}

echo "\n=== BUSCANDO CONVERSAS PELO TELEFONE DO LEAD ===\n";
$phone_formatted = str_replace(['(', ')', ' ', '-'], '', $lead['phone']);
echo "Telefone formatado: $phone_formatted\n";

$sql_phone = "SELECT c.id, c.conversation_key, c.contact_name, c.contact_external_id, c.lead_id, c.created_at, c.last_message_at FROM conversations c WHERE c.contact_external_id LIKE '%$phone_formatted%' ORDER BY c.last_message_at DESC";
$stmt_phone = $db->prepare($sql_phone);
$stmt_phone->execute();
$phone_convs = $stmt_phone->fetchAll(PDO::FETCH_ASSOC);

if (empty($phone_convs)) {
    echo "Nenhuma conversa encontrada pelo telefone.\n";
} else {
    foreach ($phone_convs as $conv) {
        echo "Conversa ID: {$conv['id']} (Lead ID: " . ($conv['lead_id'] ?: 'N/A') . ")\n";
        echo "  Contato: {$conv['contact_name']}\n";
        echo "  External ID: {$conv['contact_external_id']}\n";
        echo "  Última msg: {$conv['last_message_at']}\n";
        
        // Verificar opportunity_link
        $sql_link = "SELECT opportunity_id FROM conversation_opportunity_links WHERE conversation_id = ?";
        $stmt_link = $db->prepare($sql_link);
        $stmt_link->execute([$conv['id']]);
        $link = $stmt_link->fetch(PDO::FETCH_ASSOC);
        
        if ($link) {
            echo "  ⚠️ Opportunity ID vinculado: {$link['opportunity_id']}\n";
            
            // Verificar se a oportunidade existe
            $sql_check_opp = "SELECT id, name, status FROM opportunities WHERE id = ?";
            $stmt_check_opp = $db->prepare($sql_check_opp);
            $stmt_check_opp->execute([$link['opportunity_id']]);
            $check_opp = $stmt_check_opp->fetch(PDO::FETCH_ASSOC);
            
            if ($check_opp) {
                echo "  ✅ Oportunidade encontrada: {$check_opp['name']} ({$check_opp['status']})\n";
            } else {
                echo "  ❌ OPORTUNIDADE NÃO EXISTE MAIS!\n";
            }
        }
        
        echo "---\n";
    }
}
?>
