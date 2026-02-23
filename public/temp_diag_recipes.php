<?php
// Diagnóstico temporário — remover após uso
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $file = __DIR__ . '/../src/' . str_replace(['PixelHub\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($file)) require $file;
    });
}
if (file_exists(__DIR__ . '/../.env')) {
    foreach (file(__DIR__ . '/../.env') as $line) {
        $line = trim($line);
        if ($line && strpos($line, '=') !== false && $line[0] !== '#') {
            [$k, $v] = explode('=', $line, 2);
            $_ENV[trim($k)] = trim($v);
            putenv(trim($k) . '=' . trim($v));
        }
    }
}

$db = \PixelHub\Core\DB::getConnection();

echo "<pre>";

// Corrige receitas com source vazio que têm cnae_code (são minhareceita)
$fix = $db->exec("UPDATE prospecting_recipes SET source = 'minhareceita' WHERE (source = '' OR source IS NULL) AND cnae_code IS NOT NULL AND cnae_code != ''");
echo "=== Correção: {$fix} receita(s) com source vazio corrigidas para 'minhareceita' ===\n\n";

echo "=== Últimas 10 receitas de prospecção ===\n";
$stmt = $db->query("SELECT id, tenant_id, name, source, cnae_code, state, city, created_at FROM prospecting_recipes ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID={$r['id']} tenant_id=" . ($r['tenant_id'] ?? 'NULL') . " source={$r['source']} cnae={$r['cnae_code']} state={$r['state']} city={$r['city']} name=\"{$r['name']}\" created={$r['created_at']}\n";
}
echo "</pre>";
