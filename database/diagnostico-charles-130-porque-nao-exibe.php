<?php

/**
 * Por que a mensagem "Teste de duplicação" (conversation_id=130) não exibe no Inbox?
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$conversationId = 130;

echo "=== Diagnóstico: Conversation 130 - Charles Dietrich - por que não exibe? ===\n\n";

// 1. Dados da conversa 130
echo "1. Conversa id=130:\n";
$stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->execute([$conversationId]);
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conv) {
    echo "   ❌ Conversa 130 NÃO EXISTE\n";
    exit(1);
}

echo "   id: {$conv['id']}\n";
echo "   conversation_key: " . ($conv['conversation_key'] ?: 'NULL') . "\n";
echo "   contact_external_id: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
echo "   contact_name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
echo "   remote_key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
echo "   tenant_id: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
echo "   channel_id: " . ($conv['channel_id'] ?: 'NULL') . "\n";
echo "   channel_type: " . ($conv['channel_type'] ?: 'NULL') . "\n";
echo "   last_message_at: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
echo "   created_at: {$conv['created_at']}\n\n";

// 2. thread_id que o frontend usaria (whatsapp_{id})
$threadId = 'whatsapp_' . $conversationId;
echo "2. thread_id esperado pelo Inbox: {$threadId}\n\n";

// 3. Eventos da conversa 130
echo "3. Eventos em communication_events com conversation_id=130:\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.status,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text
    FROM communication_events ce
    WHERE ce.conversation_id = ?
    ORDER BY ce.created_at ASC
");
$stmt->execute([$conversationId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Total: " . count($events) . " evento(s)\n";
foreach ($events as $e) {
    echo "   - {$e['event_id']} | {$e['event_type']} | {$e['source_system']} | " . substr($e['text'] ?: '', 0, 50) . " | {$e['created_at']}\n";
}

// 4. Simula getWhatsAppMessagesFromConversation - verifica filtros
echo "\n4. Verificação de filtros (contact_external_id vs payload):\n";
$contactExt = $conv['contact_external_id'];
echo "   contact_external_id da conversa: " . ($contactExt ?: 'NULL') . "\n";

// Padrões que o backend pode usar para match
$patterns = [];
if ($contactExt) {
    $patterns[] = $contactExt;
    $patterns[] = preg_replace('/@.*$/', '', $contactExt); // sem @c.us
    $patterns[] = preg_replace('/\D/', '', $contactExt);   // só dígitos
}
echo "   Padrões de match: " . implode(', ', $patterns) . "\n";

// 5. Verifica se há inconsistência no contact_external_id
echo "\n5. Outras conversas com mesmo número (554796164699 / 4796164699):\n";
$stmt = $db->prepare("
    SELECT id, conversation_key, contact_external_id, contact_name, channel_id, last_message_at
    FROM conversations
    WHERE contact_external_id LIKE '%554796164699%'
       OR contact_external_id LIKE '%4796164699%'
       OR contact_external_id LIKE '%996164699%'
       OR contact_name LIKE '%Charles%'
    ORDER BY last_message_at DESC
");
$stmt->execute();
$others = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($others as $o) {
    echo "   - id={$o['id']} | key={$o['conversation_key']} | ext_id=" . ($o['contact_external_id'] ?: 'NULL') . " | name=" . ($o['contact_name'] ?: 'NULL') . "\n";
}

// 6. Canal pixel12digital - normalização
echo "\n6. tenant_message_channels para pixel12digital:\n";
$stmt = $db->prepare("
    SELECT id, tenant_id, channel_id, session_id 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway' 
    AND (channel_id LIKE '%pixel12%' OR session_id LIKE '%pixel12%')
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($channels as $ch) {
    echo "   - tenant_id={$ch['tenant_id']} | channel_id=" . ($ch['channel_id'] ?: 'NULL') . " | session_id=" . ($ch['session_id'] ?: 'NULL') . "\n";
}

echo "\n=== Fim ===\n";
