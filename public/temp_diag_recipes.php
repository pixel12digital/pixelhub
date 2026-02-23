<?php
// Diagnóstico temporário — remover após uso
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Config.php';
\PixelHub\Core\Config::load();

$db = \PixelHub\Core\DB::getConnection();

echo "<pre>";
echo "=== Últimas 10 receitas de prospecção ===\n";
$stmt = $db->query("SELECT id, tenant_id, name, source, cnae_code, state, city, created_at FROM prospecting_recipes ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID={$r['id']} tenant_id=" . ($r['tenant_id'] ?? 'NULL') . " source={$r['source']} cnae={$r['cnae_code']} state={$r['state']} city={$r['city']} name=\"{$r['name']}\" created={$r['created_at']}\n";
}
echo "</pre>";
