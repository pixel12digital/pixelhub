<?php
/**
 * Endpoint temporário para visualizar logs de debug
 * REMOVER EM PRODUÇÃO
 */

$logDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
$logFile = realpath($logDir);
if ($logFile === false) {
    $logFile = $logDir;
}
$logFile = $logFile . DIRECTORY_SEPARATOR . 'pixelhub.log';

if (!file_exists($logFile)) {
    die("Arquivo de log não encontrado: {$logFile}\n\nAcesse o site primeiro para gerar logs.");
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Logs do Pixel Hub ===\n\n";
echo "Últimas 100 linhas:\n\n";
echo file_get_contents($logFile);

