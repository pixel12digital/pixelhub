<?php

/**
 * Teste: Simula inbound do Charles para verificar se atualiza conversa existente
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\ConversationService;

Env::load();

echo "=== TESTE: INBOUND CHARLES (VERIFICAR ATUALIZAÇÃO) ===\n\n";

$db = DB::getConnection();

// Verifica conversas antes
echo "1. Conversas do Charles ANTES do teste:\n";
$stmt = $db->query("
    SELECT id, conversation_key, last_message_at, unread_count, message_count, channel_account_id
    FROM conversations 
    WHERE contact_external_id = '554796164699' 
    ORDER BY last_message_at DESC
");
$before = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($before as $c) {
    echo "   - ID: {$c['id']}, Key: {$c['conversation_key']}, Last: {$c['last_message_at']}, Unread: {$c['unread_count']}, Channel Account: " . ($c['channel_account_id'] ?: 'NULL') . "\n";
}

echo "\n";

// Simula inbound do Charles
echo "2. Simulando inbound do Charles...\n";
$payload = [
    'event' => 'message',
    'session' => [
        'id' => 'Pixel12 Digital'
    ],
    'from' => '554796164699@c.us',
    'message' => [
        'id' => 'test_charles_' . time(),
        'from' => '554796164699@c.us',
        'text' => 'TESTE INBOUND CHARLES ' . date('H:i:s'),
        'notifyName' => 'Charles Dietrich',
        'timestamp' => time()
    ],
    'timestamp' => time()
];

$eventType = 'message';
$channelId = 'Pixel12 Digital';
$tenantId = 2; // Resolvido pelo channel_id

$internalEventType = 'whatsapp.inbound.message';

$eventId = EventIngestionService::ingest([
    'event_type' => $internalEventType,
    'source_system' => 'wpp_gateway',
    'payload' => $payload,
    'tenant_id' => $tenantId,
    'metadata' => [
        'channel_id' => $channelId,
        'raw_event_type' => $eventType
    ]
]);

echo "   ✅ Evento criado: {$eventId}\n\n";

// Resolve conversa
$conversation = ConversationService::resolveConversation([
    'event_type' => $internalEventType,
    'source_system' => 'wpp_gateway',
    'tenant_id' => $tenantId,
    'payload' => $payload,
    'metadata' => [
        'channel_id' => $channelId,
        'raw_event_type' => $eventType
    ]
]);

if ($conversation) {
    echo "   ✅ Conversa resolvida:\n";
    echo "      - ID: {$conversation['id']}\n";
    echo "      - Key: {$conversation['conversation_key']}\n";
    echo "      - Last Message: {$conversation['last_message_at']}\n";
    echo "      - Unread: {$conversation['unread_count']}\n";
} else {
    echo "   ❌ Conversa NÃO foi resolvida!\n";
}

echo "\n";

// Verifica conversas depois
echo "3. Conversas do Charles DEPOIS do teste:\n";
$stmt = $db->query("
    SELECT id, conversation_key, last_message_at, unread_count, message_count, channel_account_id
    FROM conversations 
    WHERE contact_external_id = '554796164699' 
    ORDER BY last_message_at DESC
");
$after = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($after as $c) {
    echo "   - ID: {$c['id']}, Key: {$c['conversation_key']}, Last: {$c['last_message_at']}, Unread: {$c['unread_count']}, Channel Account: " . ($c['channel_account_id'] ?: 'NULL') . "\n";
}

echo "\n";

// Compara
if (count($before) > count($after)) {
    echo "✅ Conversa foi mesclada/atualizada (menos conversas agora)\n";
} elseif (count($before) === count($after)) {
    $updated = false;
    foreach ($after as $cAfter) {
        foreach ($before as $cBefore) {
            if ($cAfter['id'] === $cBefore['id']) {
                if ($cAfter['last_message_at'] !== $cBefore['last_message_at'] || 
                    $cAfter['unread_count'] > $cBefore['unread_count']) {
                    $updated = true;
                    break 2;
                }
            }
        }
    }
    if ($updated) {
        echo "✅ Conversa existente foi atualizada\n";
    } else {
        echo "⚠️  Nenhuma conversa foi atualizada\n";
    }
} else {
    echo "❌ Nova conversa foi criada (duplicata!)\n";
}

echo "\n";

