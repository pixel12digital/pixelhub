<?php
/**
 * Script para debugar mensagens retornadas pela thread
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$phone = '5511965221349';
$normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

echo "========================================\n";
echo "DEBUG THREAD MENSAGENS\n";
echo "Número: {$phone}\n";
echo "========================================\n\n";

$db = DB::getConnection();

// Busca conversa
$sql = "SELECT * FROM conversations WHERE contact_external_id LIKE ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute(["%{$normalizedPhone}%"]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    echo "❌ Conversa não encontrada\n";
    exit(1);
}

echo "✅ Conversa encontrada:\n";
echo "   - ID: {$conversation['id']}\n";
echo "   - Contact: {$conversation['contact_external_id']}\n\n";

// Busca eventos da conversa
$sql2 = "SELECT 
    ce.*
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.payload LIKE ?
ORDER BY ce.created_at DESC
LIMIT 5";

$stmt2 = $db->prepare($sql2);
$stmt2->execute(["%{$normalizedPhone}%"]);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo "Evento: {$event['event_id']}\n";
    
    $payload = json_decode($event['payload'], true);
    $content = $payload['text'] ?? $payload['message']['text'] ?? '';
    
    echo "  - Content length: " . strlen($content) . "\n";
    
    // Busca mídia
    $mediaCheck = $db->prepare("SELECT * FROM communication_media WHERE event_id = ?");
    $mediaCheck->execute([$event['event_id']]);
    $media = $mediaCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($media) {
        echo "  - ✅ Mídia encontrada: {$media['media_type']}\n";
        echo "    URL: " . ($media['stored_path'] ? 'whatsapp-media/...' : 'N/A') . "\n";
    } else {
        echo "  - ❌ Sem mídia\n";
    }
    
    echo "\n";
}

echo "========================================\n";

