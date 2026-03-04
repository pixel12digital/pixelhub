<?php
/**
 * Script de diagnóstico para testar submitToMeta()
 * Captura o erro real que está causando o 500
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use PixelHub\Services\MetaTemplateService;

// Habilita exibição de erros
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TESTE DE SUBMISSÃO DE TEMPLATE PARA META ===\n\n";

// ID do template que está tentando enviar
$templateId = 1;

echo "1. Buscando template ID {$templateId}...\n";
$template = MetaTemplateService::getById($templateId);

if (!$template) {
    die("❌ Template não encontrado\n");
}

echo "✓ Template encontrado: {$template['template_name']}\n";
echo "  Status: {$template['status']}\n";
echo "  Tenant ID: {$template['tenant_id']}\n\n";

echo "2. Testando submitToMeta()...\n";

try {
    $result = MetaTemplateService::submitToMeta($templateId);
    
    echo "\n=== RESULTADO ===\n";
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    echo "Message: {$result['message']}\n";
    
    if (isset($result['meta_template_id'])) {
        echo "Meta Template ID: {$result['meta_template_id']}\n";
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO CAPTURADO:\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO TESTE ===\n";
