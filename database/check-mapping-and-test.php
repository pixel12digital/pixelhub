<?php
/**
 * Verifica mapeamento e testa processamento do evento mais recente
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

echo "=== VERIFICAÇÃO: Mapeamento e Evento ===\n\n";

// 1. Verifica mapeamento
echo "1️⃣  MAPEAMENTO WHATSAPP_BUSINESS_IDS:\n";
$stmt = $db->query("SELECT * FROM whatsapp_business_ids");
$mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($mappings)) {
    echo "   ❌ Nenhum mapeamento encontrado!\n";
} else {
    foreach ($mappings as $mapping) {
        echo "   ✅ business_id: {$mapping['business_id']}\n";
        echo "      phone_number: {$mapping['phone_number']}\n";
        echo "      tenant_id: " . ($mapping['tenant_id'] ?: 'NULL') . "\n\n";
    }
}

// 2. Busca evento mais recente
echo "2️⃣  EVENTO MAIS RECENTE:\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.tenant_id,
        ce.status,
        ce.payload,
        ce.metadata,
        ce.created_at,
        ce.processed_at
    FROM communication_events ce
    WHERE ce.event_id = '2d917166-7dc4-4dd6-bf35-564d18c8bce4'
    LIMIT 1
");

$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "   ❌ Evento não encontrado!\n";
    exit(1);
}

echo "   event_id: {$event['event_id']}\n";
echo "   event_type: {$event['event_type']}\n";
echo "   status: {$event['status']}\n";
echo "   created_at: {$event['created_at']}\n";
echo "   processed_at: " . ($event['processed_at'] ?: 'NULL') . "\n\n";

// 3. Testa resolveConversation diretamente
echo "3️⃣  TESTE: resolveConversation() direto\n";
$payload = json_decode($event['payload'], true);
$metadata = json_decode($event['metadata'], true);

$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => $event['source_system'],
    'tenant_id' => $event['tenant_id'],
    'payload' => $payload,
    'metadata' => $metadata,
];

// Extrai from
$from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
echo "   from (payload): {$from}\n\n";

try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "   ✅ resolveConversation() RETORNOU conversa:\n";
        echo "      conversation_id: {$conversation['id']}\n";
        echo "      last_message_at: {$conversation['last_message_at']}\n";
        echo "      unread_count: {$conversation['unread_count']}\n";
        echo "      last_message_direction: {$conversation['last_message_direction']}\n\n";
        
        // Verifica se atualizou no banco
        echo "4️⃣  VERIFICAÇÃO NO BANCO (após resolveConversation):\n";
        $stmt = $db->prepare("
            SELECT 
                last_message_at,
                unread_count,
                last_message_direction,
                updated_at
            FROM conversations
            WHERE id = ?
        ");
        $stmt->execute([$conversation['id']]);
        $updated = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($updated) {
            echo "   last_message_at: {$updated['last_message_at']}\n";
            echo "   unread_count: {$updated['unread_count']}\n";
            echo "   last_message_direction: {$updated['last_message_direction']}\n";
            echo "   updated_at: {$updated['updated_at']}\n";
        }
    } else {
        echo "   ❌ resolveConversation() retornou NULL\n";
    }
} catch (\Exception $e) {
    echo "   ❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

