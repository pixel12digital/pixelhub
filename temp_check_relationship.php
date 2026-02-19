<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICANDO RELACIONAMENTO CONVERSAS-OORTUNIDADES ===\n";

// Verificar se há campo opportunity_id na tabela conversations
$stmt = $db->prepare('DESCRIBE conversations');
$stmt->execute();
$conv_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Colunas da tabela conversations:\n";
foreach ($conv_columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}

// Verificar se há campo conversation_id na tabela opportunities
echo "\nColunas da tabela opportunities (relevante):\n";
$stmt = $db->prepare('DESCRIBE opportunities');
$stmt->execute();
$opp_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($opp_columns as $col) {
    if (strpos($col['Field'], 'conversation') !== false || strpos($col['Field'], 'lead') !== false) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
}

echo "\n=== BUSCANDO CONVERSA DA VIVIANE ===\n";
$sql_conv = "SELECT c.id, c.conversation_key, c.contact_name, c.contact_external_id, c.lead_id, c.created_at, c.last_message_at FROM conversations c WHERE c.contact_name LIKE '%Viviane%' ORDER BY c.last_message_at DESC";
$stmt_conv = $db->prepare($sql_conv);
$stmt_conv->execute();
$conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "Conversa ID: {$conv['id']}\n";
    echo "  Lead ID: {$conv['lead_id']}\n";
    echo "  Contact: {$conv['contact_name']}\n";
    echo "  External ID: {$conv['contact_external_id']}\n";
    echo "  Última msg: {$conv['last_message_at']}\n";
    
    // Se há conversation_id, buscar oportunidades
    if (isset($conv_columns) && !empty($conv_columns)) {
        $has_conversation_field = false;
        foreach ($conv_columns as $col) {
            if ($col['Field'] === 'conversation_id') {
                $has_conversation_field = true;
                break;
            }
        }
        
        if ($has_conversation_field) {
            $sql_opp = "SELECT o.id, o.name, o.status, o.stage FROM opportunities o WHERE o.conversation_id = ?";
            $stmt_opp = $db->prepare($sql_opp);
            $stmt_opp->execute([$conv['id']]);
            $opps = $stmt_opp->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($opps)) {
                echo "  Oportunidades vinculadas:\n";
                foreach ($opps as $opp) {
                    echo "    - ID: {$opp['id']} | {$opp['name']} | {$opp['status']}\n";
                }
            } else {
                echo "  ❌ Nenhuma oportunidade vinculada pelo conversation_id\n";
            }
        }
    }
    
    echo "---\n";
}

echo "\n=== VERIFICANDO SE HÁ ALGUMA OPORTUNIDADE SEM LEAD ===\n";
$sql_orphan = "SELECT o.id, o.name, o.status, o.lead_id, o.conversation_id FROM opportunities o WHERE o.lead_id IS NULL OR o.lead_id = 0 ORDER BY o.updated_at DESC LIMIT 10";
$stmt_orphan = $db->prepare($sql_orphan);
$stmt_orphan->execute();
$orphans = $stmt_orphan->fetchAll(PDO::FETCH_ASSOC);

if (empty($orphans)) {
    echo "Nenhuma oportunidade órfã encontrada.\n";
} else {
    foreach ($orphans as $opp) {
        echo "ID: {$opp['id']} | {$opp['name']} | Lead: " . ($opp['lead_id'] ?: 'NULL') . " | Conv: " . ($opp['conversation_id'] ?: 'NULL') . "\n";
    }
}
?>
