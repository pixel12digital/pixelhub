<?php
/**
 * Script para ler últimas linhas do log relacionadas ao erro 400
 */

$logFile = __DIR__ . '/logs/pixelhub.log';

if (!file_exists($logFile)) {
    echo "❌ Arquivo de log não encontrado: {$logFile}\n";
    exit(1);
}

echo "=== ÚLTIMAS 100 LINHAS DO LOG (FILTRADAS) ===\n\n";

// Lê arquivo de trás para frente (mais eficiente para arquivos grandes)
$lines = [];
$handle = fopen($logFile, 'r');

if ($handle) {
    // Pula para o final do arquivo
    fseek($handle, -1, SEEK_END);
    $pos = ftell($handle);
    $line = '';
    $lineCount = 0;
    
    // Lê de trás para frente até ter 100 linhas relevantes
    while ($pos >= 0 && $lineCount < 100) {
        fseek($handle, $pos, SEEK_SET);
        $char = fgetc($handle);
        
        if ($char === "\n" || $pos === 0) {
            if ($pos === 0) {
                $line = $char . $line;
            }
            
            // Filtra apenas linhas relevantes
            if (stripos($line, 'CommunicationHub::send') !== false ||
                stripos($line, 'ERRO 400') !== false ||
                stripos($line, 'channel_id') !== false ||
                stripos($line, 'tenant_id') !== false ||
                stripos($line, 'STAGE=') !== false ||
                stripos($line, 'POST DATA:') !== false) {
                
                $lines[] = $line;
                $lineCount++;
            }
            
            $line = '';
        } else {
            $line = $char . $line;
        }
        
        $pos--;
    }
    
    fclose($handle);
    
    // Inverte para ordem cronológica
    $lines = array_reverse($lines);
    
    foreach ($lines as $line) {
        echo $line . "\n";
    }
} else {
    echo "❌ Não foi possível abrir o arquivo de log\n";
}

echo "\n=== FIM DO LOG ===\n";
