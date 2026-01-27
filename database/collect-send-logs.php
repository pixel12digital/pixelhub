<?php
/**
 * Script auxiliar para coletar logs relevantes do método send()
 * 
 * USO: php database/collect-send-logs.php [caminho-do-log]
 * 
 * Se não passar caminho, tenta detectar automaticamente:
 * - /var/log/php/error.log
 * - /var/log/apache2/error.log
 * - /var/log/nginx/error.log
 * - error_log do PHP (se configurado)
 */

$logPath = $argv[1] ?? null;

// Tenta detectar automaticamente se não foi passado
if (!$logPath) {
    $possiblePaths = [
        '/var/log/php/error.log',
        '/var/log/apache2/error.log',
        '/var/log/nginx/error.log',
        ini_get('error_log') ?: null,
    ];
    
    foreach ($possiblePaths as $path) {
        if ($path && file_exists($path) && is_readable($path)) {
            $logPath = $path;
            break;
        }
    }
}

if (!$logPath || !file_exists($logPath)) {
    echo "❌ ERRO: Arquivo de log não encontrado.\n";
    echo "Uso: php database/collect-send-logs.php [caminho-do-log]\n";
    echo "\nTentou encontrar em:\n";
    foreach ($possiblePaths ?? [] as $path) {
        echo "  - " . ($path ?: 'N/A') . "\n";
    }
    exit(1);
}

echo "=== Coletando logs do método send() ===\n\n";
echo "Arquivo: {$logPath}\n";
echo "Últimas 500 linhas (ou use tail -f no servidor)\n\n";

// Lê as últimas 500 linhas do log
$lines = file($logPath);
$recentLines = array_slice($lines, -500);

$found = false;
$inBlock = false;
$blockLines = [];
$stampFound = false;
$traceFound = false;
$returnPointFound = false;

foreach ($recentLines as $line) {
    // Procura pelo stamp
    if (strpos($line, 'SEND_HANDLER_STAMP=15a1023') !== false) {
        $stampFound = true;
        $found = true;
        $inBlock = true;
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "✅ STAMP ENCONTRADO - Iniciando coleta do bloco\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $blockLines = [];
    }
    
    // Se está no bloco, coleta linhas relevantes
    if ($inBlock) {
        // Coleta todas as linhas do CommunicationHub::send
        if (strpos($line, '[CommunicationHub::send]') !== false) {
            $blockLines[] = $line;
        }
        
        // Para quando encontrar um bloco completo (stamp até fim do request)
        // Assumindo que o bloco termina quando não há mais logs do send por 10 linhas
        // ou quando encontra outro stamp
        if (strpos($line, 'SEND_HANDLER_STAMP=15a1023') !== false && count($blockLines) > 0) {
            // Novo stamp encontrado, processa o bloco anterior
            processBlock($blockLines);
            $blockLines = [];
        }
    }
}

// Processa último bloco se houver
if ($inBlock && !empty($blockLines)) {
    processBlock($blockLines);
}

if (!$found) {
    echo "❌ STAMP não encontrado nas últimas 500 linhas.\n";
    echo "\nPossíveis causas:\n";
    echo "  1. O código não está sendo executado (rota errada, deploy não refletiu, OPcache)\n";
    echo "  2. O log está em outro arquivo\n";
    echo "  3. O request ainda não foi feito\n";
    echo "\nTente:\n";
    echo "  - Fazer uma requisição POST para /communication-hub/send\n";
    echo "  - Verificar se o log está no arquivo correto\n";
    echo "  - Verificar se OPcache está ativo e limpar se necessário\n";
}

function processBlock(array $lines) {
    global $stampFound, $traceFound, $returnPointFound;
    
    $relevantLines = [];
    $inTrace = false;
    $inReturnPoint = false;
    $inResolution = false;
    
    foreach ($lines as $line) {
        // STAMP
        if (strpos($line, 'SEND_HANDLER_STAMP') !== false || 
            strpos($line, '__FILE__') !== false || 
            strpos($line, '__LINE__') !== false) {
            $relevantLines[] = $line;
            $stampFound = true;
        }
        
        // TRACE
        if (strpos($line, 'TRACE channel_id') !== false) {
            $inTrace = true;
            $relevantLines[] = "\n--- TRACE INÍCIO ---\n";
        }
        if ($inTrace) {
            $relevantLines[] = $line;
            if (strpos($line, 'FIM TRACE') !== false || strpos($line, '===== FIM TRACE') !== false) {
                $inTrace = false;
                $traceFound = true;
                $relevantLines[] = "--- TRACE FIM ---\n\n";
            }
        }
        
        // RESOLUÇÃO
        if (strpos($line, 'RESOLUÇÃO CANAL') !== false) {
            $inResolution = true;
            $relevantLines[] = "\n--- RESOLUÇÃO INÍCIO ---\n";
        }
        if ($inResolution) {
            $relevantLines[] = $line;
            if (strpos($line, 'FIM RESOLUÇÃO') !== false || strpos($line, '===== FIM RESOLUÇÃO') !== false) {
                $inResolution = false;
                $relevantLines[] = "--- RESOLUÇÃO FIM ---\n\n";
            }
        }
        
        // RETURN_POINT
        if (strpos($line, 'RETURN_POINT=') !== false) {
            $inReturnPoint = true;
            $relevantLines[] = "\n--- RETURN_POINT INÍCIO ---\n";
        }
        if ($inReturnPoint) {
            $relevantLines[] = $line;
            if (strpos($line, 'FIM RETURN_POINT') !== false || strpos($line, '===== FIM RETURN_POINT') !== false) {
                $inReturnPoint = false;
                $returnPointFound = true;
                $relevantLines[] = "--- RETURN_POINT FIM ---\n\n";
            }
        }
        
        // validateGatewaySessionId (importante para diagnóstico)
        if (strpos($line, 'validateGatewaySessionId') !== false && 
            (strpos($line, 'Canal encontrado') !== false || strpos($line, 'não encontrado') !== false)) {
            $relevantLines[] = $line;
        }
    }
    
    if (!empty($relevantLines)) {
        echo implode('', $relevantLines);
        echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "📊 RESUMO DO BLOCO:\n";
        echo "  Stamp: " . ($stampFound ? '✅' : '❌') . "\n";
        echo "  TRACE: " . ($traceFound ? '✅' : '❌') . "\n";
        echo "  RESOLUÇÃO: " . ($inResolution ? '✅' : '❌') . "\n";
        echo "  RETURN_POINT: " . ($returnPointFound ? '✅' : '❌') . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
}

echo "\n✅ Coleta concluída!\n";
echo "\n📋 O que enviar para análise:\n";
echo "  1. Output do script fix-tenant-25-channel.php (UPDATE/INSERT, ANTES/DEPOIS)\n";
echo "  2. Este output completo (stamp + TRACE + RETURN_POINT)\n";
echo "  3. Response JSON do Network tab (se ainda vier 'Pixel12 Digital')\n";

