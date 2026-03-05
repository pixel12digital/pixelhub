<?php
// Diagnóstico: últimas 50 linhas do PHP error log
$logFile = ini_get('error_log');
echo "<pre>error_log file: $logFile\n\n";

if ($logFile && file_exists($logFile)) {
    $lines = file($logFile);
    $total = count($lines);
    $last = array_slice($lines, max(0, $total - 80));
    // Filtra apenas linhas relacionadas ao CommunicationHub ou send
    foreach ($last as $line) {
        if (stripos($line, 'CommunicationHub') !== false || stripos($line, 'EXCEPTION') !== false || stripos($line, 'TypeError') !== false || stripos($line, 'Error') !== false) {
            echo htmlspecialchars($line);
        }
    }
} else {
    // tenta apache error log
    $apacheLog = '/var/log/apache2/error.log';
    $phpFpmLog = '/var/log/php-fpm/error.log';
    $altLog = '/var/log/php8.1-fpm.log';
    
    foreach ([$apacheLog, $phpFpmLog, $altLog] as $f) {
        if (file_exists($f)) {
            echo "Lendo: $f\n";
            $lines = file($f);
            $last = array_slice($lines, max(0, count($lines) - 50));
            foreach ($last as $line) {
                if (stripos($line, 'CommunicationHub') !== false || stripos($line, 'EXCEPTION') !== false || stripos($line, 'TypeError') !== false) {
                    echo htmlspecialchars($line);
                }
            }
        }
    }
    echo "\nNenhum log encontrado em: $logFile\n";
}
echo "</pre>";
