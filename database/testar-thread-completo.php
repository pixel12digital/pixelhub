<?php
/**
 * Script para testar o retorno completo da thread como o controller faz
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
echo "TESTE COMPLETO DA THREAD\n";
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

$conversationId = $conversation['id'];
echo "✅ Conversa ID: {$conversationId}\n\n";

// Simula getWhatsAppMessagesFromConversation
$contactExternalId = $conversation['contact_external_id'];
$normalizedContactExternalId = preg_replace('/[^0-9]/', '', $contactExternalId);

// Busca eventos
$sql2 = "SELECT 
    ce.*
FROM communication_events ce
WHERE ce.event_type = 'whatsapp.inbound.message'
AND (
    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
)
ORDER BY ce.created_at ASC
LIMIT 10";

$stmt2 = $db->prepare($sql2);
$pattern = "%{$normalizedContactExternalId}%";
$stmt2->execute([$pattern, $pattern]);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos encontrados: " . count($events) . "\n\n";

$messages = [];

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    
    // Extrai conteúdo
    $content = $payload['text'] 
        ?? $payload['body'] 
        ?? $payload['message']['text'] 
        ?? $payload['message']['body'] 
        ?? '';
    
    // Busca mídia
    $mediaInfo = null;
    try {
        $mediaInfo = WhatsAppMediaService::getMediaByEventId($event['event_id']);
        
        // Se encontrou mídia, limpa conteúdo se for base64
        if ($mediaInfo && !empty($content)) {
            if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                $textCleaned = preg_replace('/\s+/', '', $content);
                $decoded = base64_decode($textCleaned, true);
                if ($decoded !== false) {
                    if (substr($decoded, 0, 4) === 'OggS' || strlen($decoded) > 1000) {
                        $content = '';
                    }
                }
            } else if (strlen($content) > 500) {
                $content = '';
            }
        }
    } catch (\Exception $e) {
        echo "Erro ao buscar mídia: " . $e->getMessage() . "\n";
    }
    
    $message = [
        'id' => $event['event_id'],
        'direction' => 'inbound',
        'content' => $content,
        'timestamp' => $event['created_at'],
        'media' => $mediaInfo
    ];
    
    $messages[] = $message;
    
    echo "Mensagem {$event['event_id']}:\n";
    echo "  - Content: " . (empty($content) ? 'VAZIO ✅' : 'TEM CONTEÚDO ❌ (' . strlen($content) . ' chars)') . "\n";
    echo "  - Media: " . ($mediaInfo ? 'SIM ✅' : 'NÃO ❌') . "\n";
    if ($mediaInfo) {
        echo "    * URL: " . ($mediaInfo['url'] ?? 'N/A') . "\n";
        echo "    * Type: " . ($mediaInfo['media_type'] ?? 'N/A') . "\n";
    }
    echo "\n";
}

echo "========================================\n";
echo "RESUMO:\n";
echo "Total de mensagens: " . count($messages) . "\n";
$withMedia = array_filter($messages, function($m) { return !empty($m['media']); });
echo "Mensagens com mídia: " . count($withMedia) . "\n";
$withEmptyContent = array_filter($messages, function($m) { return empty($m['content']); });
echo "Mensagens com conteúdo vazio: " . count($withEmptyContent) . "\n";

echo "\nJSON da primeira mensagem com mídia:\n";
foreach ($messages as $msg) {
    if (!empty($msg['media'])) {
        echo json_encode($msg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break;
    }
}

