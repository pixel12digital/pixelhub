<?php
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}
\PixelHub\Core\Env::load(__DIR__ . '/');

use PixelHub\Core\DB;

$db = DB::getConnection();

// Reseta search_grid_data de TODAS as receitas Google Maps
// para que recomecem com a lógica corrigida (keyword sem cidade)
$stmt = $db->prepare("UPDATE prospecting_recipes SET search_grid_data = NULL, updated_at = NOW() WHERE source = 'google_maps'");
$stmt->execute();
$affected = $stmt->rowCount();

echo "Grid resetado em {$affected} receita(s) Google Maps.\n";
echo "Próximo 'Buscar Agora' iniciará a grade do zero com queries corrigidas.\n";

// Lista receitas afetadas
$rows = $db->query("SELECT id, name, city, state FROM prospecting_recipes WHERE source = 'google_maps'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "  - ID {$r['id']}: {$r['name']} ({$r['city']}/{$r['state']})\n";
}
