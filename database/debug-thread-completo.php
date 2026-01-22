<?php
/**
 * Debug completo da thread - verifica todos os pontos
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
echo "DEBUG COMPLETO DA THREAD\n";
echo "Número: {$phone}\n";
echo "========================================\n\n";

$db = DB::getConnection();

// 1. Verifica mídia no banco
echo "1. VERIFICAÇÃO NO BANCO:\n";
$sql = "SELECT * FROM communication_media WHERE event_id = 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc'";
$stmt = $db->query($sql);
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if ($media) {
    echo "   ✅ Mídia encontrada (ID: {$media['id']})\n";
    echo "   - stored_path: {$media['stored_path']}\n";
    echo "   - media_type: {$media['media_type']}\n";
    echo "   - mime_type: {$media['mime_type']}\n";
} else {
    echo "   ❌ Mídia NÃO encontrada\n";
    exit(1);
}

// 2. Verifica arquivo físico
echo "\n2. VERIFICAÇÃO DO ARQUIVO:\n";
$fullPath = __DIR__ . '/../storage/' . $media['stored_path'];
if (file_exists($fullPath)) {
    echo "   ✅ Arquivo existe\n";
    echo "   - Caminho: {$fullPath}\n";
    echo "   - Tamanho: " . number_format(filesize($fullPath) / 1024, 2) . " KB\n";
    echo "   - Legível: " . (is_readable($fullPath) ? '✅ Sim' : '❌ Não') . "\n";
} else {
    echo "   ❌ Arquivo NÃO existe em: {$fullPath}\n";
}

// 3. Testa WhatsAppMediaService
echo "\n3. TESTE DO WhatsAppMediaService:\n";
$mediaInfo = WhatsAppMediaService::getMediaByEventId('fe23f980-c24b-4f8a-b378-99b4a1c2a2cc');
if ($mediaInfo) {
    echo "   ✅ getMediaByEventId() retornou dados\n";
    echo "   - URL gerada: " . ($mediaInfo['url'] ?? 'N/A') . "\n";
    echo "   - Estrutura completa:\n";
    echo json_encode($mediaInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "   ❌ getMediaByEventId() retornou NULL\n";
}

// 4. Verifica BASE_PATH e pixelhub_url
echo "\n4. VERIFICAÇÃO DE BASE_PATH E URL:\n";
$basePath = defined('BASE_PATH') ? BASE_PATH : 'NÃO DEFINIDO';
echo "   - BASE_PATH: {$basePath}\n";
echo "   - pixelhub_url() existe: " . (function_exists('pixelhub_url') ? '✅ Sim' : '❌ Não') . "\n";
if (function_exists('pixelhub_url')) {
    $testUrl = pixelhub_url('/communication-hub/media?path=test');
    echo "   - pixelhub_url('/communication-hub/media?path=test'): {$testUrl}\n";
}

// 5. Simula mensagem como retornada pelo controller
echo "\n5. SIMULAÇÃO DA MENSAGEM RETORNADA:\n";
$message = [
    'id' => 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc',
    'direction' => 'inbound',
    'content' => '',
    'timestamp' => '2026-01-16 05:35:23',
    'media' => $mediaInfo
];

echo "   Estrutura da mensagem:\n";
echo json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

// 6. Testa condições da view
echo "\n6. TESTE DAS CONDIÇÕES DA VIEW:\n";
$hasMedia = !empty($message['media']);
$hasMediaUrl = $hasMedia && !empty($message['media']['url']);
echo "   - !empty(\$msg['media']): " . ($hasMedia ? '✅ TRUE' : '❌ FALSE') . "\n";
echo "   - !empty(\$msg['media']['url']): " . ($hasMediaUrl ? '✅ TRUE' : '❌ FALSE') . "\n";

if ($hasMediaUrl) {
    $media = $message['media'];
    $mediaType = strtolower($media['media_type'] ?? 'unknown');
    $mimeType = strtolower($media['mime_type'] ?? '');
    $isAudio = (strpos($mimeType, 'audio/') === 0 || in_array($mediaType, ['audio', 'voice']));
    echo "   - É áudio? " . ($isAudio ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   - mediaType: {$mediaType}\n";
    echo "   - mimeType: {$mimeType}\n";
    echo "   - URL final: " . ($media['url'] ?? 'N/A') . "\n";
}

// 7. Verifica endpoint serveMedia
echo "\n7. VERIFICAÇÃO DO ENDPOINT:\n";
$controllerFile = __DIR__ . '/../src/Controllers/CommunicationHubController.php';
$content = file_get_contents($controllerFile);
if (strpos($content, 'public function serveMedia') !== false) {
    echo "   ✅ Método serveMedia() existe\n";
    if (strpos($content, 'Auth::requireInternal') !== false) {
        $serveMediaPos = strpos($content, 'public function serveMedia');
        $authCheckPos = strpos($content, 'Auth::requireInternal', $serveMediaPos);
        if ($authCheckPos !== false && $authCheckPos < $serveMediaPos + 500) {
            echo "   ⚠️  REQUER AUTENTICAÇÃO (pode causar 401/403)\n";
            echo "   - Isso significa que a URL precisa ser acessada com sessão ativa\n";
        }
    }
} else {
    echo "   ❌ Método serveMedia() NÃO encontrado\n";
}

echo "\n========================================\n";
echo "RESUMO:\n";
echo "========================================\n";
echo "✅ Mídia no banco: " . ($media ? 'SIM' : 'NÃO') . "\n";
echo "✅ Arquivo físico: " . (file_exists($fullPath) ? 'SIM' : 'NÃO') . "\n";
echo "✅ getMediaByEventId(): " . ($mediaInfo ? 'RETORNOU DADOS' : 'NULL') . "\n";
echo "✅ Condições da view: " . ($hasMediaUrl ? 'SATISFEITAS' : 'NÃO SATISFEITAS') . "\n";
echo "⚠️  Endpoint requer auth: SIM (pode ser o problema)\n";
echo "\nPRÓXIMO PASSO: Testar URL no navegador com sessão ativa\n";
echo "========================================\n";

