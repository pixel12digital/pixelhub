<?php
// Testar a consulta específica do controller
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

echo "=== TESTANDO CONSULTA DO CONTROLLER ===\n\n";

// 1. Testar consulta de sources
try {
    echo "1. Testando consulta de sources:\n";
    $stmt = $db->query("SELECT DISTINCT l.source FROM opportunities o LEFT JOIN leads l ON o.lead_id = l.id WHERE l.source IS NOT NULL AND l.source != '' ORDER BY l.source");
    $sources = $stmt->fetchAll();
    
    echo "✅ Sources encontradas: " . count($sources) . "\n";
    foreach ($sources as $s) {
        echo "- " . $s['source'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro na consulta de sources: " . $e->getMessage() . "\n";
}

// 2. Testar OpportunityProductService
echo "\n2. Testando OpportunityProductService:\n";
try {
    require_once __DIR__ . '/src/Services/OpportunityProductService.php';
    $products = \PixelHub\Services\OpportunityProductService::listActive();
    echo "✅ Products: " . count($products) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro no OpportunityProductService: " . $e->getMessage() . "\n";
}

// 3. Testar OpportunityService::STAGES
echo "\n3. Testando OpportunityService::STAGES:\n";
try {
    require_once __DIR__ . '/src/Services/OpportunityService.php';
    echo "✅ STAGES: " . json_encode(\PixelHub\Services\OpportunityService::STAGES) . "\n";
    
} catch (Exception $e) {
    echo "❌ Erro no STAGES: " . $e->getMessage() . "\n";
}

echo "\n=== FIM ===\n";
