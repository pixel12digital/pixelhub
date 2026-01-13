<?php
/**
 * Processa o evento mais recente do ServPro em status queued
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

echo "=== PROCESSAR EVENTO MAIS RECENTE DO SERVPRO ===\n\n";

// Busca evento mais recente em queued
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.tenant_id,
        ce.status,
        ce.payload,
        ce.metadata,
        ce.created_at
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.status = 'queued'
    ORDER BY ce.created_at DESC
    LIMIT 1
");

$stmt->execute();
$event = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Nenhum evento em status 'queued' encontrado.\n";
    exit(1);
}

echo "📋 Evento encontrado:\n";
echo "   event_id: {$event['event_id']}\n";
echo "   created_at: {$event['created_at']}\n\n";

// Verifica se é do ServPro
$payload = json_decode($event['payload'], true);
$from = $payload['from'] ?? $payload['message']['from'] ?? '';

if (strpos($from, '10523374551225@lid') === false && strpos($event['payload'], '554796474223') === false) {
    echo "⚠️  Este evento NÃO é do ServPro (from: {$from})\n";
    echo "   Processando mesmo assim...\n\n";
}

// Processa
$metadata = json_decode($event['metadata'], true);
$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => $event['source_system'],
    'tenant_id' => $event['tenant_id'],
    'payload' => $payload,
    'metadata' => $metadata,
];

echo "🔍 Processando resolveConversation()...\n";
try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "✅ Conversa atualizada: conversation_id={$conversation['id']}\n";
        echo "   last_message_at: {$conversation['last_message_at']}\n";
        echo "   unread_count: {$conversation['unread_count']}\n\n";
        
        // Atualiza status
        $updateStmt = $db->prepare("
            UPDATE communication_events 
            SET status = 'processed', 
                processed_at = NOW(),
                updated_at = NOW()
            WHERE event_id = ?
        ");
        $updateStmt->execute([$event['event_id']]);
        echo "✅ Evento marcado como 'processed'\n";
    } else {
        echo "❌ resolveConversation() retornou NULL\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

