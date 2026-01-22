<?php
/**
 * Teste: Verifica se o objeto media está completo em todas as respostas
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

echo "========================================\n";
echo "TESTE: Objeto Media Completo\n";
echo "========================================\n\n";

$db = DB::getConnection();

// Busca mídia
$sql = "SELECT * FROM communication_media WHERE event_id = 'fe23f980-c24b-4f8a-b378-99b4a1c2a2cc' LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute();
$media = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$media) {
    echo "❌ Mídia não encontrada\n";
    exit(1);
}

echo "1. Testando WhatsAppMediaService::getMediaByEventId():\n";
$mediaInfo = WhatsAppMediaService::getMediaByEventId('fe23f980-c24b-4f8a-b378-99b4a1c2a2cc');

if ($mediaInfo) {
    echo "   ✅ Objeto media retornado\n\n";
    
    // Campos obrigatórios esperados
    $requiredFields = [
        'id' => 'ID da mídia',
        'type' => 'Tipo da mídia (compatibilidade)',
        'media_type' => 'Tipo da mídia (original)',
        'mime_type' => 'MIME type',
        'size' => 'Tamanho em bytes (compatibilidade)',
        'file_size' => 'Tamanho em bytes (original)',
        'url' => 'URL para acessar a mídia',
        'path' => 'Caminho armazenado (compatibilidade)',
        'stored_path' => 'Caminho armazenado (original)',
        'file_name' => 'Nome do arquivo'
    ];
    
    echo "2. Verificando campos obrigatórios:\n";
    $allPresent = true;
    foreach ($requiredFields as $field => $description) {
        $present = isset($mediaInfo[$field]);
        $value = $mediaInfo[$field] ?? 'N/A';
        $status = $present ? '✅' : '❌';
        echo "   {$status} {$field}: " . ($present ? (is_null($value) ? 'NULL' : (is_array($value) ? json_encode($value) : $value)) : 'AUSENTE') . "\n";
        if (!$present) {
            $allPresent = false;
        }
    }
    
    echo "\n3. Estrutura completa do objeto:\n";
    echo json_encode($mediaInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    
    echo "\n4. Verificações de tipo:\n";
    echo "   - id é inteiro: " . (is_int($mediaInfo['id'] ?? null) ? '✅' : '❌') . "\n";
    echo "   - size é inteiro ou null: " . (is_int($mediaInfo['size'] ?? null) || is_null($mediaInfo['size'] ?? null) ? '✅' : '❌') . "\n";
    echo "   - url é string não vazia: " . (!empty($mediaInfo['url']) && is_string($mediaInfo['url']) ? '✅' : '❌') . "\n";
    echo "   - path é string não vazia: " . (!empty($mediaInfo['path']) && is_string($mediaInfo['path']) ? '✅' : '❌') . "\n";
    
    if ($allPresent) {
        echo "\n✅ TODOS OS CAMPOS OBRIGATÓRIOS ESTÃO PRESENTES\n";
    } else {
        echo "\n❌ ALGUNS CAMPOS OBRIGATÓRIOS ESTÃO AUSENTES\n";
    }
} else {
    echo "   ❌ Objeto media NÃO retornado\n";
}

echo "\n========================================\n";

