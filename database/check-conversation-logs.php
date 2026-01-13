<?php

/**
 * Script para verificar logs relacionados a criação de conversas
 */

echo "=== VERIFICAÇÃO DE LOGS - CONVERSATION UPSERT ===\n\n";

// Lista de possíveis locais de log
$possibleLogs = [
    __DIR__ . '/../logs/pixelhub.log',
    ini_get('error_log'),
    'C:/xampp/php/logs/php_error_log',
    'C:/xampp/apache/logs/error.log',
];

$logFile = null;
foreach ($possibleLogs as $path) {
    if ($path && file_exists($path)) {
        $logFile = $path;
        break;
    }
}

if (!$logFile) {
    echo "⚠️  Nenhum arquivo de log encontrado nos locais padrão.\n";
    echo "   Verifique manualmente:\n";
    foreach ($possibleLogs as $path) {
        if ($path) {
            echo "   - {$path}\n";
        }
    }
    exit(1);
}

echo "📄 Arquivo de log encontrado: {$logFile}\n\n";

// Lê últimas 100 linhas do log
$lines = file($logFile);
$recentLines = array_slice($lines, -100);

// Filtra linhas relacionadas a CONVERSATION UPSERT
$conversationLogs = [];
foreach ($recentLines as $line) {
    if (stripos($line, 'CONVERSATION UPSERT') !== false || 
        stripos($line, 'extractChannelInfo') !== false ||
        stripos($line, 'extractChannelIdFromPayload') !== false) {
        $conversationLogs[] = trim($line);
    }
}

if (empty($conversationLogs)) {
    echo "⚠️  Nenhum log de CONVERSATION UPSERT encontrado nas últimas 100 linhas.\n";
    echo "   Isso pode indicar que:\n";
    echo "   1. O ConversationService::resolveConversation() não está sendo chamado\n";
    echo "   2. Os logs não estão sendo escritos\n";
    echo "   3. Os logs estão em outro arquivo\n\n";
    
    echo "📋 Últimas 10 linhas do log:\n";
    foreach (array_slice($recentLines, -10) as $line) {
        echo "   " . trim($line) . "\n";
    }
} else {
    echo "✅ Encontrados " . count($conversationLogs) . " log(s) relacionados:\n\n";
    foreach ($conversationLogs as $log) {
        echo "   {$log}\n";
    }
}

echo "\n";

