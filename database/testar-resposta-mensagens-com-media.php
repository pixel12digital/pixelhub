<?php
/**
 * Teste: Simula resposta de mensagens com objeto media completo
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
echo "TESTE: Resposta de Mensagens com Media\n";
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
    
    // Busca mídia (como o controller faz)
    $mediaInfo = null;
    try {
        $mediaInfo = WhatsAppMediaService::getMediaByEventId($event['event_id']);
        
        // Limpa conteúdo se for base64
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
        'media' => $mediaInfo // Objeto media completo quando existir
    ];
    
    $messages[] = $message;
}

echo "Mensagens montadas: " . count($messages) . "\n\n";

// Verifica se objeto media está completo
foreach ($messages as $i => $msg) {
    if (!empty($msg['media'])) {
        echo "Mensagem " . ($i + 1) . " - Objeto media:\n";
        $media = $msg['media'];
        
        $requiredFields = ['id', 'type', 'media_type', 'mime_type', 'size', 'file_size', 'url', 'path', 'stored_path', 'file_name'];
        $allPresent = true;
        
        foreach ($requiredFields as $field) {
            $present = isset($media[$field]);
            echo "   " . ($present ? '✅' : '❌') . " {$field}: " . ($present ? (is_null($media[$field]) ? 'NULL' : (is_array($media[$field]) ? json_encode($media[$field]) : $media[$field])) : 'AUSENTE') . "\n";
            if (!$present) {
                $allPresent = false;
            }
        }
        
        if ($allPresent) {
            echo "   ✅ Objeto media COMPLETO\n";
        } else {
            echo "   ❌ Objeto media INCOMPLETO\n";
        }
        
        echo "\n   Estrutura JSON completa:\n";
        echo json_encode($media, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        break; // Mostra apenas a primeira com mídia
    }
}

echo "\n========================================\n";
echo "RESUMO:\n";
echo "========================================\n";
$withMedia = array_filter($messages, function($m) { return !empty($m['media']); });
echo "Mensagens com mídia: " . count($withMedia) . "\n";
echo "Mensagens sem mídia: " . (count($messages) - count($withMedia)) . "\n";

if (count($withMedia) > 0) {
    $firstWithMedia = array_values($withMedia)[0];
    $media = $firstWithMedia['media'];
    $hasAllFields = isset($media['id']) && isset($media['type']) && isset($media['mime_type']) && 
                    isset($media['size']) && isset($media['url']) && isset($media['path']);
    echo "Objeto media completo: " . ($hasAllFields ? '✅ SIM' : '❌ NÃO') . "\n";
}

echo "========================================\n";

