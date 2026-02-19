<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== BUSCANDO CONVERSAS DA VIVIANE ===\n";

// Buscar conversas pelo telefone
$phone = '+5519983711169';
$sql_conv = "SELECT c.id, c.conversation_key, c.contact_name, c.contact_external_id, c.tenant_id, c.created_at, c.last_message_at FROM conversations c WHERE c.contact_external_id LIKE '%$phone' OR c.contact_external_id LIKE '%19983711169' ORDER BY c.last_message_at DESC";
$stmt_conv = $db->prepare($sql_conv);
$stmt_conv->execute();
$conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "Nenhuma conversa encontrada com este telefone.\n";
} else {
    foreach ($conversations as $conv) {
        echo "Conversa ID: {$conv['id']}\n";
        echo "Contato: {$conv['contact_name']}\n";
        echo "External ID: {$conv['contact_external_id']}\n";
        echo "Tenant: {$conv['tenant_id']}\n";
        
        // Verificar vinculo com oportunidade
        $sql_link = "SELECT opportunity_id FROM conversation_opportunity_links WHERE conversation_id = ?";
        $stmt_link = $db->prepare($sql_link);
        $stmt_link->execute([$conv['id']]);
        $link = $stmt_link->fetch(PDO::FETCH_ASSOC);
        
        if ($link) {
            echo "Opportunity ID vinculado: {$link['opportunity_id']}\n";
            
            // Buscar oportunidade
            $sql_opp = "SELECT o.id, o.name, o.status, o.stage, o.lead_id, o.conversation_id FROM opportunities o WHERE o.id = ?";
            $stmt_opp = $db->prepare($sql_opp);
            $stmt_opp->execute([$link['opportunity_id']]);
            $opp = $stmt_opp->fetch(PDO::FETCH_ASSOC);
            
            if ($opp) {
                echo "✅ OPORTUNIDADE ENCONTRADA:\n";
                echo "  ID: {$opp['id']}\n";
                echo "  Nome: {$opp['name']}\n";
                echo "  Status: {$opp['status']}\n";
                echo "  Stage: {$opp['stage']}\n";
                echo "  Lead ID: {$opp['lead_id']}\n";
                echo "  Conversation ID: {$opp['conversation_id']}\n";
                
                // Verificar se o lead ainda existe
                if ($opp['lead_id']) {
                    $sql_lead = "SELECT l.id, l.name, l.phone FROM leads l WHERE l.id = ?";
                    $stmt_lead = $db->prepare($sql_lead);
                    $stmt_lead->execute([$opp['lead_id']]);
                    $lead = $stmt_lead->fetch(PDO::FETCH_ASSOC);
                    
                    if ($lead) {
                        echo "  Lead associado: {$lead['name']} ({$lead['phone']})\n";
                    } else {
                        echo "  ❌ Lead ID {$opp['lead_id']} NÃO ENCONTRADO!\n";
                    }
                }
            } else {
                echo "❌ OPORTUNIDADE ID {$link['opportunity_id']} NÃO ENCONTRADA!\n";
            }
        } else {
            echo "Nenhuma oportunidade vinculada a esta conversa.\n";
        }
        echo "---\n";
    }
}

echo "\n=== BUSCANDO OPORTUNIDADES COM NOME VIVIANE ===\n";
$sql_viviane = "SELECT o.id, o.name, o.status, o.stage, o.lead_id, o.conversation_id FROM opportunities o WHERE o.name LIKE '%Viviane%' ORDER BY o.updated_at DESC";
$stmt_viviane = $db->prepare($sql_viviane);
$stmt_viviane->execute();
$viviane_opps = $stmt_viviane->fetchAll(PDO::FETCH_ASSOC);

if (empty($viviane_opps)) {
    echo "Nenhuma oportunidade com nome Viviane encontrada.\n";
} else {
    foreach ($viviane_opps as $opp) {
        echo "ID: {$opp['id']} | Nome: {$opp['name']} | Status: {$opp['status']} | Stage: {$opp['stage']}\n";
    }
}

echo "\n=== BUSCANDO LEADS COM NOME VIVIANE ===\n";
$sql_leads = "SELECT l.id, l.name, l.phone, l.status FROM leads l WHERE l.name LIKE '%Viviane%' ORDER BY l.updated_at DESC";
$stmt_leads = $db->prepare($sql_leads);
$stmt_leads->execute();
$viviane_leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);

if (empty($viviane_leads)) {
    echo "Nenhum lead com nome Viviane encontrado.\n";
} else {
    foreach ($viviane_leads as $lead) {
        echo "ID: {$lead['id']} | Nome: {$lead['name']} | Telefone: {$lead['phone']} | Status: {$lead['status']}\n";
        
        // Verificar oportunidades deste lead
        $sql_lead_opps = "SELECT o.id, o.name, o.status FROM opportunities o WHERE o.lead_id = ?";
        $stmt_lead_opps = $db->prepare($sql_lead_opps);
        $stmt_lead_opps->execute([$lead['id']]);
        $lead_opps = $stmt_lead_opps->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($lead_opps)) {
            echo "  Oportunidades deste lead:\n";
            foreach ($lead_opps as $opp) {
                echo "    - ID: {$opp['id']} | {$opp['name']} | {$opp['status']}\n";
            }
        }
    }
}

echo "\n=== VERIFICANDO STATUS POSSÍVEIS ===\n";
$sql_statuses = "SELECT DISTINCT status FROM opportunities";
$stmt_statuses = $db->prepare($sql_statuses);
$stmt_statuses->execute();
$statuses = $stmt_statuses->fetchAll(PDO::FETCH_COLUMN);

echo "Status disponíveis: " . implode(', ', $statuses) . "\n";
?>
