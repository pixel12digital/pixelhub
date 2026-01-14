<?php
/**
 * Script para verificar logs do Hub relacionados ao teste
 * Busca por correlation_id, HUB_* patterns e horário do teste
 */

$correlationId = '9858a507-cc4c-4632-8f92-462535eab504';
$testTime = '21:35'; // Horário aproximado do teste
$logFile = __DIR__ . '/logs/pixelhub.log';

echo "=== Verificando Logs do Hub ===\n";
echo "correlation_id: $correlationId\n";
echo "horário do teste: ~$testTime\n";
echo "arquivo de log: $logFile\n\n";

if (!file_exists($logFile)) {
    echo "❌ Arquivo de log não encontrado: $logFile\n";
    echo "Verificando outros locais possíveis...\n";
    
    $possiblePaths = [
        __DIR__ . '/storage/logs/pixelhub.log',
        __DIR__ . '/var/log/pixelhub.log',
        '/var/log/pixelhub.log',
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $logFile = $path;
            echo "✅ Encontrado em: $logFile\n";
            break;
        }
    }
    
    if (!file_exists($logFile)) {
        echo "\n⚠️  Arquivo de log não encontrado localmente.\n";
        echo "Execute no servidor:\n";
        echo "  docker logs --since 21:30 gateway-hub 2>&1 | grep -i '$correlationId\\|HUB_WEBHOOK_IN\\|HUB_MSG_SAVE\\|HUB_MSG_DROP' | tail -50\n";
        exit(1);
    }
}

// Lê as últimas 2000 linhas do log
$lines = file($logFile);
$totalLines = count($lines);
$relevantLines = [];

echo "Total de linhas no log: $totalLines\n";
echo "Analisando últimas 2000 linhas...\n\n";

// Busca por correlation_id
$foundCorrelation = false;
$foundWebhookIn = false;
$foundMsgSave = false;
$foundMsgDrop = false;

$startIndex = max(0, $totalLines - 2000);

for ($i = $startIndex; $i < $totalLines; $i++) {
    $line = $lines[$i];
    
    // Busca por correlation_id
    if (strpos($line, $correlationId) !== false) {
        $foundCorrelation = true;
        $relevantLines[] = [
            'line' => $i + 1,
            'content' => trim($line),
            'type' => 'correlation_id'
        ];
    }
    
    // Busca por HUB_WEBHOOK_IN próximo ao horário do teste
    if (strpos($line, 'HUB_WEBHOOK_IN') !== false && strpos($line, $testTime) !== false) {
        $foundWebhookIn = true;
        $relevantLines[] = [
            'line' => $i + 1,
            'content' => trim($line),
            'type' => 'HUB_WEBHOOK_IN'
        ];
    }
    
    // Busca por HUB_MSG_SAVE próximo ao horário
    if ((strpos($line, 'HUB_MSG_SAVE_OK') !== false || strpos($line, 'HUB_MSG_SAVE') !== false) 
        && strpos($line, $testTime) !== false) {
        $foundMsgSave = true;
        $relevantLines[] = [
            'line' => $i + 1,
            'content' => trim($line),
            'type' => 'HUB_MSG_SAVE'
        ];
    }
    
    // Busca por HUB_MSG_DROP próximo ao horário
    if (strpos($line, 'HUB_MSG_DROP') !== false && strpos($line, $testTime) !== false) {
        $foundMsgDrop = true;
        $relevantLines[] = [
            'line' => $i + 1,
            'content' => trim($line),
            'type' => 'HUB_MSG_DROP'
        ];
    }
}

// Busca por erros/exceções próximos ao horário
for ($i = $startIndex; $i < $totalLines; $i++) {
    $line = $lines[$i];
    if ((strpos($line, 'Exception') !== false || 
         strpos($line, 'Error') !== false || 
         strpos($line, 'Fatal') !== false) &&
        strpos($line, $testTime) !== false) {
        $relevantLines[] = [
            'line' => $i + 1,
            'content' => trim($line),
            'type' => 'ERROR'
        ];
    }
}

// Ordena por linha
usort($relevantLines, function($a, $b) {
    return $a['line'] <=> $b['line'];
});

// Exibe resultados
echo "=== Resultados ===\n\n";

if (empty($relevantLines)) {
    echo "❌ Nenhuma linha relevante encontrada nas últimas 2000 linhas.\n";
    echo "\nIsso pode significar:\n";
    echo "1. O webhook não chegou ao Hub\n";
    echo "2. Os logs estão em outro arquivo/local\n";
    echo "3. O horário está diferente (verificar timezone)\n";
    echo "\nExecute no servidor do Hub:\n";
    echo "  docker logs --since 21:30 gateway-hub 2>&1 | grep -i '$correlationId\\|HUB_WEBHOOK_IN\\|HUB_MSG_SAVE\\|HUB_MSG_DROP' | tail -50\n";
} else {
    echo "✅ Encontradas " . count($relevantLines) . " linhas relevantes:\n\n";
    
    foreach ($relevantLines as $entry) {
        $typeColor = '';
        switch ($entry['type']) {
            case 'correlation_id':
                $typeColor = '🔵';
                break;
            case 'HUB_WEBHOOK_IN':
                $typeColor = '🟢';
                break;
            case 'HUB_MSG_SAVE':
                $typeColor = '✅';
                break;
            case 'HUB_MSG_DROP':
                $typeColor = '⚠️';
                break;
            case 'ERROR':
                $typeColor = '❌';
                break;
        }
        
        echo sprintf(
            "%s [Linha %d] %s\n",
            $typeColor,
            $entry['line'],
            $entry['content']
        );
        echo "\n";
    }
    
    // Resumo
    echo "\n=== Resumo ===\n";
    echo ($foundCorrelation ? "✅" : "❌") . " correlation_id encontrado\n";
    echo ($foundWebhookIn ? "✅" : "❌") . " HUB_WEBHOOK_IN encontrado\n";
    echo ($foundMsgSave ? "✅" : "❌") . " HUB_MSG_SAVE encontrado\n";
    echo ($foundMsgDrop ? "⚠️" : "✅") . " HUB_MSG_DROP encontrado\n";
}

