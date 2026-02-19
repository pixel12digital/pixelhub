<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

// Verificar estrutura da tabela opportunities
$stmt = $db->prepare('DESCRIBE opportunities');
$stmt->execute();
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== ESTRUTURA DA TABELA OPPORTUNITIES ===\n";
foreach ($columns as $col) {
    echo "- {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== BUSCANDO VIVIANE PELO TELEFONE ===\n";

// Buscar conversas primeiro
$phone = '+5519983711169';
$sql_conv = "SELECT c.id, c.conversation_key, c.contact_name, c.contact_external_id, c.tenant_id, c.created_at, c.last_message_at FROM conversations c WHERE c.contact_external_id LIKE '%$phone' OR c.contact_external_id LIKE '%19983711169' ORDER BY c.last_message_at DESC";
$stmt_conv = $db->prepare($sql_conv);
$stmt_conv->execute();
$conversations = $stmt_conv->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "Conversa ID: {$conv['id']}\n";
    echo "Contato: {$conv['contact_name']}\n";
    echo "External ID: {$conv['contact_external_id']}\n";
    
    // Verificar vinculo com oportunidade
    $sql_link = "SELECT opportunity_id FROM conversation_opportunity_links WHERE conversation_id = ?";
    $stmt_link = $db->prepare($sql_link);
    $stmt_link->execute([$conv['id']]);
    $link = $stmt_link->fetch(PDO::FETCH_ASSOC);
    
    if ($link) {
        echo "Opportunity ID vinculado: {$link['opportunity_id']}\n";
        
        // Buscar oportunidade com campos corretos
        $sql_opp = "SELECT id, name, status, deleted_at FROM opportunities WHERE id = ?";
        $stmt_opp = $db->prepare($sql_opp);
        $stmt_opp->execute([$link['opportunity_id']]);
        $opp = $stmt_opp->fetch(PDO::FETCH_ASSOC);
        
        if ($opp) {
            echo "Status: {$opp['status']}\n";
            echo "Nome: {$opp['name']}\n";
        } else {
            echo "❌ OPORTUNIDADE NÃO ENCONTRADA!\n";
        }
    }
    echo "---\n";
}

echo "\n=== BUSCANDO OPORTUNIDADES COM NOME VIVIANE ===\n";
$sql_viviane = "SELECT id, name, status, deleted_at FROM opportunities WHERE name LIKE '%Viviane%' ORDER BY updated_at DESC";
$stmt_viviane = $db->prepare($sql_viviane);
$stmt_viviane->execute();
$viviane_opps = $stmt_viviane->fetchAll(PDO::FETCH_ASSOC);

if (empty($viviane_opps)) {
    echo "Nenhuma oportunidade com nome Viviane encontrada.\n";
} else {
    foreach ($viviane_opps as $opp) {
        echo "ID: {$opp['id']} | Nome: {$opp['name']} | Status: {$opp['status']}\n";
        if ($opp['deleted_at']) echo "EXCLUIDA: {$opp['deleted_at']}\n";
    }
}

echo "\n=== VERIFICANDO SE HÁ CAMPO DELETED_AT ===\n";
$sql_check_deleted = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'opportunities' AND COLUMN_NAME = 'deleted_at'";
$stmt_check = $db->prepare($sql_check_deleted);
$stmt_check->execute();
$deleted_col = $stmt_check->fetch(PDO::FETCH_ASSOC);

if ($deleted_col) {
    echo "Campo deleted_at existe.\n";
} else {
    echo "Campo deleted_at NÃO existe.\n";
}
?>
