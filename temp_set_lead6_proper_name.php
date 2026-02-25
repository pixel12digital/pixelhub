<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== CONFIGURANDO NOME ADEQUADO PARA LEAD #6 ===\n\n";

// O Lead #6 tem uma oportunidade "E-commerce" vinculada
// Vamos usar um nome descritivo baseado nisso

$leadName = "Lead E-commerce 5239";

// 1. Atualiza o Lead #6
$updateStmt = $db->prepare("UPDATE leads SET name = ? WHERE id = 6");
$updateStmt->execute([$leadName]);

echo "✅ Lead #6 atualizado com nome: {$leadName}\n\n";

// 2. Atualiza a conversa vinculada
$updateConvStmt = $db->prepare("
    UPDATE conversations 
    SET contact_name = ? 
    WHERE lead_id = 6
");
$updateConvStmt->execute([$leadName]);

echo "✅ Conversa vinculada atualizada com contact_name: {$leadName}\n\n";

// 3. Verifica resultado
$stmt = $db->prepare("
    SELECT 
        l.id,
        l.name,
        l.phone,
        c.id as conv_id,
        c.contact_name,
        o.name as opp_name,
        o.stage as opp_stage
    FROM leads l
    LEFT JOIN conversations c ON l.id = c.lead_id
    LEFT JOIN opportunities o ON l.id = o.lead_id
    WHERE l.id = 6
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "RESULTADO FINAL:\n";
echo "  Lead ID: {$result['id']}\n";
echo "  Lead Name: {$result['name']}\n";
echo "  Lead Phone: {$result['phone']}\n";
echo "  Conversation contact_name: {$result['contact_name']}\n";
echo "  Oportunidade: {$result['opp_name']} ({$result['opp_stage']})\n\n";

echo "✅ CONFIGURAÇÃO CONCLUÍDA!\n\n";
echo "RESULTADO:\n";
echo "- Kanban: Lead aparecerá como '{$leadName}'\n";
echo "- Inbox: Conversa aparecerá como '{$leadName}'\n";
echo "- Você pode editar o nome em /leads/edit?id=6 se quiser personalizá-lo\n";
