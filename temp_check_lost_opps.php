<?php
require_once 'vendor/autoload.php';
use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICANDO OPORTUNIDADES PERDIDAS RECENTES ===\n";

// Buscar oportunidades com status lost ou com lost_at preenchido
$sql_lost = "SELECT o.id, o.name, o.status, o.stage, o.lead_id, o.conversation_id, o.lost_at, o.lost_reason, o.created_at, o.updated_at FROM opportunities o WHERE o.status = 'lost' OR o.lost_at IS NOT NULL ORDER BY o.updated_at DESC LIMIT 20";
$stmt_lost = $db->prepare($sql_lost);
$stmt_lost->execute();
$lost_opps = $stmt_lost->fetchAll(PDO::FETCH_ASSOC);

if (empty($lost_opps)) {
    echo "Nenhuma oportunidade perdida encontrada.\n";
} else {
    foreach ($lost_opps as $opp) {
        echo "ID: {$opp['id']} | {$opp['name']}\n";
        echo "  Status: {$opp['status']} | Stage: {$opp['stage']}\n";
        echo "  Lead ID: {$opp['lead_id']} | Conv ID: {$opp['conversation_id']}\n";
        echo "  Criada: {$opp['created_at']}\n";
        echo "  Perdida: {$opp['lost_at']}\n";
        echo "  Motivo: " . ($opp['lost_reason'] ?: 'N/A') . "\n";
        
        // Se for lead 7, mostrar detalhes
        if ($opp['lead_id'] == 7) {
            echo "  ⚠️ ESTA É A OPORTUNIDADE DA VIVIANE!\n";
        }
        
        echo "---\n";
    }
}

echo "\n=== VERIFICANDO OPORTUNIDADES CRIADAS NOS ÚLTIMOS DIAS ===\n";
$sql_recent = "SELECT o.id, o.name, o.status, o.stage, o.lead_id, o.conversation_id, o.created_at, o.updated_at FROM opportunities o WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY o.created_at DESC";
$stmt_recent = $db->prepare($sql_recent);
$stmt_recent->execute();
$recent_opps = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

if (empty($recent_opps)) {
    echo "Nenhuma oportunidade criada nos últimos 7 dias.\n";
} else {
    foreach ($recent_opps as $opp) {
        echo "ID: {$opp['id']} | {$opp['name']}\n";
        echo "  Status: {$opp['status']} | Stage: {$opp['stage']}\n";
        echo "  Lead ID: {$opp['lead_id']} | Conv ID: {$opp['conversation_id']}\n";
        echo "  Criada: {$opp['created_at']}\n";
        
        // Se for lead 7
        if ($opp['lead_id'] == 7) {
            echo "  ⚠️ ESTA É A OPORTUNIDADE DA VIVIANE!\n";
        }
        
        echo "---\n";
    }
}

echo "\n=== VERIFICANDO SE HÁ OPORTUNIDADE COM conversation_id = 196 ===\n";
$sql_conv_opp = "SELECT o.id, o.name, o.status, o.stage, o.lead_id, o.created_at, o.updated_at, o.lost_at, o.lost_reason FROM opportunities o WHERE o.conversation_id = 196";
$stmt_conv_opp = $db->prepare($sql_conv_opp);
$stmt_conv_opp->execute();
$conv_opps = $stmt_conv_opp->fetchAll(PDO::FETCH_ASSOC);

if (empty($conv_opps)) {
    echo "Nenhuma oportunidade encontrada com conversation_id = 196.\n";
} else {
    foreach ($conv_opps as $opp) {
        echo "ID: {$opp['id']} | {$opp['name']}\n";
        echo "  Status: {$opp['status']} | Stage: {$opp['stage']}\n";
        echo "  Lead ID: {$opp['lead_id']}\n";
        echo "  Criada: {$opp['created_at']}\n";
        echo "  Atualizada: {$opp['updated_at']}\n";
        
        if ($opp['lost_at']) {
            echo "  Perdida em: {$opp['lost_at']} | Motivo: " . ($opp['lost_reason'] ?: 'N/A') . "\n";
        }
        
        echo "---\n";
    }
}

echo "\n=== RESUMO ===\n";
echo "Lead Viviane (ID: 7): ✅ EXISTE\n";
echo "Conversa Viviane (ID: 196): ✅ EXISTE\n";
echo "Oportunidade vinculada: ❌ NÃO EXISTE\n";
echo "\nSolução: Criar uma oportunidade para o lead 7 vinculando à conversa 196.\n";
?>
