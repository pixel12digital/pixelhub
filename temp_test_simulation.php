<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

echo "=== TESTE COMPLETO DA SIMULAÇÃO ===\n\n";

// Teste 1: Service existe?
echo "1. Verificando se TemplateInspectorService existe...\n";
if (class_exists('\PixelHub\Services\TemplateInspectorService')) {
    echo "   ✅ Classe existe\n\n";
} else {
    echo "   ❌ Classe NÃO existe\n\n";
    exit(1);
}

// Teste 2: Método simulateButtonClick existe?
echo "2. Verificando método simulateButtonClick...\n";
if (method_exists('\PixelHub\Services\TemplateInspectorService', 'simulateButtonClick')) {
    echo "   ✅ Método existe\n\n";
} else {
    echo "   ❌ Método NÃO existe\n\n";
    exit(1);
}

// Teste 3: Executar simulação
echo "3. Executando simulação para botão 'btn_quero_conhecer'...\n";
try {
    $result = \PixelHub\Services\TemplateInspectorService::simulateButtonClick(1, 'btn_quero_conhecer', null);
    echo "   ✅ Simulação executada\n\n";
    echo "Resultado:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (\Exception $e) {
    echo "   ❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n\n=== TESTE DO ENDPOINT API ===\n\n";

// Teste 4: Controller existe?
echo "4. Verificando WhatsAppTemplateController...\n";
if (class_exists('\PixelHub\Controllers\WhatsAppTemplateController')) {
    echo "   ✅ Controller existe\n";
    
    if (method_exists('\PixelHub\Controllers\WhatsAppTemplateController', 'simulateButton')) {
        echo "   ✅ Método simulateButton existe\n\n";
    } else {
        echo "   ❌ Método simulateButton NÃO existe\n\n";
    }
} else {
    echo "   ❌ Controller NÃO existe\n\n";
}

// Teste 5: Verificar rotas
echo "5. Verificando arquivo de rotas...\n";
$indexContent = file_get_contents('public/index.php');
if (strpos($indexContent, '/api/templates/simulate-button') !== false) {
    echo "   ✅ Rota /api/templates/simulate-button está registrada\n";
} else {
    echo "   ❌ Rota NÃO está registrada\n";
}

if (strpos($indexContent, '/api/templates/inspector-data') !== false) {
    echo "   ✅ Rota /api/templates/inspector-data está registrada\n";
} else {
    echo "   ❌ Rota NÃO está registrada\n";
}
