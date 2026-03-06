<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== RECEITAS CADASTRADAS ===\n\n";

$recipes = $db->query("
    SELECT id, name, source, city, state, status, 
           (SELECT COUNT(*) FROM prospecting_results WHERE recipe_id = prospecting_recipes.id) as results_count
    FROM prospecting_recipes 
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($recipes as $r) {
    echo "ID {$r['id']}: {$r['name']}\n";
    echo "  - Source: {$r['source']}\n";
    echo "  - Local: {$r['city']}, {$r['state']}\n";
    echo "  - Status: {$r['status']}\n";
    echo "  - Resultados: {$r['results_count']}\n\n";
}
