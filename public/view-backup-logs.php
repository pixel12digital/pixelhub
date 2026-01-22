<?php
/**
 * Script para visualizar logs de upload de backups
 * Acesse: http://localhost/painel.pixel12digital/public/view-backup-logs.php
 */

// Prioriza o log customizado do projeto
$logDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs';
$projectLogFile = realpath($logDir) . DIRECTORY_SEPARATOR . 'pixelhub.log';
if ($projectLogFile === false) {
    $projectLogFile = $logDir . DIRECTORY_SEPARATOR . 'pixelhub.log';
}

// Lista de possíveis locais de log (prioriza o log do projeto)
$possibleLogs = [
    $projectLogFile, // Primeiro: log customizado do projeto
    __DIR__ . '/../logs/php_errors.log',
];

// Adiciona log do PHP se configurado
$phpErrorLog = ini_get('error_log');
if (!empty($phpErrorLog) && $phpErrorLog !== 'syslog') {
    $possibleLogs[] = $phpErrorLog;
}

// Adiciona locais comuns do XAMPP
$possibleLogs[] = 'C:/xampp/php/logs/php_error_log';
$possibleLogs[] = 'C:/xampp/apache/logs/error.log';

// Procura o primeiro arquivo que existe
$logFile = null;
foreach ($possibleLogs as $path) {
    if (file_exists($path)) {
        $logFile = $path;
        break;
    }
}

// Se não encontrou nenhum, usa o log do projeto (mesmo que não exista ainda)
if (!$logFile) {
    $logFile = $projectLogFile;
}

// Garante que o diretório de logs existe
if (!file_exists($logDir)) {
    @mkdir($logDir, 0755, true);
}

// Se o arquivo não existe, cria um vazio para poder exibir
if (!file_exists($logFile)) {
    @file_put_contents($logFile, '');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Logs de Upload de Backups</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #023A8D;
        }
        .log-file {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.5;
        }
        .log-entry {
            margin-bottom: 5px;
        }
        .log-entry.hosting-backup {
            background: #2d2d2d;
            padding: 5px;
            border-left: 3px solid #F7931E;
        }
        .info {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .error {
            color: #f44336;
        }
        .success {
            color: #4caf50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Logs de Upload de Backups</h1>
        
        <div class="info">
            <strong>Arquivo de log:</strong> <?= htmlspecialchars($logFile ?? 'Não encontrado') ?><br>
            <strong>Última atualização:</strong> <?= $logFile && file_exists($logFile) && filesize($logFile) > 0 ? date('d/m/Y H:i:s', filemtime($logFile)) : 'N/A' ?><br>
            <strong>Tamanho:</strong> <?= $logFile && file_exists($logFile) && filesize($logFile) > 0 ? number_format(filesize($logFile) / 1024, 2) . ' KB' : '0 KB (vazio)' ?>
        </div>

        <?php if ($logFile && file_exists($logFile)): ?>
            <?php
            $fileSize = filesize($logFile);
            if ($fileSize > 0):
            ?>
                <h2>Últimas 200 linhas (filtradas por "HostingBackup"):</h2>
                <div class="log-file">
                    <?php
                    $lines = file($logFile);
                    $filteredLines = [];
                    $allLines = [];
                    
                    // Pega últimas 200 linhas
                    $recentLines = array_slice($lines, -200);
                    
                    foreach ($recentLines as $line) {
                        $allLines[] = $line;
                        if (stripos($line, 'HostingBackup') !== false) {
                            $filteredLines[] = $line;
                        }
                    }
                    
                    // Se não encontrou linhas filtradas, mostra todas
                    $displayLines = !empty($filteredLines) ? $filteredLines : $allLines;
                    
                    foreach ($displayLines as $line) {
                        $isHostingBackup = stripos($line, 'HostingBackup') !== false;
                        $isError = stripos($line, 'ERRO') !== false || stripos($line, 'error') !== false;
                        $isSuccess = stripos($line, 'successfully') !== false;
                        
                        $class = $isHostingBackup ? 'hosting-backup' : '';
                        $color = '';
                        if ($isError) $color = 'error';
                        if ($isSuccess) $color = 'success';
                        
                        echo '<div class="log-entry ' . $class . '" style="color: ' . ($color ?: '#d4d4d4') . '">';
                        echo htmlspecialchars($line);
                        echo '</div>';
                    }
                    ?>
                </div>
                
                <?php if (empty($filteredLines)): ?>
                    <p style="color: #666; margin-top: 10px;">
                        <em>Nenhuma linha com "HostingBackup" encontrada nas últimas 200 linhas. 
                        Tente fazer upload de um arquivo para gerar logs.</em>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <div class="info" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                    <p><strong>Arquivo de log vazio</strong></p>
                    <p>O arquivo de log existe mas está vazio. Faça upload de um arquivo .wpress para gerar logs de diagnóstico.</p>
                    <p><strong>Arquivo:</strong> <?= htmlspecialchars($logFile) ?></p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="error">
                <p><strong>Arquivo de log não encontrado!</strong></p>
                <p>Possíveis locais verificados:</p>
                <ul>
                    <?php foreach ($possibleLogs ?? [] as $path): ?>
                        <li><?= htmlspecialchars($path) ?> - <?= file_exists($path) ? '✅ Existe' : '❌ Não existe' ?></li>
                    <?php endforeach; ?>
                </ul>
                <p><strong>Dica:</strong> Verifique o php.ini para ver onde está configurado o error_log:</p>
                <pre><?= ini_get('error_log') ?: 'Não configurado (usa padrão do sistema)' ?></pre>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 20px;">
            <a href="javascript:location.reload()" style="color: #023A8D; text-decoration: none;">🔄 Atualizar</a> | 
            <a href="<?= str_replace('view-backup-logs.php', 'hosting/backups', $_SERVER['PHP_SELF']) ?>" style="color: #023A8D; text-decoration: none;">← Voltar para Backups</a>
        </p>
    </div>
</body>
</html>

