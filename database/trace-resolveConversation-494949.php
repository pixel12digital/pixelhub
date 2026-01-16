<?php

/**
 * Script para rastrear passo a passo o resolveConversation
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/PhoneNormalizer.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== RASTREAMENTO COMPLETO resolveConversation ===\n\n";

// Busca evento
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        payload,
        metadata
    FROM communication_events
    WHERE JSON_EXTRACT(payload, '$.message.text') LIKE '%494949%'
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch();

if (!$event) {
    echo "Evento não encontrado!\n";
    exit(1);
}

$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => 'wpp_gateway',
    'tenant_id' => $event['tenant_id'],
    'payload' => json_decode($event['payload'], true),
    'metadata' => json_decode($event['metadata'] ?? '{}', true)
];

echo "1. Verificando se é evento de mensagem...\n";
$reflection = new ReflectionClass(\PixelHub\Services\ConversationService::class);
$isMessageEventMethod = $reflection->getMethod('isMessageEvent');
$isMessageEventMethod->setAccessible(true);
$isMessage = $isMessageEventMethod->invoke(null, $eventData['event_type']);
echo "   -> " . ($isMessage ? 'SIM' : 'NÃO') . "\n\n";

if (!$isMessage) {
    echo "Não é evento de mensagem, abortando.\n";
    exit(1);
}

echo "2. Extraindo informações do canal...\n";
$extractMethod = $reflection->getMethod('extractChannelInfo');
$extractMethod->setAccessible(true);
$channelInfo = $extractMethod->invoke(null, $eventData);

if (!$channelInfo) {
    echo "   -> ERRO: extractChannelInfo retornou NULL\n";
    exit(1);
}

echo "   -> SUCESSO\n";
echo "   channel_type: {$channelInfo['channel_type']}\n";
echo "   contact_external_id: {$channelInfo['contact_external_id']}\n";
echo "   channel_id: " . ($channelInfo['channel_id'] ?? 'NULL') . "\n";
echo "   channel_account_id: " . ($channelInfo['channel_account_id'] ?? 'NULL') . "\n";
echo "\n";

echo "3. Gerando conversation_key...\n";
$generateKeyMethod = $reflection->getMethod('generateConversationKey');
$generateKeyMethod->setAccessible(true);
$conversationKey = $generateKeyMethod->invoke(null, 
    $channelInfo['channel_type'],
    $channelInfo['channel_account_id'],
    $channelInfo['contact_external_id']
);
echo "   -> conversation_key: {$conversationKey}\n\n";

echo "4. Buscando conversa existente por chave...\n";
$findByKeyMethod = $reflection->getMethod('findByKey');
$findByKeyMethod->setAccessible(true);
$existing = $findByKeyMethod->invoke(null, $conversationKey);
if ($existing) {
    echo "   -> ENCONTRADA: ID {$existing['id']}\n";
    exit(0);
}
echo "   -> NÃO ENCONTRADA\n\n";

echo "5. Buscando conversa equivalente...\n";
$findEquivalentMethod = $reflection->getMethod('findEquivalentConversation');
$findEquivalentMethod->setAccessible(true);
$equivalent = $findEquivalentMethod->invoke(null, $channelInfo, $channelInfo['contact_external_id']);
if ($equivalent) {
    echo "   -> ENCONTRADA: ID {$equivalent['id']}\n";
    exit(0);
}
echo "   -> NÃO ENCONTRADA\n\n";

echo "6. Buscando conversa por contato apenas...\n";
$findByContactMethod = $reflection->getMethod('findConversationByContactOnly');
$findByContactMethod->setAccessible(true);
$byContact = $findByContactMethod->invoke(null, $channelInfo);
if ($byContact) {
    echo "   -> ENCONTRADA: ID {$byContact['id']}\n";
    exit(0);
}
echo "   -> NÃO ENCONTRADA\n\n";

echo "7. Criando nova conversa...\n";
$createMethod = $reflection->getMethod('createConversation');
$createMethod->setAccessible(true);
$newConversation = $createMethod->invoke(null, $conversationKey, $eventData, $channelInfo);

if ($newConversation) {
    echo "   -> SUCESSO: ID {$newConversation['id']}\n";
    echo "   conversation_key: {$newConversation['conversation_key']}\n";
    echo "   contact_external_id: {$newConversation['contact_external_id']}\n";
    echo "   tenant_id: " . ($newConversation['tenant_id'] ?? 'NULL') . "\n";
    echo "   is_incoming_lead: " . ($newConversation['is_incoming_lead'] ?? 0) . "\n";
} else {
    echo "   -> ERRO: createConversation retornou NULL\n";
    echo "   Verifique os logs de erro.\n";
}

