<?php

/**
 * Script focado em testar apenas o método show() que está causando erro
 */

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/vendor/autoload.php';

echo "=== Teste focado no método show() ===\n";

try {
    // Simular sessão autenticada
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Test User';
    
    // Simular requisição para view
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/opportunities/view?id=3';
    $_GET['id'] = '3';
    
    echo "1. Instanciando OpportunitiesController...\n";
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    echo "   ✅ Controller instanciado\n";
    
    echo "2. Executando método show()...\n";
    
    // Capturar qualquer erro
    ob_start();
    $controller->show();
    $output = ob_get_clean();
    
    echo "   ✅ Método show() executado\n";
    echo "   📄 Output length: " . strlen($output) . " bytes\n";
    
    // Verificar se há redirect para login
    if (strpos($output, 'login') !== false) {
        echo "   ⚠️  Redirect para login detectado\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro no método show(): " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   ❌ Erro fatal no método show(): " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Teste concluído ===\n";
