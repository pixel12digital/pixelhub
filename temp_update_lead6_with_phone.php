<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== ATUALIZAÇÃO DO LEAD #6 ===\n\n";

// Como o Lead #6 não tem nome (contato nunca respondeu), vamos usar o telefone como identificador
$leadName = "Lead +55 11 96012-5239";

// 1. Atualiza o Lead #6
$updateLeadStmt = $db->prepare("UPDATE leads SET name = ? WHERE id = 6");
$updateLeadStmt->execute([$leadName]);

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
        l.id as lead_id,
        l.name as lead_name,
        l.phone as lead_phone,
        c.id as conversation_id,
        c.contact_name,
        c.contact_external_id
    FROM leads l
    LEFT JOIN conversations c ON l.id = c.lead_id
    WHERE l.id = 6
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "RESULTADO FINAL:\n";
echo "  Lead ID: {$result['lead_id']}\n";
echo "  Lead Name: {$result['lead_name']}\n";
echo "  Lead Phone: {$result['lead_phone']}\n";
echo "  Conversation ID: {$result['conversation_id']}\n";
echo "  Contact Name: {$result['contact_name']}\n";
echo "  Contact External ID: {$result['contact_external_id']}\n\n";

echo "✅ CORREÇÃO CONCLUÍDA!\n";
echo "A conversa agora deve aparecer como '{$leadName}' no Inbox\n";
echo "\n💡 Você pode editar o nome do Lead pela interface em: /leads/edit?id=6\n";
