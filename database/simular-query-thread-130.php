<?php

/**
 * Simula a query exata que getWhatsAppMessagesFromConversation executa para conversation_id=130
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

Env::load();
$db = DB::getConnection();

$conversationId = 130;

echo "=== Simulação: getWhatsAppMessagesFromConversation(130) ===\n\n";

// 1. Dados da conversa
$stmt = $db->prepare("SELECT conversation_key, contact_external_id, remote_key, tenant_id, channel_id FROM conversations WHERE id = ?");
$stmt->execute([$conversationId]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    die("Conversa 130 não existe\n");
}

$contactExternalId = $conv['contact_external_id'];
$tenantId = $conv['tenant_id'];
$sessionId = $conv['channel_id'] ?? '';

echo "Conversa: contact_external_id={$contactExternalId}, tenant_id=" . ($tenantId ?: 'NULL') . ", channel_id=" . ($sessionId ?: 'NULL') . "\n\n";

// 2. Query direta por conversation_id (condição mais simples)
echo "2. Eventos com conversation_id=130:\n";
$stmt = $db->prepare("
    SELECT event_id, event_type, tenant_id, created_at,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text
    FROM communication_events
    WHERE conversation_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$conversationId]);
$byConvId = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Encontrados: " . count($byConvId) . "\n";
foreach ($byConvId as $e) {
    echo "   - {$e['event_id']} | tenant_id={$e['tenant_id']} | {$e['text']} | {$e['created_at']}\n";
}

// 3. Com filtro tenant_id (como a query real quando channel_id é NULL)
echo "\n3. Eventos com conversation_id=130 AND tenant_id=25:\n";
$stmt = $db->prepare("
    SELECT event_id, event_type, tenant_id, created_at
    FROM communication_events
    WHERE conversation_id = ? AND tenant_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$conversationId, $tenantId]);
$withTenant = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "   Encontrados: " . count($withTenant) . "\n";

// 4. Verifica tenant_id dos eventos
echo "\n4. tenant_id dos eventos da conversa 130:\n";
$stmt = $db->prepare("SELECT event_id, tenant_id FROM communication_events WHERE conversation_id = ?");
$stmt->execute([$conversationId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as $e) {
    echo "   - {$e['event_id']}: tenant_id=" . ($e['tenant_id'] ?: 'NULL') . "\n";
}

// 5. getWhatsAppThreadInfo - existe thread para whatsapp_130?
echo "\n5. getWhatsAppThreadInfo(whatsapp_130) - conversa existe?\n";
$stmt = $db->prepare("
    SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name, c.tenant_id, c.channel_id
    FROM conversations c
    WHERE c.id = ?
");
$stmt->execute([$conversationId]);
$thread = $stmt->fetch(PDO::FETCH_ASSOC);
if ($thread) {
    echo "   ✅ Thread existe\n";
    echo "   contact_name: " . ($thread['contact_name'] ?: 'NULL') . " (UI mostra 'Sem nome' quando NULL)\n";
} else {
    echo "   ❌ Thread NÃO existe\n";
}

// 6. Lista de conversas do Inbox - como a conversa 130 aparece na lista?
echo "\n6. Como a conversa 130 aparece na lista do Inbox (threads)?\n";
$stmt = $db->prepare("
    SELECT id, conversation_key, contact_external_id, contact_name, channel_id, last_message_at
    FROM conversations
    WHERE id = 130
");
$stmt->execute();
$c = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($c);

echo "\n=== Fim ===\n";
