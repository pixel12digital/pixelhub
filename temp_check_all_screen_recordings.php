<?php
require_once 'vendor/autoload.php';
PixelHub\Core\Env::load();
$db = PixelHub\Core\DB::getConnection();

echo "=== Verificando TODOS os registros de screen_recordings ===\n\n";

$stmt = $db->query('SELECT id, public_token, file_path, created_at FROM screen_recordings ORDER BY id DESC');
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Total de registros: ' . count($records) . "\n\n";

foreach ($records as $rec) {
    $hasToken = $rec['public_token'] ? 'SIM' : 'NÃO';
    echo "ID: {$rec['id']} | Token: $hasToken | file_path: {$rec['file_path']} | created_at: {$rec['created_at']}\n";
    
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
