<?php
// Verificar erro real no servidor
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

echo "=== VERIFICANDO ERRO REAL ===\n\n";

try {
    // Incluir classes necessárias
    require_once __DIR__ . '/src/Services/OpportunityService.php';
    
    // Testar com filtros vazios
    $filters = [
        'status' => null,
        'stage' => null,
        'product_id' => null,
        'responsible_user_id' => null,
        'search' => null,
        'source' => null,
    ];
    
    echo "Testando OpportunityService::list()...\n";
    $opportunities = \PixelHub\Services\OpportunityService::list($filters);
    
    echo "✅ Sucesso! " . count($opportunities) . " oportunidades\n";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    
    // Verificar se é erro de coluna
    if (strpos($e->getMessage(), 'Column not found') !== false) {
        echo "\n🔍 ERRO DE COLUNA DETECTADO!\n";
        
        // Verificar estrutura da consulta
        $db = \PixelHub\Core\DB::getConnection();
        
        echo "\nVerificando colunas na consulta:\n";
        
        // Testar consulta básica
        try {
            $stmt = $db->query("SELECT o.*, t.name as tenant_name, l.name as lead_name FROM opportunities o LEFT JOIN tenants t ON o.tenant_id = t.id LEFT JOIN leads l ON o.lead_id = l.id LIMIT 1");
            echo "✅ Consulta básica funciona\n";
        } catch (Exception $e2) {
            echo "❌ Erro na consulta básica: " . $e2->getMessage() . "\n";
        }
        
        // Testar com products
        try {
            $stmt = $db->query("SELECT o.*, p.label as product_label FROM opportunities o LEFT JOIN opportunity_products p ON o.product_id = p.id LIMIT 1");
            echo "✅ Consulta com products funciona\n";
        } catch (Exception $e2) {
            echo "❌ Erro na consulta com products: " . $e2->getMessage() . "\n";
        }
    }
}

echo "\n=== FIM ===\n";
