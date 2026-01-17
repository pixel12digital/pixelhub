<?php
/**
 * Verificar áudios no Hub - diagnosticar problema de reprodução
 */

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

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE ÁUDIOS NO HUB ===\n\n";

// 1. Buscar mídias de áudio recentes
echo "1. Mídias de áudio recentes (últimas 10):\n";
$stmt = $db->query("
    SELECT 
        cm.id,
        cm.event_id,
        cm.media_type,
        cm.mime_type,
        cm.file_name,
        cm.stored_path,
        cm.created_at,
        ce.tenant_id
    FROM communication_media cm
    INNER JOIN communication_events ce ON cm.event_id = ce.event_id
    WHERE cm.media_type = 'audio' OR cm.mime_type LIKE 'audio/%'
    ORDER BY cm.created_at DESC
    LIMIT 10
");
$audios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($audios)) {
    echo "❌ Nenhum áudio encontrado.\n\n";
} else {
    foreach ($audios as $audio) {
        $storedPath = $audio['stored_path'] ?: 'NULL';
        $absolutePath = __DIR__ . '/../storage/' . ltrim($storedPath, '/');
        $exists = file_exists($absolutePath) ? '✅' : '❌';
        $size = file_exists($absolutePath) ? filesize($absolutePath) . ' bytes' : 'N/A';
        
        echo "  • ID: {$audio['id']} | Event ID: {$audio['event_id']} | Tenant: {$audio['tenant_id']}\n";
        echo "    MIME: {$audio['mime_type']} | Tipo: {$audio['media_type']}\n";
        echo "    Path: {$storedPath} {$exists}\n";
        echo "    Absoluto: {$absolutePath}\n";
        echo "    Tamanho: {$size} | Criado: {$audio['created_at']}\n";
        
        // Gerar URL esperada
        if ($storedPath !== 'NULL') {
            $baseUrl = 'http://localhost/painel.pixel12digital';
            $url = $baseUrl . '/communication-hub/media?path=' . urlencode($storedPath);
            echo "    URL: {$url}\n";
        }
        echo "\n";
    }
}

// 2. Verificar se diretório storage/whatsapp-media existe
echo "2. Verificação do diretório storage:\n";
$storageDir = __DIR__ . '/../storage';
$whatsappMediaDir = $storageDir . '/whatsapp-media';

echo "  • storage/ existe: " . (is_dir($storageDir) ? '✅' : '❌') . "\n";
echo "  • storage/whatsapp-media/ existe: " . (is_dir($whatsappMediaDir) ? '✅' : '❌') . "\n";

if (is_dir($whatsappMediaDir)) {
    $files = glob($whatsappMediaDir . '/**/*.{ogg,mp3,wav,m4a}', GLOB_BRACE);
    echo "  • Arquivos de áudio encontrados: " . count($files) . "\n";
    if (count($files) > 0) {
        echo "    Primeiros 5 arquivos:\n";
        foreach (array_slice($files, 0, 5) as $file) {
            $relativePath = str_replace(__DIR__ . '/../storage/', '', $file);
            echo "      - {$relativePath} (" . filesize($file) . " bytes)\n";
        }
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

