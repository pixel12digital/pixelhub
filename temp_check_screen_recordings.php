<?php
require_once 'vendor/autoload.php';
PixelHub\Core\Env::load();
$db = PixelHub\Core\DB::getConnection();

echo "=== Verificando registros com token público ===\n\n";

$stmt = $db->query('SELECT id, public_token, file_path, file_name, created_at FROM screen_recordings WHERE public_token IS NOT NULL ORDER BY id DESC LIMIT 10');
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($records as $rec) {
    echo "ID: {$rec['id']} | Token: {$rec['public_token']} | file_path: {$rec['file_path']} | file_name: {$rec['file_name']} | created_at: {$rec['created_at']}\n";
    
    // Verifica se o arquivo existe fisicamente
    $relativePath = ltrim($rec['file_path'], '/');
    if (strpos($relativePath, 'screen-recordings/') === 0) {
        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
        $filePath = __DIR__ . '/public/screen-recordings/' . $fileRelativePath;
        $exists = file_exists($filePath) && is_file($filePath);
        echo "  - Arquivo físico: " . ($exists ? "EXISTS" : "NOT FOUND") . " em $filePath\n";
    } elseif (strpos($relativePath, 'storage/tasks/') === 0) {
        $filePath = __DIR__ . '/' . $relativePath;
        $exists = file_exists($filePath) && is_file($filePath);
        echo "  - Arquivo físico: " . ($exists ? "EXISTS" : "NOT FOUND") . " em $filePath\n";
    }
    echo "\n";
}

echo "Total de registros: " . count($records) . "\n";
