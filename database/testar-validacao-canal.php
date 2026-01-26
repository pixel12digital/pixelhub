<?php

/**
 * Script para testar a validação de canal "Pixel 12 Digital"
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Controllers/CommunicationHubController.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Controllers\CommunicationHubController;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();
$controller = new CommunicationHubController();

echo "=== Teste de Validação de Canal ===\n\n";

// Testa diferentes variações do nome
$testNames = [
    'Pixel 12 Digital',  // Com espaço entre Pixel e 12
    'Pixel12 Digital',   // Sem espaço entre Pixel e 12 (como está no banco)
    'pixel12digital',    // Tudo minúsculo sem espaços
    'PIXEL 12 DIGITAL',  // Tudo maiúsculo
];

foreach ($testNames as $testName) {
    echo "Testando: '{$testName}'\n";
    echo "----------------------------------------\n";
    
    // Usa reflexão para acessar método privado
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('validateGatewaySessionId');
    $method->setAccessible(true);
    
    // Testa sem tenant_id
    $result = $method->invoke($controller, $testName, null, $db);
    
    if ($result) {
        echo "✅ Canal encontrado (sem tenant_id):\n";
        echo "   Session ID: {$result['session_id']}\n";
        echo "   Tenant ID: " . ($result['tenant_id'] ?: 'NULL') . "\n";
        echo "   Is Enabled: " . ($result['is_enabled'] ? 'Sim' : 'Não') . "\n";
    } else {
        echo "❌ Canal NÃO encontrado (sem tenant_id)\n";
    }
    
    // Testa com tenant_id = 2
    $result2 = $method->invoke($controller, $testName, 2, $db);
    
    if ($result2) {
        echo "✅ Canal encontrado (com tenant_id=2):\n";
        echo "   Session ID: {$result2['session_id']}\n";
        echo "   Tenant ID: " . ($result2['tenant_id'] ?: 'NULL') . "\n";
        echo "   Is Enabled: " . ($result2['is_enabled'] ? 'Sim' : 'Não') . "\n";
    } else {
        echo "❌ Canal NÃO encontrado (com tenant_id=2)\n";
    }
    
    echo "\n";
}

echo "=== Fim do teste ===\n";

