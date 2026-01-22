<?php
/**
 * Script para debugar o processamento de áudio
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/WhatsAppMediaService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\WhatsAppMediaService;

Env::load();

$db = DB::getConnection();

echo "=== DEBUG: PROCESSAMENTO DE ÁUDIO ===\n\n";

$eventId = '02025624-a245-4b9d-9fa9-384b2841fc6c';

// Busca o evento
$stmt = $db->prepare("SELECT * FROM communication_events WHERE event_id = ?");
$stmt->execute([$eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado!\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);
$text = $payload['text'] ?? $payload['message']['text'] ?? null;

echo "1. Verificando payload:\n";
echo "   text existe: " . (isset($text) ? 'sim' : 'não') . "\n";
if ($text) {
    echo "   text length: " . strlen($text) . "\n";
    echo "   text preview: " . substr($text, 0, 50) . "...\n";
    
    // Verifica padrão base64
    $isBase64Pattern = preg_match('/^[A-Za-z0-9+\/=\s]+$/', $text);
    echo "   é padrão base64: " . ($isBase64Pattern ? 'sim' : 'não') . "\n";
    
    if ($isBase64Pattern && strlen($text) > 100) {
        $textCleaned = preg_replace('/\s+/', '', $text);
        $decoded = base64_decode($textCleaned, true);
        
        if ($decoded !== false) {
            echo "   ✅ base64 válido!\n";
            echo "   decoded length: " . strlen($decoded) . " bytes\n";
            
            if (substr($decoded, 0, 4) === 'OggS') {
                echo "   ✅ É OGG!\n";
            } else {
                echo "   ⚠️  Não é OGG (primeiros 4 bytes: " . bin2hex(substr($decoded, 0, 4)) . ")\n";
            }
        } else {
            echo "   ❌ base64 inválido!\n";
        }
    }
}

echo "\n2. Chamando processMediaFromEvent...\n";
$result = WhatsAppMediaService::processMediaFromEvent($event);

if ($result) {
    echo "✅ Retornou resultado:\n";
    echo "   ID: {$result['id']}\n";
    echo "   Stored Path: {$result['stored_path']}\n";
    echo "   URL: {$result['url']}\n";
    
    // Verifica se arquivo existe
    $absolutePath = __DIR__ . '/../storage/' . $result['stored_path'];
    if (file_exists($absolutePath)) {
        echo "   ✅ Arquivo existe!\n";
    } else {
        echo "   ❌ Arquivo NÃO existe!\n";
        echo "   Caminho esperado: {$absolutePath}\n";
    }
} else {
    echo "❌ Retornou null (não processou)\n";
}

echo "\n✅ Debug concluído!\n";

