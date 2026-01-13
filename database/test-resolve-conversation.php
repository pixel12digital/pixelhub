<?php
/**
 * Testa diretamente o resolveConversation com o payload do evento
 */

// Autoloader simples
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

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== TESTE DIRETO: resolveConversation() ===\n\n";

// Busca o evento mais recente
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.tenant_id,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_id = '9d9c1322-0da8-4259-a689-e7371071c934'
    LIMIT 1
");

$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado.\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);
$metadata = json_decode($event['metadata'], true);

echo "📋 PREPARANDO DADOS:\n";
echo "   event_type: {$event['event_type']}\n";
echo "   source_system: {$event['source_system']}\n";
echo "   tenant_id: {$event['tenant_id']}\n";
echo "   from (payload): " . ($payload['from'] ?? $payload['message']['from'] ?? 'N/A') . "\n\n";

// Prepara dados para resolveConversation
$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => $event['source_system'],
    'tenant_id' => $event['tenant_id'],
    'payload' => $payload,
    'metadata' => $metadata,
];

echo "🔍 CHAMANDO resolveConversation()...\n\n";

try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "✅ SUCESSO: Conversa encontrada/criada\n";
        echo "   conversation_id: {$conversation['id']}\n";
        echo "   conversation_key: {$conversation['conversation_key']}\n";
        echo "   last_message_at: {$conversation['last_message_at']}\n";
        echo "   unread_count: {$conversation['unread_count']}\n";
    } else {
        echo "❌ resolveConversation() retornou NULL\n";
    }
} catch (\Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n📋 VERIFICANDO CONVERSA DO SERVPRO NO BANCO:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        last_message_at,
        unread_count,
        updated_at
    FROM conversations
    WHERE id = 34
    LIMIT 1
");
$stmt->execute();
$conv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conv) {
    echo "   conversation_id: {$conv['id']}\n";
    echo "   last_message_at: {$conv['last_message_at']}\n";
    echo "   unread_count: {$conv['unread_count']}\n";
    echo "   updated_at: {$conv['updated_at']}\n";
} else {
    echo "   ❌ Conversa não encontrada\n";
}

