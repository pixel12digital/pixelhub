<?php

/**
 * Script para testar URLs específicas e capturar erros
 * Executar no servidor: php test_opportunities_urls.php
 */

// Carrega ambiente completo
define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Auth;

echo "=== Teste de URLs /opportunities ===\n\n";

// 1. Testar carregamento do controller
echo "1. Testando carregamento do OpportunitiesController:\n";

try {
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    echo "   ✅ OpportunitiesController instanciado com sucesso\n";
    
    // Verificar método index
    if (method_exists($controller, 'index')) {
        echo "   ✅ Método index() existe\n";
    } else {
        echo "   ❌ Método index() NÃO encontrado\n";
    }
    
    // Verificar método show
    if (method_exists($controller, 'show')) {
        echo "   ✅ Método show() existe\n";
    } else {
        echo "   ❌ Método show() NÃO encontrado\n";
    }
    
    // Verificar método updateOrigin
    if (method_exists($controller, 'updateOrigin')) {
        echo "   ✅ Método updateOrigin() existe\n";
    } else {
        echo "   ❌ Método updateOrigin() NÃO encontrado\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro ao instanciar controller: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   ❌ Erro fatal ao instanciar controller: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// 2. Testar método index diretamente
echo "2. Testando método index() diretamente:\n";

try {
    // Simular ambiente de requisição
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/opportunities';
    
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    
    // Capturar output (em vez de renderizar view)
    ob_start();
    $controller->index();
    $output = ob_get_clean();
    
    echo "   ✅ Método index() executado com sucesso\n";
    echo "   📄 Output length: " . strlen($output) . " bytes\n";
    
} catch (Exception $e) {
    echo "   ❌ Erro no método index(): " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   ❌ Erro fatal no método index(): " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// 3. Testar método show diretamente
echo "3. Testando método show() diretamente:\n";

try {
    // Simular requisição para view
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/opportunities/view?id=3';
    $_GET['id'] = '3';
    
    $controller = new \PixelHub\Controllers\OpportunitiesController();
    
    // Capturar output
    ob_start();
    $controller->show();
    $output = ob_get_clean();
    
    echo "   ✅ Método show() executado com sucesso\n";
    echo "   📄 Output length: " . strlen($output) . " bytes\n";
    
} catch (Exception $e) {
    echo "   ❌ Erro no método show(): " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   ❌ Erro fatal no método show(): " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// 4. Verificar se a view existe
echo "4. Verificando arquivos de view:\n";

$viewFiles = [
    'views/opportunities/index.php',
    'views/opportunities/view.php',
    'views/layout/main.php'
];

foreach ($viewFiles as $viewFile) {
    $fullPath = ROOT_PATH . '/' . $viewFile;
    if (file_exists($fullPath)) {
        echo "   ✅ {$viewFile} - EXISTE\n";
        
        // Verificar se há erros de sintaxe
        $content = file_get_contents($fullPath);
        if (strpos($content, 'getOriginDisplay') !== false) {
            echo "      📄 Contém getOriginDisplay()\n";
        }
        
        // Verificar se há chamadas a classes
        if (preg_match('/new\s+\\\w+\\\\/', $content)) {
            echo "      📄 Contém instanciações de classes\n";
        }
    } else {
        echo "   ❌ {$viewFile} - NÃO EXISTE\n";
    }
}

echo "\n";

// 5. Verificar helper getOriginDisplay
echo "5. Testando helper getOriginDisplay:\n";

// Carregar a view para testar o helper
$viewPath = ROOT_PATH . '/views/opportunities/view.php';
if (file_exists($viewPath)) {
    $content = file_get_contents($viewPath);
    
    // Verificar se a função está definida
    if (preg_match('/function\s+getOriginDisplay\s*\(/', $content)) {
        echo "   ✅ Função getOriginDisplay() definida na view\n";
        
        // Testar a função
        eval($content); // Isso vai executar o PHP da view
        
        if (function_exists('getOriginDisplay')) {
            echo "   ✅ getOriginDisplay() disponível para teste\n";
            
            // Testar com diferentes valores
            $testCases = ['unknown', '', null, 'whatsapp', 'site'];
            foreach ($testCases as $test) {
                $result = getOriginDisplay($test);
                echo "      📄 getOriginDisplay(" . var_export($test, true) . ") = " . var_export($result, true) . "\n";
            }
        } else {
            echo "   ❌ getOriginDisplay() não disponível após eval\n";
        }
    } else {
        echo "   ❌ Função getOriginDisplay() NÃO definida na view\n";
    }
} else {
    echo "   ❌ View não encontrada para testar helper\n";
}

echo "\n";

// 6. Verificar logs mais recentes
echo "6. Verificando logs recentes (últimas 10 linhas):\n";

$logFile = ROOT_PATH . '/logs/pixelhub.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $recentLines = array_slice($lines, -10);
    
    foreach ($recentLines as $line) {
        echo "   📄 " . trim($line) . "\n";
    }
} else {
    echo "   ❌ Arquivo de log não encontrado\n";
}

echo "\n=== Teste concluído ===\n";
