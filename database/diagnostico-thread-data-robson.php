<?php
/**
 * Diagnóstico: Simula a resposta de thread-data para whatsapp_8 (Robson 4234)
 * Chama o controller real para ver exatamente o que a API retornaria.
 *
 * Execução: php database/diagnostico-thread-data-robson.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

\PixelHub\Core\Env::load();

// Define BASE_PATH para pixelhub_url()
if (!defined('BASE_PATH')) {
    define('BASE_PATH', '/painel.pixel12digital');
}

// Simula ambiente web para Auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Login simulado: pega usuário com is_internal=1, ou primeiro usuário
$db = \PixelHub\Core\DB::getConnection();
$stmt = $db->query("SELECT id, email, name, COALESCE(is_internal, 1) as is_internal FROM users WHERE is_internal = 1 LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $stmt = $db->query("SELECT id, email, name, 1 as is_internal FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$user) {
    die("Erro: Nenhum usuário no banco para simular login.\n");
}
$user['is_internal'] = 1; // Garante acesso
$_SESSION['pixelhub_user'] = $user;

// Simula requisição GET
$_GET['thread_id'] = 'whatsapp_8';
$_GET['channel'] = 'whatsapp';
$_SERVER['REQUEST_URI'] = '/communication-hub/thread-data?thread_id=whatsapp_8&channel=whatsapp';
$_SERVER['HTTP_ACCEPT'] = 'application/json';

// Captura saída do controller
ob_start();
try {
    $controller = new \PixelHub\Controllers\CommunicationHubController();
    $controller->getThreadData();
    $output = ob_get_clean();
} catch (\Throwable $e) {
    ob_end_clean();
    die("Erro: " . $e->getMessage() . "\n" . $e->getTraceAsString());

}

$result = json_decode($output, true);
if (!$result) {
    echo "Resposta não é JSON válido:\n";
    echo substr($output, 0, 500) . "\n";
    exit(1);
}

echo "=== RESUMO thread-data (whatsapp_8) ===\n\n";
echo "success: " . ($result['success'] ? 'true' : 'false') . "\n";
if (!empty($result['error'])) {
    echo "error: " . $result['error'] . "\n";
    exit(1);
}

$messages = $result['messages'] ?? [];
echo "Total de mensagens retornadas: " . count($messages) . "\n\n";

// Verifica se a mensagem "testa novamente" está entre elas (limita exibição a 30)
$hasOutbound = false;
$hasImage = false;
$hasAudio = false;
$toShow = array_slice($messages, -30, 30); // Últimas 30 mensagens
foreach ($toShow as $i => $m) {
    $dir = $m['direction'] ?? '?';
    $rawContent = $m['content'] ?? $m['text'] ?? $m['body'] ?? '';
    $content = strlen($rawContent) > 80 ? substr($rawContent, 0, 80) . '...' : $rawContent;
    $media = $m['media'] ?? null;
    $mediaUrl = $media['url'] ?? '-';
    $mediaType = $media['media_type'] ?? $media['type'] ?? '-';
    $ts = $m['timestamp'] ?? $m['created_at'] ?? $i;

    $mediaInfo = $mediaType !== '-' ? "{$mediaType}" : '-';
    echo sprintf("[%d] %s | %s | %s | media=%s\n", $i + 1, $ts, $dir, $content, $mediaInfo);

    if (strpos($content, 'Testa novamente') !== false || strpos($content, 'testa novamente') !== false) {
        $hasOutbound = true;
    }
    if ($mediaType === 'image' || $mediaType === 'sticker') $hasImage = true;
    if ($mediaType === 'audio' || $mediaType === 'ptt' || $mediaType === 'voice') $hasAudio = true;
}

echo "\n=== DIAGNÓSTICO ===\n";
echo "Mensagem enviada (13:28) 'testa novamente Robson': " . ($hasOutbound ? "✓ PRESENTE" : "❌ AUSENTE") . "\n";
echo "Imagem recebida (13:29): " . ($hasImage ? "✓ PRESENTE" : "❌ AUSENTE") . "\n";
echo "Áudio recebido (13:29): " . ($hasAudio ? "✓ PRESENTE" : "❌ AUSENTE") . "\n";
