<?php
// Verificar estrutura da tabela communication_events

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

echo "=== Estrutura communication_events ===\n";

$db = DB::getConnection();
$cols = $db->query('DESCRIBE communication_events')->fetchAll(PDO::FETCH_ASSOC);

foreach ($cols as $col) {
    echo $col['Field'] . ' - ' . $col['Type'] . "\n";
}
?>
