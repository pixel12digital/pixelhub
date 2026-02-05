<?php
/**
 * Corrige conversa 140: contact_external_id errado (5511940863773 -> 208989199560861@lid)
 * Evento teste1310: from=208989199560861@lid (Charles 47), conversa tinha 5511940863773 (11)
 * 
 * Uso: php database/fix-conversation-140-charles-identidade.php
 */
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
\PixelHub\Core\Env::load(__DIR__ . '/../.env');
$db = \PixelHub\Core\DB::getConnection();

$conversationId = 140;
$correctContactId = '208989199560861@lid';  // from do payload teste1310
$correctPhone = '5547996164699';             // Charles 47 - E.164

echo "=== Correção identidade conversa 140 (Charles Dietrich) ===\n\n";

$stmt = $db->prepare("SELECT id, contact_external_id, contact_name, channel_id FROM conversations WHERE id = ?");
$stmt->execute([$conversationId]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo "Conversa 140 não encontrada.\n";
    exit(1);
}

echo "Antes: contact_external_id={$conv['contact_external_id']}\n";

$upd = $db->prepare("UPDATE conversations SET contact_external_id = ?, updated_at = NOW() WHERE id = ?");
$upd->execute([$correctContactId, $conversationId]);
echo "Atualizado: contact_external_id={$correctContactId}\n\n";

// Garante mapeamento @lid -> phone para exibição (47) 99616-4699
$check = $db->prepare("SELECT 1 FROM whatsapp_business_ids WHERE business_id = ?");
$check->execute([$correctContactId]);
if (!$check->fetch()) {
    $ins = $db->prepare("INSERT INTO whatsapp_business_ids (business_id, phone_number, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE phone_number = VALUES(phone_number)");
    $ins->execute([$correctContactId, $correctPhone]);
    echo "Mapeamento criado: {$correctContactId} -> {$correctPhone}\n";
} else {
    echo "Mapeamento já existe.\n";
}

echo "\nConcluído. No Inbox, o card deve exibir (47) 99616-4699.\n";
