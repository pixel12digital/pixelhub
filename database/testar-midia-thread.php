<?php
/**
 * Script para testar se a mídia está sendo retornada corretamente para a thread
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
echo "TESTE DE MÍDIA NA THREAD\n";
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

echo "✅ Evento encontrado:\n";
echo "   - Event ID: {$event['event_id']}\n\n";

// Testa busca de mídia
echo "1. Testando WhatsAppMediaService::getMediaByEventId()...\n";
$mediaInfo = WhatsAppMediaService::getMediaByEventId($event['event_id']);

if ($mediaInfo) {
    echo "   ✅ Mídia encontrada:\n";
    echo "      - ID: {$mediaInfo['id']}\n";
    echo "      - Event ID: {$mediaInfo['event_id']}\n";
    echo "      - Tipo: {$mediaInfo['media_type']}\n";
    echo "      - MIME: {$mediaInfo['mime_type']}\n";
    echo "      - Caminho: {$mediaInfo['stored_path']}\n";
    echo "      - URL: " . ($mediaInfo['url'] ?? 'N/A') . "\n";
    echo "      - Tamanho: " . ($mediaInfo['file_size'] ? number_format($mediaInfo['file_size'] / 1024, 2) . ' KB' : 'N/A') . "\n";
    
    // Verifica se arquivo existe
    if ($mediaInfo['stored_path']) {
        $fullPath = __DIR__ . '/../storage/' . $mediaInfo['stored_path'];
        echo "      - Arquivo existe: " . (file_exists($fullPath) ? '✅ Sim' : '❌ Não') . "\n";
        if (file_exists($fullPath)) {
            echo "      - Tamanho real: " . number_format(filesize($fullPath) / 1024, 2) . " KB\n";
        }
    }
} else {
    echo "   ❌ Mídia não encontrada\n";
}

echo "\n2. Verificando estrutura esperada pela view...\n";
echo "   A view espera: message.media.url\n";
echo "   Estrutura atual:\n";
echo "   " . json_encode($mediaInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n3. Testando URL da mídia...\n";
if ($mediaInfo && !empty($mediaInfo['url'])) {
    echo "   URL: {$mediaInfo['url']}\n";
    // Tenta acessar o arquivo diretamente
    if ($mediaInfo['stored_path']) {
        $fullPath = __DIR__ . '/../storage/' . $mediaInfo['stored_path'];
        if (file_exists($fullPath)) {
            echo "   ✅ Arquivo existe no caminho esperado\n";
        } else {
            echo "   ❌ Arquivo NÃO existe em: {$fullPath}\n";
        }
    }
} else {
    echo "   ⚠️  URL não disponível\n";
}

echo "\n========================================\n";

