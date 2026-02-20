<?php

/**
 * Teste simples da view
 */

define('ROOT_PATH', __DIR__);

echo "=== Teste simples da view ===\n";

$viewPath = ROOT_PATH . '/views/opportunities/view.php';
if (!file_exists($viewPath)) {
    echo "❌ View não encontrada\n";
    exit;
}

echo "✅ View encontrada\n";

// Verificar sintaxe
$output = shell_exec("php -l {$viewPath} 2>&1");
echo "Sintaxe: " . (strpos($output, 'No syntax errors') !== false ? '✅ OK' : '❌ Erro') . "\n";

// Verificar getOriginDisplay
$content = file_get_contents($viewPath);
if (strpos($content, 'function getOriginDisplay') !== false) {
    echo "✅ Função getOriginDisplay encontrada\n";
} else {
    echo "❌ Função getOriginDisplay não encontrada\n";
}

// Verificar se há $this->getOriginDisplay
if (strpos($content, '$this->getOriginDisplay') !== false) {
    echo "❌ Ainda existe chamada \$this->getOriginDisplay\n";
} else {
    echo "✅ Não há chamada \$this->getOriginDisplay\n";
}

echo "\n=== Teste concluído ===\n";
