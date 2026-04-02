<?php
/**
 * Script para verificar logs de erro relacionados a templates
 */

// Verificar log do PHP
$phpErrorLog = 'C:\xampp\php\logs\php_error_log';
$pixelhubLog = __DIR__ . '/logs/pixelhub.log';

echo "=== VERIFICANDO LOGS DE ERRO ===\n\n";

// 1. Verificar log PHP
if (file_exists($phpErrorLog)) {
    echo "1. Últimas 50 linhas do log PHP:\n";
    echo str_repeat('-', 80) . "\n";
    
    $lines = file($phpErrorLog);
    $lastLines = array_slice($lines, -50);
    
    foreach ($lastLines as $line) {
        if (stripos($line, 'template') !== false || 
            stripos($line, 'whatsapp') !== false ||
            stripos($line, 'fatal') !== false ||
            stripos($line, 'error') !== false) {
            echo $line;
        }
    }
    echo str_repeat('-', 80) . "\n\n";
} else {
    echo "1. Log PHP não encontrado em: {$phpErrorLog}\n\n";
}

// 2. Verificar log PixelHub
if (file_exists($pixelhubLog)) {
    echo "2. Últimas 50 linhas do log PixelHub:\n";
    echo str_repeat('-', 80) . "\n";
    
    $lines = file($pixelhubLog);
    $lastLines = array_slice($lines, -50);
    
    foreach ($lastLines as $line) {
        if (stripos($line, 'template') !== false || 
            stripos($line, 'whatsapp') !== false ||
            stripos($line, 'error') !== false) {
            echo $line;
        }
    }
    echo str_repeat('-', 80) . "\n\n";
} else {
    echo "2. Log PixelHub não encontrado em: {$pixelhubLog}\n\n";
}

// 3. Verificar Apache error log
$apacheLog = 'C:\xampp\apache\logs\error.log';
if (file_exists($apacheLog)) {
    echo "3. Últimas 30 linhas do log Apache:\n";
    echo str_repeat('-', 80) . "\n";
    
    $lines = file($apacheLog);
    $lastLines = array_slice($lines, -30);
    
    foreach ($lastLines as $line) {
        echo $line;
    }
    echo str_repeat('-', 80) . "\n\n";
} else {
    echo "3. Log Apache não encontrado em: {$apacheLog}\n\n";
}

echo "=== VERIFICAÇÃO CONCLUÍDA ===\n";
