<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== RESTAURANDO NOME DO LEAD #6 ===\n\n";

// 1. Verifica estado atual
$stmt = $db->prepare("SELECT id, name, phone FROM leads WHERE id = 6");
$stmt->execute();
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Estado ATUAL do Lead #6:\n";
echo "  Nome: " . ($lead['name'] ?: 'NULL') . "\n";
echo "  Telefone: {$lead['phone']}\n\n";

// 2. Verifica se há oportunidade vinculada que possa ter um nome
$stmt = $db->prepare("
    SELECT id, name, stage 
    FROM opportunities 
    WHERE lead_id = 6
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute();
$opportunity = $stmt->fetch(PDO::FETCH_ASSOC);

if ($opportunity) {
    echo "Oportunidade vinculada:\n";
    echo "  ID: {$opportunity['id']}\n";
    echo "  Nome: " . ($opportunity['name'] ?: 'NULL') . "\n";
    echo "  Stage: {$opportunity['stage']}\n\n";
}

// 3. Limpa o nome do Lead #6 para NULL (volta ao estado original)
echo "AÇÃO: Removendo nome temporário do Lead #6\n";
echo "Isso fará o Lead #6 aparecer como 'Lead #6' no Kanban\n\n";

$updateStmt = $db->prepare("UPDATE leads SET name = NULL WHERE id = 6");
$updateStmt->execute();

echo "✅ Nome do Lead #6 removido (agora NULL)\n\n";

// 4. Remove contact_name da conversa também
$updateConvStmt = $db->prepare("
    UPDATE conversations 
    SET contact_name = NULL 
    WHERE lead_id = 6
");
$updateConvStmt->execute();

echo "✅ contact_name da conversa também removido\n\n";

// 5. Verifica resultado
$stmt = $db->prepare("
    SELECT 
        l.id,
        l.name,
        l.phone,
        c.id as conv_id,
        c.contact_name
    FROM leads l
    LEFT JOIN conversations c ON l.id = c.lead_id
    WHERE l.id = 6
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "RESULTADO FINAL:\n";
echo "  Lead ID: {$result['id']}\n";
echo "  Lead Name: " . ($result['name'] ?: 'NULL') . "\n";
echo "  Lead Phone: {$result['phone']}\n";
echo "  Conversation contact_name: " . ($result['contact_name'] ?: 'NULL') . "\n\n";

echo "✅ RESTAURAÇÃO CONCLUÍDA!\n\n";
echo "IMPORTANTE:\n";
echo "- No Kanban, o Lead #6 aparecerá como 'Lead #6'\n";
echo "- No Inbox, a conversa aparecerá como 'Contato Desconhecido' até você cadastrar um nome\n";
echo "- Para adicionar um nome real, edite o Lead em: /leads/edit?id=6\n";
