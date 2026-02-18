<?php
// Teste da migration com conexão remota

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
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

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== Conexão OK ===\n";
    echo "Host: " . $db->query('SELECT @@hostname')->fetchColumn() . "\n";
    echo "Database: " . $db->query('SELECT DATABASE()')->fetchColumn() . "\n";
    
    // Verifica se campos já existem
    $stmt = $db->query("SHOW COLUMNS FROM agenda_manual_items LIKE 'status'");
    $statusExists = $stmt->rowCount() > 0;
    
    if ($statusExists) {
        echo "✓ Campo status já existe\n";
    } else {
        echo "⚠ Campo status não existe, pode ser adicionado\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
