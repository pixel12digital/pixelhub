<?php
/**
 * Verificar payload real dos eventos com mídia
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

Env::load();

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE PAYLOAD DE MÍDIA ===\n\n";

// Buscar eventos recentes com mídia
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.tenant_id,
        ce.payload,
        ce.metadata,
        cm.id AS media_id,
        cm.media_id AS cm_media_id,
        cm.stored_path,
        ce.created_at
    FROM communication_events ce
    INNER JOIN communication_media cm ON cm.event_id = ce.event_id
    WHERE ce.source_system = 'wpp_gateway'
        AND ce.event_type = 'whatsapp.inbound.message'
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
    ORDER BY ce.created_at DESC
    LIMIT 3
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento com mídia encontrado.\n";
    exit(1);
}

foreach ($events as $index => $event) {
    echo "=== EVENTO " . ($index + 1) . " ===\n";
    echo "Event ID: {$event['event_id']}\n";
    echo "Media ID (cm.media_id): {$event['cm_media_id']}\n";
    echo "Stored Path: {$event['stored_path']}\n";
    echo "Created: {$event['created_at']}\n\n";
    
    $payload = json_decode($event['payload'], true);
    $metadata = json_decode($event['metadata'] ?? '{}', true);
    
    echo "PAYLOAD (campos relevantes):\n";
    
    // Verificar diferentes locais onde mediaId pode estar
    $possibleMediaId = [
        'payload.mediaId' => $payload['mediaId'] ?? null,
        'payload.media_id' => $payload['media_id'] ?? null,
        'payload.mediaUrl' => $payload['mediaUrl'] ?? null,
        'payload.media_url' => $payload['media_url'] ?? null,
        'payload.message.mediaId' => $payload['message']['mediaId'] ?? null,
        'payload.message.media_id' => $payload['message']['media_id'] ?? null,
        'payload.message.mediaUrl' => $payload['message']['mediaUrl'] ?? null,
        'payload.message.media_url' => $payload['message']['media_url'] ?? null,
        'payload.message.id' => $payload['message']['id'] ?? null,
        'payload.id' => $payload['id'] ?? null,
        'payload.messageId' => $payload['messageId'] ?? null,
        'metadata.media_id' => $metadata['media_id'] ?? null,
    ];
    
    foreach ($possibleMediaId as $key => $value) {
        if ($value) {
            echo "  ✅ {$key}: {$value}\n";
        }
    }
    
    // Verificar tipo de mídia
    $type = $payload['type'] ?? $payload['message']['type'] ?? null;
    if ($type) {
        echo "  ✅ Tipo: {$type}\n";
    }
    
    // Verificar se tem base64 no text
    $text = $payload['text'] ?? $payload['message']['text'] ?? null;
    if ($text && strlen($text) > 100) {
        echo "  ✅ Text (primeiros 50 chars): " . substr($text, 0, 50) . "...\n";
        if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', substr($text, 0, 100))) {
            echo "    ⚠️  Parece ser base64!\n";
        }
    }
    
    echo "\n";
}

echo "=== FIM DA VERIFICAÇÃO ===\n";









