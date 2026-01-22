<?php
/**
 * Script para simular o que o controller retorna para a thread
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
use PixelHub\Services\WhatsAppMediaService;

Env::load();

$phone = '5511965221349';
$normalizedPhone = preg_replace('/[^0-9]/', '', $phone);

echo "========================================\n";
echo "SIMULAÇÃO DE MENSAGEM DA THREAD\n";
echo "Número: {$phone}\n";
echo "========================================\n\n";

$db = DB::getConnection();

// Busca o evento
$sql = "SELECT 
    ce.*
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
AND ce.payload LIKE ?
ORDER BY ce.created_at DESC
LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute(["%{$normalizedPhone}%"]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Nenhum evento encontrado\n";
    exit(1);
}

// Simula o que o controller faz
$payload = json_decode($event['payload'], true);

// Extrai conteúdo
$content = $payload['text'] 
    ?? $payload['body'] 
    ?? $payload['message']['text'] 
    ?? $payload['message']['body'] 
    ?? '';

echo "1. Conteúdo extraído:\n";
echo "   - Tamanho: " . strlen($content) . " caracteres\n";
echo "   - Primeiros 100 chars: " . substr($content, 0, 100) . "...\n\n";

// Busca mídia (como o controller faz)
$mediaInfo = null;
try {
    $mediaInfo = WhatsAppMediaService::getMediaByEventId($event['event_id']);
    
    // Se encontrou mídia e o conteúdo parece ser base64, limpa o conteúdo
    if ($mediaInfo && !empty($content) && strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
        $textCleaned = preg_replace('/\s+/', '', $content);
        $decoded = base64_decode($textCleaned, true);
        if ($decoded !== false && substr($decoded, 0, 4) === 'OggS') {
            echo "   ✅ Detectado como áudio base64, limpando conteúdo\n";
            $content = '';
        }
    }
} catch (\Exception $e) {
    echo "   ⚠️  Erro ao buscar mídia: " . $e->getMessage() . "\n";
}

// Monta mensagem como o controller faz
$message = [
    'id' => $event['event_id'],
    'direction' => 'inbound',
    'content' => $content,
    'timestamp' => $event['created_at'],
    'media' => $mediaInfo
];

echo "\n2. Mensagem montada:\n";
echo json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

echo "\n3. Verificações:\n";
echo "   - Content vazio: " . (empty($message['content']) ? '✅ Sim' : '❌ Não') . "\n";
echo "   - Media presente: " . ($message['media'] ? '✅ Sim' : '❌ Não') . "\n";
if ($message['media']) {
    echo "   - Media URL: " . ($message['media']['url'] ?? 'N/A') . "\n";
    echo "   - Media Type: " . ($message['media']['media_type'] ?? 'N/A') . "\n";
    echo "   - MIME Type: " . ($message['media']['mime_type'] ?? 'N/A') . "\n";
}

echo "\n========================================\n";

