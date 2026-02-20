<?php
// Script para verificar logs do Apache no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificação de Logs do Apache</h1>";

// Possíveis locais de logs no servidor
$logPaths = [
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    '/usr/local/apache/logs/error_log',
    '/home/pixel12digital/logs/error_log',
    '/var/log/apache2/access.log',
    '/var/log/httpd/access_log'
];

echo "<h2>Verificando arquivos de log</h2>";
$foundLogs = [];

foreach ($logPaths as $path) {
    if (file_exists($path)) {
        $foundLogs[] = $path;
        echo "<p style='color: green;'>✓ Encontrado: $path</p>";
    } else {
        echo "<p style='color: #666;'>✗ Não encontrado: $path</p>";
    }
}

if (!empty($foundLogs)) {
    echo "<h2>Últimas linhas dos logs de erro</h2>";
    
    foreach ($foundLogs as $logFile) {
        if (strpos($logFile, 'error') !== false) {
            echo "<h3>$logFile</h3>";
            echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
            
            // Tenta ler as últimas 50 linhas
            $lines = file($logFile);
            if ($lines) {
                $lastLines = array_slice($lines, -50);
                foreach ($lastLines as $line) {
                    // Destaca linhas que contenham opportunities ou 500
                    if (strpos($line, 'opportunities') !== false || strpos($line, '500') !== false) {
                        echo "<span style='color: red; font-weight: bold;'>" . htmlspecialchars($line) . "</span>";
                    } else {
                        echo htmlspecialchars($line);
                    }
                }
            } else {
                echo "Não foi possível ler o arquivo";
            }
            
            echo "</pre>";
        }
    }
}

echo "<h2>Comandos para executar no servidor</h2>";
echo "<p>Execute estes comandos no SSH do servidor:</p>";
echo "<pre><code># Verificar logs do Apache
tail -n 100 /var/log/apache2/error.log | grep -i opportunities

# Verificar logs de acesso
tail -n 100 /var/log/apache2/access.log | grep opportunities

# Verificar se há erros PHP recentes
tail -n 100 /var/log/apache2/error.log | grep -i php

# Verificar erros 500
tail -n 100 /var/log/apache2/error.log | grep -i \"500\|internal\"

# Data e hora atual
date</code></pre>";

echo "<h2>Teste direto via curl</h2>";
echo "<pre><code># Testar a URL diretamente
curl -I https://hub.pixel12digital.com.br/opportunities

# Com verbose para ver detalhes
curl -v https://hub.pixel12digital.com.br/opportunities</code></pre>";

?>
