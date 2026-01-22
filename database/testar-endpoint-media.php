<?php
/**
 * Teste do endpoint de mídia
 * Prova #1: O endpoint entrega o arquivo de verdade?
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

echo "========================================\n";
echo "TESTE DO ENDPOINT DE MÍDIA\n";
echo "Prova #1: O endpoint entrega o arquivo?\n";
echo "========================================\n\n";

$db = DB::getConnection();

// Busca mídia
$sql = "SELECT * FROM communication_media WHERE event_id = 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc' LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute();
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    echo "❌ Mídia não encontrada no banco\n";
    exit(1);
}

$storedPath = $media['stored_path'];
$fullPath = __DIR__ . '/../storage/' . $storedPath;

echo "1. Verificando arquivo físico:\n";
echo "   - Caminho: {$fullPath}\n";
echo "   - Existe: " . (file_exists($fullPath) ? '✅ Sim' : '❌ Não') . "\n";
if (file_exists($fullPath)) {
    echo "   - Tamanho: " . number_format(filesize($fullPath) / 1024, 2) . " KB\n";
    echo "   - Permissões: " . substr(sprintf('%o', fileperms($fullPath)), -4) . "\n";
    
    // Verifica header OGG
    $handle = fopen($fullPath, 'rb');
    $header = fread($handle, 4);
    fclose($handle);
    echo "   - Header: " . bin2hex($header) . " (" . ($header === 'OggS' ? '✅ OGG válido' : '❌ Inválido') . ")\n";
}

echo "\n2. URL gerada pelo sistema:\n";
if (function_exists('pixelhub_url')) {
    $url = pixelhub_url('/communication-hub/media?path=' . urlencode($storedPath));
    echo "   - Com pixelhub_url(): {$url}\n";
} else {
    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
    $url = $basePath . '/communication-hub/media?path=' . urlencode($storedPath);
    echo "   - Sem pixelhub_url (fallback): {$url}\n";
    echo "   - BASE_PATH: " . ($basePath ?: 'NÃO DEFINIDO') . "\n";
}

echo "\n3. URL que deve ser testada no navegador:\n";
echo "   {$url}\n";

echo "\n4. Teste manual:\n";
echo "   - Abra esta URL no navegador: {$url}\n";
echo "   - Deve retornar:\n";
echo "     * Status: 200 OK\n";
echo "     * Content-Type: audio/ogg\n";
echo "     * Arquivo deve tocar ou baixar\n";

echo "\n5. Verificando endpoint serveMedia():\n";
$controllerFile = __DIR__ . '/../src/Controllers/CommunicationHubController.php';
if (file_exists($controllerFile)) {
    $content = file_get_contents($controllerFile);
    if (strpos($content, 'function serveMedia') !== false) {
        echo "   - ✅ Método serveMedia() existe\n";
        
        // Verifica se verifica autenticação
        if (strpos($content, 'Auth::requireInternal') !== false && strpos($content, 'serveMedia') !== false) {
            $beforeServeMedia = substr($content, 0, strpos($content, 'public function serveMedia'));
            $authCheck = strpos($beforeServeMedia, 'Auth::requireInternal') !== false || 
                        strpos($content, 'serveMedia') < strpos($content, 'Auth::requireInternal');
            echo "   - Requer autenticação: " . ($authCheck ? '⚠️  SIM (pode causar 401/403)' : '✅ NÃO') . "\n";
        }
    } else {
        echo "   - ❌ Método serveMedia() NÃO encontrado\n";
    }
} else {
    echo "   - ❌ Controller não encontrado\n";
}

echo "\n========================================\n";
echo "PRÓXIMOS PASSOS:\n";
echo "1. Teste a URL no navegador\n";
echo "2. Verifique status HTTP e Content-Type\n";
echo "3. Se der 401/403: endpoint requer autenticação\n";
echo "4. Se der 404: verificar rota/BASE_PATH\n";
echo "========================================\n";

