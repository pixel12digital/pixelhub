<?php
/**
 * Script de diagnóstico para verificar token de gravação no banco remoto
 */

// Autoload manual
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

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$token = $argv[1] ?? 'c9fe172b1f5b403ec386106a755ccb51';

echo "=== Verificação de Token de Gravação ===\n\n";
echo "Token: {$token}\n\n";

try {
    $db = DB::getConnection();
    
    // Busca gravação por token
    $stmt = $db->prepare("
        SELECT 
            id, file_path, file_name, original_name, mime_type, 
            size_bytes, duration_seconds, has_audio, public_token, created_at
        FROM screen_recordings
        WHERE public_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $recording = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$recording) {
        echo "✗ Gravação NÃO encontrada no banco com este token!\n";
        echo "\nVerificando se a tabela existe...\n";
        $stmt = $db->query("SHOW TABLES LIKE 'screen_recordings'");
        if ($stmt->fetch()) {
            echo "✓ Tabela screen_recordings existe\n";
            echo "\nÚltimas 5 gravações no banco:\n";
            $stmt = $db->query("SELECT id, public_token, file_path, created_at FROM screen_recordings ORDER BY id DESC LIMIT 5");
            $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all as $r) {
                echo "  - ID: {$r['id']}, Token: {$r['public_token']}, Path: {$r['file_path']}, Data: {$r['created_at']}\n";
            }
        } else {
            echo "✗ Tabela screen_recordings NÃO existe!\n";
            echo "Execute: php database/migrate.php\n";
        }
        exit(1);
    }
    
    echo "✓ Gravação encontrada!\n\n";
    echo "Detalhes:\n";
    echo "  ID: {$recording['id']}\n";
    echo "  file_path: {$recording['file_path']}\n";
    echo "  file_name: {$recording['file_name']}\n";
    echo "  original_name: {$recording['original_name']}\n";
    echo "  size_bytes: {$recording['size_bytes']}\n";
    echo "  duration_seconds: {$recording['duration_seconds']}\n";
    echo "  created_at: {$recording['created_at']}\n\n";
    
    // Verifica se arquivo físico existe
    $relativePath = ltrim($recording['file_path'], '/');
    $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
    $filePath = __DIR__ . '/../public/screen-recordings/' . $fileRelativePath;
    
    echo "Verificando arquivo físico:\n";
    echo "  Caminho relativo (banco): {$recording['file_path']}\n";
    echo "  Caminho relativo (processado): {$fileRelativePath}\n";
    echo "  Caminho absoluto: {$filePath}\n";
    
    if (file_exists($filePath) && is_file($filePath)) {
        $fileSize = filesize($filePath);
        echo "  ✓ Arquivo existe! Tamanho: {$fileSize} bytes\n";
        
        if ($fileSize != $recording['size_bytes']) {
            echo "  ⚠ AVISO: Tamanho do arquivo ({$fileSize}) difere do banco ({$recording['size_bytes']})\n";
        }
    } else {
        echo "  ✗ Arquivo NÃO existe no servidor!\n";
        echo "  Verifique se o arquivo foi enviado corretamente.\n";
    }
    
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}












