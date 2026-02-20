<?php

/**
 * Teste focado na view opportunities/view.php
 */

define('ROOT_PATH', __DIR__);
require_once ROOT_PATH . '/vendor/autoload.php';

echo "=== Teste da view opportunities/view.php ===\n";

// 1. Verificar se a view existe
$viewPath = ROOT_PATH . '/views/opportunities/view.php';
if (!file_exists($viewPath)) {
    echo "❌ View não encontrada: {$viewPath}\n";
    exit;
}

echo "✅ View encontrada\n";

// 2. Verificar sintaxe da view
echo "2. Verificando sintaxe da view...\n";
$output = shell_exec("php -l {$viewPath} 2>&1");
if (strpos($output, 'No syntax errors') !== false) {
    echo "✅ Sintaxe OK\n";
} else {
    echo "❌ Erro de sintaxe:\n{$output}\n";
}

// 3. Verificar se a função getOriginDisplay está definida
echo "3. Verificando função getOriginDisplay...\n";
$content = file_get_contents($viewPath);
if (preg_match('/function\s+getOriginDisplay\s*\(/', $content)) {
    echo "✅ Função getOriginDisplay() encontrada\n";
    
    // Extrair a função para teste
    if (preg_match('/function\s+getOriginDisplay\s*\([^{]*\)\s*{([^}]*)}/s', $content, $matches)) {
        $functionCode = "function getOriginDisplay(\$origin) {$matches[1]}";
        eval($functionCode);
        
        if (function_exists('getOriginDisplay')) {
            echo "✅ getOriginDisplay() executável\n";
            
            // Testar
            $testCases = ['unknown', '', null, 'whatsapp', 'site'];
            foreach ($testCases as $test) {
                $result = getOriginDisplay($test);
                echo "   📄 getOriginDisplay(" . var_export($test, true) . ") = " . var_export($result, true) . "\n";
            }
        }
    }
} else {
    echo "❌ Função getOriginDisplay() NÃO encontrada\n";
}

// 4. Verificar se há chamadas a classes que podem não existir
echo "4. Verificando chamadas a classes na view...\n";
if (preg_match_all('/new\s+\\\w+\\\\(\w+)/', $content, $matches)) {
    foreach ($matches[1] as $className) {
        $fullClass = "PixelHub\\Services\\{$className}";
        if (class_exists($fullClass)) {
            echo "   ✅ {$fullClass} - existe\n";
        } else {
            echo "   ❌ {$fullClass} - NÃO existe\n";
        }
    }
}

// 5. Verificar se há variáveis não definidas
echo "5. Verificando variáveis críticas na view...\n";
$criticalVars = ['opp', 'trackingInfo', 'origins', 'stages'];
foreach ($criticalVars as $var) {
    if (strpos($content, '$' . $var) !== false) {
        echo "   📄 \${$var} - usada na view\n";
    }
}

echo "\n=== Teste concluído ===\n";
