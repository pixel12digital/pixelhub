<?php
echo "=== ÚLTIMOS ERROS NO LOG DO SERVIDOR ===\n\n";

$logFile = __DIR__ . '/logs/pixelhub.log';

if (!file_exists($logFile)) {
    die("Arquivo de log não encontrado: {$logFile}\n");
}

$lines = file($logFile);
$lastLines = array_slice($lines, -200); // Últimas 200 linhas

echo "Total de linhas no log: " . count($lines) . "\n";
echo "Analisando últimas 200 linhas...\n\n";

// Filtra apenas linhas com erro, Meta API, whatsapp_api ou sendViaMetaAPI
$relevantLines = [];
foreach ($lastLines as $line) {
    if (
        stripos($line, 'error') !== false ||
        stripos($line, 'exception') !== false ||
        stripos($line, 'fatal') !== false ||
        stripos($line, 'sendViaMetaAPI') !== false ||
        stripos($line, 'whatsapp_api') !== false ||
        stripos($line, 'Meta API') !== false ||
        stripos($line, 'template') !== false ||
        stripos($line, 'bfda7d8a49aa510a') !== false // Request ID do erro
    ) {
        $relevantLines[] = $line;
    }
}

if (empty($relevantLines)) {
    echo "Nenhum erro encontrado nas últimas 200 linhas.\n";
    echo "Mostrando últimas 50 linhas do log:\n\n";
    echo implode('', array_slice($lastLines, -50));
} else {
    echo "Erros encontrados (" . count($relevantLines) . " linhas):\n\n";
    foreach ($relevantLines as $line) {
        echo $line;
    }
}
