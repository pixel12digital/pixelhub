<?php

/**
 * Script para verificar logs recentes do webhook
 */

require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

echo "=== Verificando logs recentes do webhook ===\n\n";

// Lista de possíveis locais de log
$logDir = __DIR__ . '/../logs';
$projectLogFile = realpath($logDir) . '/pixelhub.log';
if ($projectLogFile === false) {
    $projectLogFile = $logDir . '/pixelhub.log';
}

$possibleLogs = [
    $projectLogFile,
    __DIR__ . '/../logs/php_errors.log',
    'C:/xampp/php/logs/php_error_log',
    'C:/xampp/apache/logs/error.log',
];

// Adiciona log do PHP se configurado
$phpErrorLog = ini_get('error_log');
if (!empty($phpErrorLog) && $phpErrorLog !== 'syslog') {
    $possibleLogs[] = $phpErrorLog;
}

// Procura logs que existem
$foundLogs = [];
foreach ($possibleLogs as $path) {
    if (file_exists($path)) {
        $foundLogs[] = $path;
    }
}

if (empty($foundLogs)) {
    echo "⚠️  Nenhum arquivo de log encontrado\n";
    echo "   Tentando criar log do projeto em: {$projectLogFile}\n";
    if (!file_exists($logDir)) {
        @mkdir($logDir, 0755, true);
    }
} else {
    echo "✅ Encontrados " . count($foundLogs) . " arquivo(s) de log:\n";
    foreach ($foundLogs as $logFile) {
        echo "   - {$logFile}\n";
    }
    echo "\n";
}

// Busca logs relacionados ao webhook
foreach ($foundLogs as $logFile) {
    echo "=== Analisando: " . basename($logFile) . " ===\n";
    
    if (!file_exists($logFile) || !is_readable($logFile)) {
        echo "   ⚠️  Arquivo não existe ou não é legível\n\n";
        continue;
    }
    
    // Lê últimas 1000 linhas do log
    $lines = file($logFile);
    if ($lines === false) {
        echo "   ⚠️  Erro ao ler arquivo\n\n";
        continue;
    }
    
    // Filtra linhas relacionadas ao webhook (últimas 2 horas)
    $webhookLines = [];
    $cutoffTime = time() - (2 * 60 * 60); // 2 horas atrás
    
    foreach ($lines as $line) {
        // Busca por padrões relacionados ao webhook
        if (
            stripos($line, 'webhook') !== false ||
            stripos($line, 'HUB_WEBHOOK') !== false ||
            stripos($line, 'WhatsAppWebhook') !== false ||
            stripos($line, 'WEBHOOK INSTRUMENTADO') !== false ||
            stripos($line, 'teste-1516') !== false ||
            stripos($line, 'teste-1459') !== false ||
            stripos($line, '554796164699') !== false
        ) {
            // Tenta extrair timestamp da linha
            if (preg_match('/(\d{4}-\d{2}-\d{2}[\s:]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $lineTime = strtotime($matches[1]);
                if ($lineTime && $lineTime >= $cutoffTime) {
                    $webhookLines[] = $line;
                }
            } else {
                // Se não tem timestamp, assume que é recente (últimas linhas)
                if (count($webhookLines) < 50) {
                    $webhookLines[] = $line;
                }
            }
        }
    }
    
    if (empty($webhookLines)) {
        echo "   ℹ️  Nenhuma linha relacionada ao webhook encontrada nas últimas 2 horas\n";
    } else {
        echo "   ✅ Encontradas " . count($webhookLines) . " linha(s) relacionada(s) ao webhook:\n";
        echo "   (Mostrando últimas 20 linhas)\n\n";
        
        $recentLines = array_slice($webhookLines, -20);
        foreach ($recentLines as $line) {
            echo "   " . rtrim($line) . "\n";
        }
    }
    
    echo "\n";
}

// Verifica logs de erro específicos
echo "=== Verificando erros recentes ===\n";
foreach ($foundLogs as $logFile) {
    $lines = file($logFile);
    if ($lines === false) continue;
    
    $errorLines = [];
    $cutoffTime = time() - (2 * 60 * 60);
    
    foreach ($lines as $line) {
        if (
            stripos($line, 'error') !== false ||
            stripos($line, 'exception') !== false ||
            stripos($line, 'fatal') !== false ||
            stripos($line, 'warning') !== false
        ) {
            if (preg_match('/(\d{4}-\d{2}-\d{2}[\s:]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
                $lineTime = strtotime($matches[1]);
                if ($lineTime && $lineTime >= $cutoffTime) {
                    $errorLines[] = $line;
                }
            } elseif (count($errorLines) < 20) {
                $errorLines[] = $line;
            }
        }
    }
    
    if (!empty($errorLines)) {
        echo "   ⚠️  Encontrados " . count($errorLines) . " erro(s) recente(s) em " . basename($logFile) . ":\n";
        $recentErrors = array_slice($errorLines, -10);
        foreach ($recentErrors as $line) {
            echo "   " . rtrim($line) . "\n";
        }
        echo "\n";
    }
}

echo "=== Fim da verificação de logs ===\n";

