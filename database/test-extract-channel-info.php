<?php
/**
 * Testa extractChannelInfo diretamente
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

echo "=== TESTE: extractChannelInfo() ===\n\n";

// Busca o evento
$stmt = $db->prepare("
    SELECT 
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

$payload = json_decode($event['payload'], true);
$metadata = json_decode($event['metadata'], true);

$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => $event['source_system'],
    'tenant_id' => $event['tenant_id'],
    'payload' => $payload,
    'metadata' => $metadata,
];

echo "📋 PAYLOAD:\n";
echo "   from: " . ($payload['from'] ?? $payload['message']['from'] ?? 'N/A') . "\n";
echo "   to: " . ($payload['to'] ?? $payload['message']['to'] ?? 'N/A') . "\n";
echo "   message.from: " . ($payload['message']['from'] ?? 'N/A') . "\n";
echo "   message.to: " . ($payload['message']['to'] ?? 'N/A') . "\n\n";

// Usa reflection para chamar método privado
$reflection = new ReflectionClass('PixelHub\Services\ConversationService');
$method = $reflection->getMethod('extractChannelInfo');
$method->setAccessible(true);

echo "🔍 CHAMANDO extractChannelInfo()...\n\n";

try {
    $channelInfo = $method->invoke(null, $eventData);
    
    if ($channelInfo) {
        echo "✅ SUCESSO: ChannelInfo extraído\n";
        echo "   channel_type: {$channelInfo['channel_type']}\n";
        echo "   contact_external_id: {$channelInfo['contact_external_id']}\n";
        echo "   direction: {$channelInfo['direction']}\n";
        echo "   channel_account_id: " . ($channelInfo['channel_account_id'] ?? 'NULL') . "\n";
        echo "   channel_id: " . ($channelInfo['channel_id'] ?? 'NULL') . "\n";
    } else {
        echo "❌ extractChannelInfo() retornou NULL\n";
    }
} catch (\Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

