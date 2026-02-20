<?php
// Simular exatamente o que o OpportunitiesController faz
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

echo "=== SIMULANDO OPPORTUNITIESCONTROLLER ===\n\n";

try {
    // Simula os filtros
    $filters = [
        'status' => null,
        'stage' => null,
        'product_id' => null,
        'responsible_user_id' => null,
        'search' => null,
        'source' => null, // Sem filtro para testar
    ];

    echo "Filtros: " . json_encode($filters) . "\n\n";

    // Chama o OpportunityService::list()
    $opportunities = \PixelHub\Services\OpportunityService::list($filters);
    
    echo "✅ Sucesso! " . count($opportunities) . " oportunidades encontradas\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
