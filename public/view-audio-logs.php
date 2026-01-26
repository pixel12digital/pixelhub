<?php
/**
 * Script para visualizar logs de envio de áudio em tempo real
 * Acesse: http://localhost/painel.pixel12digital/public/view-audio-logs.php
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs de Envio de Áudio</title>
    <meta charset="utf-8">
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .log-container {
            background: #252526;
            border: 1px solid #3e3e42;
            padding: 15px;
            border-radius: 5px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .log-line {
            margin: 5px 0;
            padding: 5px;
            border-left: 3px solid transparent;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .log-line.error {
            border-left-color: #f48771;
            background: rgba(244, 135, 113, 0.1);
        }
        .log-line.warning {
            border-left-color: #cca700;
            background: rgba(204, 167, 0, 0.1);
        }
        .log-line.success {
            border-left-color: #89d185;
            background: rgba(137, 209, 133, 0.1);
        }
        .log-line.info {
            border-left-color: #4ec9b0;
        }
        .timestamp {
            color: #858585;
        }
        .refresh-btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        .refresh-btn:hover {
            background: #1177bb;
        }
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h1>📻 Logs de Envio de Áudio</h1>
    
    <div class="auto-refresh">
        <button class="refresh-btn" onclick="location.reload()">🔄 Atualizar</button>
        <label>
            <input type="checkbox" id="autoRefresh" onchange="toggleAutoRefresh()">
            Auto-atualizar a cada 5 segundos
        </label>
    </div>

    <div class="log-container" id="logContainer">
        <?php
        // Caminho correto: de public/ para ../logs/pixelhub.log
        $logFile = __DIR__ . '/../logs/pixelhub.log';
        
        if (!file_exists($logFile)) {
            echo "<p style='color: #f48771;'>❌ Arquivo de log não encontrado: {$logFile}</p>";
            echo "<p style='color: #858585;'>Verificando caminhos alternativos...</p>";
            
            // Tenta caminhos alternativos
            $altPaths = [
                __DIR__ . '/../logs/pixelhub.log',
                dirname(__DIR__) . '/logs/pixelhub.log',
                realpath(__DIR__ . '/../logs/pixelhub.log')
            ];
            
            foreach ($altPaths as $altPath) {
                if (file_exists($altPath)) {
                    echo "<p style='color: #89d185;'>✅ Arquivo encontrado em: {$altPath}</p>";
                    $logFile = $altPath;
                    break;
                }
            }
        }
        
        if (file_exists($logFile)) {
            // Lê as últimas 1000 linhas
            $lines = file($logFile);
            $recentLines = array_slice($lines, -1000);
            
            // Filtra apenas linhas relacionadas a áudio
            $audioRelated = [];
            foreach ($recentLines as $line) {
                if (stripos($line, 'sendAudioBase64Ptt') !== false ||
                    stripos($line, 'WhatsAppGateway') !== false ||
                    stripos($line, 'CommunicationHub') !== false && stripos($line, 'send') !== false ||
                    (stripos($line, 'audio') !== false && (stripos($line, 'WhatsApp') !== false || stripos($line, 'WPPConnect') !== false)) ||
                    stripos($line, 'WPPConnect') !== false) {
                    $audioRelated[] = $line;
                }
            }
            
            // Mostra as últimas 200 linhas relacionadas a áudio
            $audioRelated = array_slice($audioRelated, -200);
            
            if (empty($audioRelated)) {
                echo "<p style='color: #858585;'>ℹ️ Nenhum log de áudio encontrado nas últimas 1000 linhas.</p>";
                echo "<p style='color: #858585;'>Total de linhas no arquivo: " . count($lines) . "</p>";
            } else {
                echo "<p style='color: #4ec9b0;'>📊 Mostrando " . count($audioRelated) . " linhas relacionadas a áudio (de " . count($recentLines) . " linhas recentes)</p>";
                foreach ($audioRelated as $line) {
                    $line = htmlspecialchars($line);
                    $class = 'info';
                    
                    if (stripos($line, 'erro') !== false || stripos($line, 'error') !== false || stripos($line, '❌') !== false || stripos($line, 'failed') !== false) {
                        $class = 'error';
                    } elseif (stripos($line, 'warning') !== false || stripos($line, '⚠️') !== false) {
                        $class = 'warning';
                    } elseif (stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
                        $class = 'success';
                    }
                    
                    // Extrai timestamp
                    if (preg_match('/\[([^\]]+)\]/', $line, $matches)) {
                        $timestamp = $matches[1];
                        $line = str_replace('[' . $timestamp . ']', '<span class="timestamp">[' . $timestamp . ']</span>', $line);
                    }
                    
                    echo "<div class='log-line {$class}'>{$line}</div>";
                }
            }
        } else {
            echo "<p style='color: #f48771;'>❌ Não foi possível encontrar o arquivo de log.</p>";
            echo "<p style='color: #858585;'>Caminho tentado: {$logFile}</p>";
        }
        ?>
    </div>

    <script>
        let autoRefreshInterval = null;
        
        function toggleAutoRefresh() {
            const checkbox = document.getElementById('autoRefresh');
            if (checkbox.checked) {
                autoRefreshInterval = setInterval(() => {
                    location.reload();
                }, 5000);
            } else {
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                    autoRefreshInterval = null;
                }
            }
        }
        
        // Scroll para o final
        const container = document.getElementById('logContainer');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    </script>
</body>
</html>

