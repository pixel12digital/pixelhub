<?php

/**
 * Script para diagnosticar erro em /opportunities
 * Executar no servidor: php diagnose_opportunities.php
 */

// Carrega ambiente
define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/src/Core/Env.php';
require_once ROOT_PATH . '/src/Core/DB.php';

use PixelHub\Core\DB;

echo "=== Diagnóstico de /opportunities ===\n\n";

// 1. Verificar classes críticas
echo "1. Verificando classes críticas:\n";

$classesToCheck = [
    'PixelHub\Controllers\OpportunitiesController',
    'PixelHub\Services\OpportunityService',
    'PixelHub\Services\OpportunityProductService',
    'PixelHub\Services\TrackingDetectionService'
];

foreach ($classesToCheck as $class) {
    if (class_exists($class)) {
        echo "   ✅ {$class} - OK\n";
    } else {
        echo "   ❌ {$class} - NÃO ENCONTRADA\n";
        
        // Tentar encontrar o arquivo
        $className = substr($class, strrpos($class, '\\') + 1);
        $possibleFiles = [
            "src/Controllers/{$className}.php",
            "src/Services/{$className}.php"
        ];
        
        foreach ($possibleFiles as $file) {
            if (file_exists(ROOT_PATH . '/' . $file)) {
                echo "      📁 Arquivo existe: {$file}\n";
            }
        }
    }
}

echo "\n";

// 2. Verificar arquivos fisicamente
echo "2. Verificando arquivos fisicamente:\n";

$filesToCheck = [
    'src/Controllers/OpportunitiesController.php',
    'src/Services/OpportunityService.php',
    'src/Services/OpportunityProductService.php',
    'src/Services/TrackingDetectionService.php'
];

foreach ($filesToCheck as $file) {
    $fullPath = ROOT_PATH . '/' . $file;
    if (file_exists($fullPath)) {
        echo "   ✅ {$file} - EXISTE\n";
        
        // Verificar namespace/classe
        $content = file_get_contents($fullPath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            echo "      Namespace: " . trim($matches[1]) . "\n";
        }
        
        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            echo "      Classe: " . $matches[1] . "\n";
        }
    } else {
        echo "   ❌ {$file} - NÃO EXISTE\n";
    }
}

echo "\n";

// 3. Verificar se TrackingDetectionService está sendo chamado
echo "3. Verificando referências a TrackingDetectionService:\n";

$grepCmd = "grep -r 'TrackingDetectionService' " . ROOT_PATH . "/src --include='*.php' | head -10";
exec($grepCmd, $output, $returnVar);

if ($returnVar === 0 && !empty($output)) {
    foreach ($output as $line) {
        echo "   📄 {$line}\n";
    }
} else {
    echo "   ℹ️  Nenhuma referência encontrada\n";
}

echo "\n";

// 4. Verificar logs recentes
echo "4. Verificando logs recentes:\n";

$logDirs = [
    ROOT_PATH . '/logs',
    ROOT_PATH . '/storage/logs',
    ROOT_PATH . '/tmp'
];

foreach ($logDirs as $logDir) {
    if (is_dir($logDir)) {
        echo "   📁 Verificando: {$logDir}\n";
        
        $files = glob($logDir . '/*.log');
        foreach ($files as $file) {
            if (file_exists($file)) {
                $mtime = filemtime($file);
                $size = filesize($file);
                echo "      📄 " . basename($file) . " (" . date('Y-m-d H:i:s', $mtime) . ", {$size} bytes)\n";
                
                // Buscar erros recentes
                $content = file_get_contents($file);
                if (preg_match('/(Fatal error|Uncaught|Class.*not found)/i', $content)) {
                    echo "         ⚠️  Contém erros fatais\n";
                }
            }
        }
    }
}

echo "\n";

// 5. Testar carregamento do controller
echo "5. Testando carregamento do OpportunitiesController:\n";

try {
    require_once ROOT_PATH . '/src/Controllers/OpportunitiesController.php';
    echo "   ✅ OpportunitiesController carregado com sucesso\n";
    
    // Verificar se o método index existe
    if (method_exists('PixelHub\Controllers\OpportunitiesController', 'index')) {
        echo "   ✅ Método index() existe\n";
    } else {
        echo "   ❌ Método index() NÃO encontrado\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Erro ao carregar: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "   ❌ Erro fatal ao carregar: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";

// 6. Verificar autoload (se usar composer)
echo "6. Verificando autoload:\n";

$composerJson = ROOT_PATH . '/composer.json';
if (file_exists($composerJson)) {
    echo "   ✅ composer.json existe\n";
    
    // Verificar se vendor/autoload.php existe
    $vendorAutoload = ROOT_PATH . '/vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        echo "   ✅ vendor/autoload.php existe\n";
    } else {
        echo "   ❌ vendor/autoload.php NÃO existe - execute 'composer install'\n";
    }
} else {
    echo "   ℹ️  Não usa composer (autoload manual)\n";
    
    // Verificar autoloader manual
    $autoloadFile = ROOT_PATH . '/config/autoload.php';
    if (file_exists($autoloadFile)) {
        echo "   ✅ Autoload manual encontrado: config/autoload.php\n";
    }
}

echo "\n=== Diagnóstico concluído ===\n";
