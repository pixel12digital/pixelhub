<?php
/**
 * Script para verificar logs do servidor
 * Acesse: /screen-recordings/check-logs.php
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Logs - Share.php</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }
        h2 {
            color: #569cd6;
            margin-top: 30px;
            border-left: 4px solid #569cd6;
            padding-left: 10px;
        }
        .section {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        pre {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 600px;
            overflow-y: auto;
        }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        .log-entry {
            margin: 5px 0;
            padding: 5px;
            border-left: 3px solid #3e3e42;
        }
        .log-entry.share { border-left-color: #4ec9b0; }
        .log-entry.bypass { border-left-color: #569cd6; }
        .log-entry.error { border-left-color: #f48771; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Verificação de Logs - Share.php</h1>
        <p class="info">Gerado em: <?= date('Y-m-d H:i:s') ?></p>

        <?php
        // Caminhos possíveis para os logs
        $logPaths = [
            __DIR__ . '/../../logs/pixelhub.log',
            '/home/pixel12digital/hub.pixel12digital.com.br/logs/pixelhub.log',
            '/var/log/apache2/error.log',
            '/var/log/httpd/error_log',
            '/usr/local/apache2/logs/error_log',
        ];

        // Tenta encontrar o arquivo de log
        $logFile = null;
        foreach ($logPaths as $path) {
            if (file_exists($path) && is_readable($path)) {
                $logFile = $path;
                break;
            }
        }

        echo '<h2>1. Localização do Arquivo de Log</h2>';
        echo '<div class="section">';
        
        if ($logFile) {
            echo '<div class="success">✓ Arquivo de log encontrado:</div>';
            echo '<code>' . htmlspecialchars($logFile) . '</code><br>';
            echo '<div class="info">Tamanho: ' . number_format(filesize($logFile)) . ' bytes</div>';
            echo '<div class="info">Última modificação: ' . date('Y-m-d H:i:s', filemtime($logFile)) . '</div>';
        } else {
            echo '<div class="error">✗ Nenhum arquivo de log encontrado nos caminhos testados:</div>';
            echo '<ul>';
            foreach ($logPaths as $path) {
                $exists = file_exists($path) ? '<span class="success">existe</span>' : '<span class="error">não existe</span>';
                $readable = file_exists($path) && is_readable($path) ? '<span class="success">legível</span>' : '<span class="error">não legível</span>';
                echo '<li><code>' . htmlspecialchars($path) . '</code> - ' . $exists . ' - ' . $readable . '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';

        if ($logFile) {
            // Lê as últimas linhas do log
            $lines = file($logFile);
            $totalLines = count($lines);
            $lastLines = array_slice($lines, -200); // Últimas 200 linhas
            
            echo '<h2>2. Últimas 200 Linhas do Log</h2>';
            echo '<div class="section">';
            echo '<div class="info">Total de linhas no arquivo: ' . number_format($totalLines) . '</div>';
            echo '<pre>';
            echo htmlspecialchars(implode('', $lastLines));
            echo '</pre>';
            echo '</div>';

            // Filtra linhas relacionadas ao share.php
            $shareLines = [];
            $bypassLines = [];
            $errorLines = [];
            
            foreach ($lastLines as $line) {
                if (stripos($line, 'ScreenRecordings Share') !== false || 
                    stripos($line, 'share.php') !== false) {
                    $shareLines[] = $line;
                }
                if (stripos($line, 'Direct Share') !== false || 
                    stripos($line, 'Bypass Check') !== false) {
                    $bypassLines[] = $line;
                }
                if (stripos($line, 'ERRO') !== false || 
                    stripos($line, 'Fatal') !== false || 
                    stripos($line, 'Exception') !== false) {
                    $errorLines[] = $line;
                }
            }

            echo '<h2>3. Linhas Relacionadas ao Share.php</h2>';
            echo '<div class="section">';
            if (!empty($shareLines)) {
                echo '<div class="success">✓ Encontradas ' . count($shareLines) . ' linhas relacionadas ao share.php:</div>';
                echo '<pre>';
                foreach ($shareLines as $line) {
                    echo '<div class="log-entry share">' . htmlspecialchars($line) . '</div>';
                }
                echo '</pre>';
            } else {
                echo '<div class="warning">⚠ Nenhuma linha relacionada ao share.php encontrada nas últimas 200 linhas</div>';
            }
            echo '</div>';

            echo '<h2>4. Linhas do Bypass (Direct Share)</h2>';
            echo '<div class="section">';
            if (!empty($bypassLines)) {
                echo '<div class="info">Encontradas ' . count($bypassLines) . ' linhas do bypass:</div>';
                echo '<pre>';
                foreach ($bypassLines as $line) {
                    echo '<div class="log-entry bypass">' . htmlspecialchars($line) . '</div>';
                }
                echo '</pre>';
            } else {
                echo '<div class="warning">⚠ Nenhuma linha do bypass encontrada</div>';
            }
            echo '</div>';

            echo '<h2>5. Erros Recentes</h2>';
            echo '<div class="section">';
            if (!empty($errorLines)) {
                echo '<div class="error">Encontrados ' . count($errorLines) . ' erros nas últimas 200 linhas:</div>';
                echo '<pre>';
                foreach (array_slice($errorLines, -20) as $line) { // Últimos 20 erros
                    echo '<div class="log-entry error">' . htmlspecialchars($line) . '</div>';
                }
                echo '</pre>';
            } else {
                echo '<div class="success">✓ Nenhum erro encontrado nas últimas 200 linhas</div>';
            }
            echo '</div>';

            // Busca específica por "share.php INICIADO"
            $iniciadoLines = [];
            foreach ($lastLines as $line) {
                if (stripos($line, 'share.php INICIADO') !== false || 
                    stripos($line, 'INICIADO') !== false) {
                    $iniciadoLines[] = $line;
                }
            }

            echo '<h2>6. Confirmação de Execução do share.php</h2>';
            echo '<div class="section">';
            if (!empty($iniciadoLines)) {
                echo '<div class="success">✓ CONFIRMADO: share.php está sendo executado!</div>';
                echo '<div class="info">Encontradas ' . count($iniciadoLines) . ' confirmações de execução:</div>';
                echo '<pre>';
                foreach ($iniciadoLines as $line) {
                    echo '<div class="log-entry share">' . htmlspecialchars($line) . '</div>';
                }
                echo '</pre>';
            } else {
                echo '<div class="error">✗ share.php NÃO está sendo executado (não encontrado "share.php INICIADO" nos logs)</div>';
                echo '<div class="warning">Isso significa que o arquivo está sendo incluído, mas não está executando o código</div>';
            }
            echo '</div>';
        } else {
            echo '<h2>2. Como Verificar os Logs Manualmente</h2>';
            echo '<div class="section">';
            echo '<p>Como não foi possível encontrar o arquivo de log automaticamente, você pode verificar manualmente:</p>';
            echo '<ol>';
            echo '<li><strong>Via SSH:</strong><br>';
            echo '<code>tail -f /home/pixel12digital/hub.pixel12digital.com.br/logs/pixelhub.log | grep "ScreenRecordings Share"</code></li>';
            echo '<li><strong>Via cPanel File Manager:</strong><br>';
            echo 'Navegue até <code>/home/pixel12digital/hub.pixel12digital.com.br/logs/pixelhub.log</code></li>';
            echo '<li><strong>Via cPanel Error Log:</strong><br>';
            echo 'Acesse "Errors" no cPanel e procure por "ScreenRecordings Share"</li>';
            echo '</ol>';
            echo '</div>';
        }

        // Verifica se há acesso ao error_log do PHP
        $phpErrorLog = ini_get('error_log');
        if ($phpErrorLog && file_exists($phpErrorLog)) {
            echo '<h2>7. Log de Erros do PHP</h2>';
            echo '<div class="section">';
            echo '<div class="info">Arquivo de log do PHP: <code>' . htmlspecialchars($phpErrorLog) . '</code></div>';
            $phpLogLines = file_exists($phpErrorLog) ? file($phpErrorLog) : [];
            $phpLastLines = array_slice($phpLogLines, -50);
            $phpShareLines = [];
            foreach ($phpLastLines as $line) {
                if (stripos($line, 'ScreenRecordings Share') !== false || 
                    stripos($line, 'share.php') !== false ||
                    stripos($line, 'Direct Share') !== false) {
                    $phpShareLines[] = $line;
                }
            }
            if (!empty($phpShareLines)) {
                echo '<div class="success">Encontradas ' . count($phpShareLines) . ' linhas relacionadas:</div>';
                echo '<pre>';
                foreach ($phpShareLines as $line) {
                    echo htmlspecialchars($line);
                }
                echo '</pre>';
            } else {
                echo '<div class="warning">Nenhuma linha relacionada encontrada no log do PHP</div>';
            }
            echo '</div>';
        }
        ?>

        <h2>🔍 Comandos Úteis para Verificar Logs</h2>
        <div class="section">
            <p><strong>Via SSH (se tiver acesso):</strong></p>
            <pre>
# Ver últimas 50 linhas relacionadas ao share.php
tail -n 1000 /home/pixel12digital/hub.pixel12digital.com.br/logs/pixelhub.log | grep -i "share"

# Monitorar logs em tempo real
tail -f /home/pixel12digital/hub.pixel12digital.com.br/logs/pixelhub.log | grep -i "ScreenRecordings Share"

# Verificar se share.php está sendo executado
grep "share.php INICIADO" /home/pixel12digital/hub.pixel12digital.com.br/logs/pixelhub.log | tail -10
            </pre>
        </div>

    </div>
</body>
</html>

