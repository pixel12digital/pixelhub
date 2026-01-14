<?php
/**
 * Processa manualmente um evento que está em status 'queued'
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

echo "=== PROCESSAR EVENTO QUEUED ===\n\n";

// Busca o evento mais recente em status 'queued'
$stmt = $db->query("
    SELECT event_id 
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message' 
    AND status = 'queued'
    AND (
        payload LIKE '%554796474223%'
        OR payload LIKE '%10523374551225@lid%'
        OR payload LIKE '%ServPro%'
    )
    ORDER BY created_at DESC 
    LIMIT 1
");
$latest = $stmt->fetch(PDO::FETCH_ASSOC);
$eventId = $latest['event_id'] ?? '2d917166-7dc4-4dd6-bf35-564d18c8bce4'; // Fallback

// Busca evento
$stmt = $db->prepare("SELECT * FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(\PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado!\n";
    exit(1);
}

echo "📋 Evento: {$event['event_id']}\n";
echo "   Status atual: {$event['status']}\n";
echo "   Event type: {$event['event_type']}\n\n";

// Processa resolveConversation
$payload = json_decode($event['payload'], true);
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
        
        // Atualiza status do evento para 'processed'
        $updateStmt = $db->prepare("
            UPDATE communication_events 
            SET status = 'processed', 
                processed_at = NOW(),
                updated_at = NOW()
            WHERE event_id = ?
        ");
        $updateStmt->execute([$eventId]);
        
        echo "✅ Evento marcado como 'processed'\n\n";
        
        // Verifica conversa no banco
        $checkStmt = $db->prepare("
            SELECT 
                last_message_at,
                unread_count,
                last_message_direction,
                updated_at
            FROM conversations
            WHERE id = ?
        ");
        $checkStmt->execute([$conversation['id']]);
        $updated = $checkStmt->fetch(\PDO::FETCH_ASSOC);
        
        echo "📊 Estado final da conversa:\n";
        echo "   last_message_at: {$updated['last_message_at']}\n";
        echo "   unread_count: {$updated['unread_count']}\n";
        echo "   last_message_direction: {$updated['last_message_direction']}\n";
        echo "   updated_at: {$updated['updated_at']}\n";
    } else {
        echo "❌ resolveConversation() retornou NULL\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

