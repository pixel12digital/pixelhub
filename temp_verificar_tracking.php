<?php
// Verifica estrutura da tabela opportunities
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

// Carrega .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, '"\'');
        
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

$db = \PixelHub\Core\DB::getConnection();

echo "=== ESTRUTURA DA TABELA OPPORTUNITIES ===\n";

$stmt = $db->query("DESCRIBE opportunities");
$columns = $stmt->fetchAll();

echo "Colunas relacionadas a tracking:\n";
foreach ($columns as $col) {
    if (strpos($col['Field'], 'tracking') !== false) {
        echo "- {$col['Field']}: {$col['Type']} (Null: " . ($col['Null'] === 'YES' ? 'YES' : 'NO') . ")\n";
    }
}

// Mostra todas as colunas para análise
echo "\n=== TODAS AS COLUNAS DA TABELA ===\n";
foreach ($columns as $col) {
    echo "- {$col['Field']}: {$col['Type']}\n";
}

// Verifica se há dados de tracking
echo "\n=== VERIFICANDO DADOS DE TRACKING ===\n";
echo "Nenhuma coluna tracking encontrada na tabela opportunities\n";
