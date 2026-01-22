<?php
/**
 * Script para detalhar o evento encontrado do número 5511965221349
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
echo "DETALHAMENTO DO EVENTO\n";
echo "Número: {$phone}\n";
echo "========================================\n\n";

$db = DB::getConnection();

$sql = "SELECT 
    ce.*,
    cm.*
FROM communication_events ce
LEFT JOIN communication_media cm ON ce.event_id = cm.event_id
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.payload LIKE ?
ORDER BY ce.created_at DESC
LIMIT 5";

$stmt = $db->prepare($sql);
$stmt->execute(["%{$normalizedPhone}%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $i => $event) {
    echo "Evento " . ($i + 1) . ":\n";
    echo "  - ID: {$event['id']}\n";
    echo "  - Event ID: {$event['event_id']}\n";
    echo "  - Tipo: {$event['event_type']}\n";
    echo "  - Data: {$event['created_at']}\n";
    echo "  - Tenant ID: " . ($event['tenant_id'] ?? 'N/A') . "\n";
    
    $payload = json_decode($event['payload'], true);
    if ($payload) {
        echo "\n  📋 Estrutura do Payload:\n";
        echo "     - Keys principais: " . implode(', ', array_keys($payload)) . "\n";
        
        // Extrai informações relevantes
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        $type = $payload['type'] ?? $payload['message']['type'] ?? $payload['message']['message']['type'] ?? 'unknown';
        
        echo "     - From: {$from}\n";
        echo "     - Type: {$type}\n";
        
        // Verifica se tem mídia
        $hasMedia = false;
        $mediaInfo = [];
        
        if (isset($payload['message']['message'])) {
            $msgContent = $payload['message']['message'];
            $mediaKeys = ['audioMessage', 'imageMessage', 'videoMessage', 'documentMessage', 'stickerMessage'];
            foreach ($mediaKeys as $key) {
                if (isset($msgContent[$key])) {
                    $hasMedia = true;
                    $mediaInfo[$key] = $msgContent[$key];
                }
            }
        }
        
        if ($hasMedia) {
            echo "     - ✅ CONTÉM MÍDIA!\n";
            foreach ($mediaInfo as $mediaType => $mediaData) {
                echo "       * Tipo: {$mediaType}\n";
                if (isset($mediaData['mimetype'])) {
                    echo "       * MIME: {$mediaData['mimetype']}\n";
                }
                if (isset($mediaData['mediaKey'])) {
                    echo "       * Media Key: {$mediaData['mediaKey']}\n";
                }
                if (isset($mediaData['url'])) {
                    echo "       * URL: {$mediaData['url']}\n";
                }
            }
        } else {
            echo "     - ❌ Não contém mídia detectável\n";
        }
        
        // Mostra payload completo (limitado)
        echo "\n  📄 Payload (primeiros 1000 caracteres):\n";
        $payloadStr = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "     " . substr($payloadStr, 0, 1000) . (strlen($payloadStr) > 1000 ? '...' : '') . "\n";
    }
    
    // Verifica mídia processada
    if ($event['media_id']) {
        echo "\n  ✅ Mídia processada:\n";
        echo "     - Media ID: {$event['media_id']}\n";
        echo "     - Tipo: {$event['media_type']}\n";
        echo "     - Caminho: {$event['stored_path']}\n";
    } else {
        echo "\n  ❌ Mídia não processada\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
}

