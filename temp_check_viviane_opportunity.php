<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

// Buscar conversas com o telefone da Viviane
$phone = '+5519983711169';
$sql_conv = "SELECT c.id, c.conversation_key, c.contact_name, c.contact_external_id, c.tenant_id, c.created_at, c.last_message_at FROM conversations c WHERE c.contact_external_id LIKE '%$phone' OR c.contact_external_id LIKE '%19983711169' ORDER BY c.last_message_at DESC";
$stmt_conv = $db->prepare($sql_conv);
$stmt_conv->execute();
$conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

echo "=== CONVERSAS ENCONTRADAS ===\n";
foreach ($conversations as $conv) {
    echo "Conversa ID: {$conv['id']}\n";
    echo "Chave: {$conv['conversation_key']}\n";
    echo "Contato: {$conv['contact_name']}\n";
    echo "External ID: {$conv['contact_external_id']}\n";
    echo "Tenant: {$conv['tenant_id']}\n";
    echo "Criada: {$conv['created_at']}\n";
    echo "Última msg: {$conv['last_message_at']}\n";
    
    // Verificar se há opportunity_id vinculado
    $sql_opp = "SELECT opportunity_id FROM conversation_opportunity_links WHERE conversation_id = ?";
    $stmt_opp = $db->prepare($sql_opp);
    $stmt_opp->execute([$conv['id']]);
    $link = $stmt_opp->fetch(PDO::FETCH_ASSOC);
    
    if ($link) {
        echo "Opportunity ID vinculado: {$link['opportunity_id']}\n";
        
        // Verificar se a oportunidade ainda existe
        $sql_check = "SELECT id, name, status, deleted_at FROM opportunities WHERE id = ?";
        $stmt_check = $db->prepare($sql_check);
        $stmt_check->execute([$link['opportunity_id']]);
        $opportunity = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($opportunity) {
            echo "Status da oportunidade: {$opportunity['status']}\n";
            if ($opportunity['deleted_at']) {
                echo "OPORTUNIDADE EXCLUIDA em: {$opportunity['deleted_at']}\n";
            }
        } else {
            echo "OPORTUNIDADE NAO ENCONTRADA (foi excluida)\n";
        }
    } else {
        echo "Nenhuma oportunidade vinculada\n";
    }
    
    echo "----------------------------------------\n";
}

// Buscar logs de exclusão de oportunidades recentes
echo "\n=== LOGS DE EXCLUSAO RECENTES ===\n";
$sql_logs = "SELECT * FROM opportunity_audit_log WHERE action = 'deleted' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 10";
try {
    $stmt_logs = $db->prepare($sql_logs);
    $stmt_logs->execute();
    $logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($logs)) {
        echo "Nenhum log de exclusao encontrado.\n";
    } else {
        foreach ($logs as $log) {
            echo "Data: {$log['created_at']}\n";
            echo "Opportunity ID: {$log['opportunity_id']}\n";
            echo "Usuario: {$log['user_id']}\n";
            echo "Dados: " . json_encode($log['old_values']) . "\n";
            echo "----------------------------------------\n";
        }
    }
} catch (Exception $e) {
    echo "Tabela de audit nao encontrada: " . $e->getMessage() . "\n";
}

// Buscar oportunidades com nome Viviane
echo "\n=== OPORTUNIDADES COM NOME VIVIANE ===\n";
$sql_viviane = "SELECT id, name, contact_name, contact_phone, status, deleted_at FROM opportunities WHERE contact_name LIKE '%Viviane%' OR name LIKE '%Viviane%' ORDER BY updated_at DESC";
$stmt_viviane = $db->prepare($sql_viviane);
$stmt_viviane->execute();
$viviane_opps = $stmt_viviane->fetchAll(PDO::FETCH_ASSOC);

if (empty($viviane_opps)) {
    echo "Nenhuma oportunidade com nome Viviane encontrada.\n";
} else {
    foreach ($viviane_opps as $opp) {
        echo "ID: {$opp['id']}\n";
        echo "Nome: {$opp['name']}\n";
        echo "Contato: {$opp['contact_name']}\n";
        echo "Telefone: {$opp['contact_phone']}\n";
        echo "Status: {$opp['status']}\n";
        if ($opp['deleted_at']) {
            echo "EXCLUIDA em: {$opp['deleted_at']}\n";
        }
        echo "----------------------------------------\n";
    }
}
?>
