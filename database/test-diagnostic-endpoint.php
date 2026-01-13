<?php

/**
 * Script para testar diretamente o endpoint de diagnóstico
 * 
 * Uso: php database/test-diagnostic-endpoint.php
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Core\Auth;
use PixelHub\Controllers\DiagnosticController;

echo "=== Teste do Endpoint de Diagnóstico ===\n\n";

try {
    Env::load();
    
    // Simula autenticação (precisa estar logado)
    // Se não estiver logado, vai dar erro de autenticação
    if (!Auth::check()) {
        echo "⚠️  Usuário não autenticado. Simulando autenticação...\n";
        echo "   (Em produção, você precisa estar logado)\n\n";
    }
    
    // Simula POST data
    $_POST['thread_id'] = 'whatsapp_31';
    $_POST['test_message'] = 'teste whatsapp_31';
    $_POST['test_type'] = 'resolve_channel';
    
    echo "Dados do teste:\n";
    echo "  Thread ID: {$_POST['thread_id']}\n";
    echo "  Test Type: {$_POST['test_type']}\n";
    echo "  Test Message: {$_POST['test_message']}\n\n";
    
    echo "=== Executando Diagnóstico ===\n\n";
    
    // Captura output
    ob_start();
    
    try {
        $controller = new DiagnosticController();
        $controller->runCommunicationDiagnostic();
        
        // Se chegou aqui, não deveria (deveria ter dado exit)
        $output = ob_get_clean();
        echo "Output capturado:\n";
        echo str_repeat("-", 80) . "\n";
        echo $output;
        echo str_repeat("-", 80) . "\n\n";
        
        // Tenta decodificar como JSON
        $json = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "✓ JSON válido!\n\n";
            echo "Conteúdo:\n";
            echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo "✗ JSON inválido!\n";
            echo "Erro: " . json_last_error_msg() . "\n";
            echo "Primeiros 500 caracteres:\n";
            echo substr($output, 0, 500) . "\n";
        }
    } catch (\Throwable $e) {
        $output = ob_get_clean();
        
        echo "✗ Exceção capturada:\n";
        echo "  Tipo: " . get_class($e) . "\n";
        echo "  Mensagem: " . $e->getMessage() . "\n";
        echo "  Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
        
        if (!empty($output)) {
            echo "Output antes do erro:\n";
            echo str_repeat("-", 80) . "\n";
            echo $output;
            echo str_repeat("-", 80) . "\n\n";
        }
        
        echo "Stack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Erro ao executar teste: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n✓ Teste concluído!\n";

