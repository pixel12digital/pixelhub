<?php
// Script para verificar logs do NGINX no servidor
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Verificação de Logs do NGINX</h1>";

// Possíveis locais de logs NGINX
$logPaths = [
    '/var/log/nginx/error.log',
    '/var/log/nginx/access.log',
    '/usr/local/nginx/logs/error.log',
    '/usr/local/nginx/logs/access.log',
    '/var/log/nginx/error.log.1',
    '/var/log/nginx/access.log.1',
    '/home/pixel12digital/logs/nginx_error.log',
    '/home/pixel12digital/logs/nginx_access.log'
];

echo "<h2>Verificando arquivos de log NGINX</h2>";
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
    echo "<h2>Últimas linhas dos logs de erro NGINX</h2>";
    
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

echo "<h2>Comandos para executar no servidor (NGINX)</h2>";
echo "<p>Execute estes comandos no SSH do servidor:</p>";
echo "<pre><code># Verificar logs do NGINX
tail -n 100 /var/log/nginx/error.log | grep -i opportunities

# Verificar logs de acesso NGINX
tail -n 100 /var/log/nginx/access.log | grep opportunities

# Verificar erros PHP recentes
tail -n 100 /var/log/nginx/error.log | grep -i php

# Verificar erros 500
tail -n 100 /var/log/nginx/error.log | grep -E \"500|internal|fatal\"

# Verificar status do NGINX
ps aux | grep nginx

# Verificar configuração NGINX
nginx -t

# Recarregar NGINX
nginx -s reload</code></pre>";

echo "<h2>Importante: O curl retornou 404!</h2>";
echo "<p>O curl retornou 'HTTP/1.1 404 Not Found'. Isso significa que a rota não está sendo encontrada pelo NGINX.</p>";
echo "<p>Verifique:</p>";
echo "<ul>";
echo "<li>Se o .htaccess está funcionando</li>";
echo "<li>Se o rewrite do NGINX está configurado corretamente</li>";
echo "<li>Se o arquivo public/index.php existe</li>";
echo "</ul>";

?>
