<?php
/**
 * Script para verificar logs do servidor
 * Acesse: /screen-recordings/check-logs.php
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../../src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

// Carrega variáveis de ambiente
try {
    if (class_exists('PixelHub\Core\Env')) {
        \PixelHub\Core\Env::load();
    }
} catch (\Exception $e) {
    // Ignora erro de env
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

        // Nova seção: Verificação de arquivos físicos
        echo '<h2>7. Verificação de Arquivos Físicos no Servidor</h2>';
        echo '<div class="section">';
        
        try {
            // DB já deve estar carregado pelo autoload acima
            if (!class_exists('PixelHub\Core\DB')) {
                throw new \Exception('Classe DB não encontrada. Verifique se o autoload está funcionando.');
            }
            
            $db = \PixelHub\Core\DB::getConnection();
            
            // Busca TODOS os registros com token
            $tokenStmt = $db->query("SELECT id, file_path, file_name, original_name, public_token, created_at, task_id FROM screen_recordings WHERE public_token IS NOT NULL ORDER BY id DESC");
            $tokens = $tokenStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($tokens)) {
                echo '<div class="info">Verificando arquivos físicos para ' . count($tokens) . ' registros com token:</div>';
                echo '<pre>';
                
                foreach ($tokens as $rec) {
                    echo '<div class="log-entry" style="border: 2px solid #3e3e42; padding: 15px; margin: 10px 0; background: #1e1e1e;">';
                    echo '<strong style="color: #4ec9b0;">═══════════════════════════════════════════════════════════</strong><br>';
                    echo '<strong style="color: #569cd6;">ID:</strong> ' . $rec['id'] . '<br>';
                    echo '<strong style="color: #569cd6;">Token:</strong> <code>' . htmlspecialchars($rec['public_token']) . '</code><br>';
                    echo '<strong style="color: #569cd6;">Task ID:</strong> ' . ($rec['task_id'] ?? 'NULL') . '<br>';
                    echo '<strong style="color: #569cd6;">Criado em:</strong> ' . htmlspecialchars($rec['created_at']) . '<br><br>';
                    
                    echo '<strong style="color: #dcdcaa;">DADOS DO BANCO:</strong><br>';
                    echo '  <strong>file_path:</strong> <code>' . htmlspecialchars($rec['file_path']) . '</code><br>';
                    echo '  <strong>file_name:</strong> <code>' . htmlspecialchars($rec['file_name']) . '</code><br>';
                    echo '  <strong>original_name:</strong> <code>' . htmlspecialchars($rec['original_name']) . '</code><br><br>';
                    
                    // Verifica se o arquivo existe com file_path
                    $relativePath = ltrim($rec['file_path'], '/');
                    $filePath1 = null;
                    $fileExists1 = false;
                    $fileSize1 = null;
                    $filePerms1 = null;
                    
                    if (strpos($relativePath, 'screen-recordings/') === 0) {
                        $fileRelativePath = preg_replace('#^screen-recordings/#', '', $relativePath);
                        $baseDir = '/home/pixel12digital/hub.pixel12digital.com.br/public/screen-recordings';
                        $filePath1 = $baseDir . '/' . $fileRelativePath;
                        $fileExists1 = file_exists($filePath1) && is_file($filePath1);
                        if ($fileExists1) {
                            $fileSize1 = filesize($filePath1);
                            $filePerms1 = substr(sprintf('%o', fileperms($filePath1)), -4);
                        }
                    } elseif (strpos($relativePath, 'storage/tasks/') === 0) {
                        $baseDir = '/home/pixel12digital/hub.pixel12digital.com.br';
                        $filePath1 = $baseDir . '/' . $relativePath;
                        $fileExists1 = file_exists($filePath1) && is_file($filePath1);
                        if ($fileExists1) {
                            $fileSize1 = filesize($filePath1);
                            $filePerms1 = substr(sprintf('%o', fileperms($filePath1)), -4);
                        }
                    }
                    
                    echo '<strong style="color: #dcdcaa;">TENTATIVA 1 - Com file_path:</strong><br>';
                    if ($filePath1) {
                        echo '  <strong>Caminho:</strong> <code>' . htmlspecialchars($filePath1) . '</code><br>';
                        echo '  <strong>Existe:</strong> ';
                        echo $fileExists1 ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>';
                        echo '<br>';
                        if ($fileExists1) {
                            echo '  <strong>Tamanho:</strong> ' . number_format($fileSize1) . ' bytes (' . number_format($fileSize1 / 1024 / 1024, 2) . ' MB)<br>';
                            echo '  <strong>Permissões:</strong> ' . $filePerms1 . '<br>';
                            echo '  <strong>Legível:</strong> ' . (is_readable($filePath1) ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>') . '<br>';
                        } else {
                            // Verifica se o diretório existe
                            $parentDir = dirname($filePath1);
                            echo '  <strong>Diretório pai existe:</strong> ' . (is_dir($parentDir) ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>') . '<br>';
                            if (is_dir($parentDir)) {
                                echo '  <strong>Diretório pai:</strong> <code>' . htmlspecialchars($parentDir) . '</code><br>';
                            }
                        }
                    } else {
                        echo '  <span class="warning">N/A - file_path não é screen-recordings/ nem storage/tasks/</span><br>';
                    }
                    echo '<br>';
                    
                    // Verifica se o arquivo existe com file_name
                    if (!empty($rec['file_name'])) {
                        $filePath2 = null;
                        $fileExists2 = false;
                        $fileSize2 = null;
                        $filePerms2 = null;
                        
                        if (strpos($relativePath, 'screen-recordings/') === 0) {
                            $pathDir = dirname(preg_replace('#^screen-recordings/#', '', $relativePath));
                            $baseDir = '/home/pixel12digital/hub.pixel12digital.com.br/public/screen-recordings';
                            $filePath2 = $baseDir . '/' . $pathDir . '/' . $rec['file_name'];
                            $fileExists2 = file_exists($filePath2) && is_file($filePath2);
                            if ($fileExists2) {
                                $fileSize2 = filesize($filePath2);
                                $filePerms2 = substr(sprintf('%o', fileperms($filePath2)), -4);
                            }
                        } elseif (strpos($relativePath, 'storage/tasks/') === 0) {
                            $pathDir = dirname($relativePath);
                            $baseDir = '/home/pixel12digital/hub.pixel12digital.com.br';
                            $filePath2 = $baseDir . '/' . $pathDir . '/' . $rec['file_name'];
                            $fileExists2 = file_exists($filePath2) && is_file($filePath2);
                            if ($fileExists2) {
                                $fileSize2 = filesize($filePath2);
                                $filePerms2 = substr(sprintf('%o', fileperms($filePath2)), -4);
                            }
                        }
                        
                        echo '<strong style="color: #dcdcaa;">TENTATIVA 2 - Com file_name:</strong><br>';
                        if ($filePath2) {
                            echo '  <strong>Caminho:</strong> <code>' . htmlspecialchars($filePath2) . '</code><br>';
                            echo '  <strong>Existe:</strong> ';
                            echo $fileExists2 ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>';
                            echo '<br>';
                            if ($fileExists2) {
                                echo '  <strong>Tamanho:</strong> ' . number_format($fileSize2) . ' bytes (' . number_format($fileSize2 / 1024 / 1024, 2) . ' MB)<br>';
                                echo '  <strong>Permissões:</strong> ' . $filePerms2 . '<br>';
                                echo '  <strong>Legível:</strong> ' . (is_readable($filePath2) ? '<span class="success">✓ SIM</span>' : '<span class="error">✗ NÃO</span>') . '<br>';
                            }
                        } else {
                            echo '  <span class="warning">N/A - não foi possível construir caminho</span><br>';
                        }
                        echo '<br>';
                        
                        // Lista TODOS os arquivos no diretório
                        if (strpos($relativePath, 'screen-recordings/') === 0) {
                            $pathDir = dirname(preg_replace('#^screen-recordings/#', '', $relativePath));
                            $baseDir = '/home/pixel12digital/hub.pixel12digital.com.br/public/screen-recordings';
                            $dirPath = $baseDir . '/' . $pathDir;
                            
                            echo '<strong style="color: #dcdcaa;">ARQUIVOS NO DIRETÓRIO:</strong><br>';
                            echo '  <strong>Diretório:</strong> <code>' . htmlspecialchars($dirPath) . '</code><br>';
                            if (is_dir($dirPath)) {
                                $files = @scandir($dirPath);
                                if ($files) {
                                    $actualFiles = array_filter($files, function($f) {
                                        return $f !== '.' && $f !== '..';
                                    });
                                    $actualFiles = array_values($actualFiles);
                                    
                                    echo '  <strong>Total de arquivos:</strong> ' . count($actualFiles) . '<br>';
                                    echo '  <strong>Lista completa:</strong><br>';
                                    foreach ($actualFiles as $f) {
                                        $fullPath = $dirPath . '/' . $f;
                                        $isFile = is_file($fullPath);
                                        $size = $isFile ? filesize($fullPath) : 0;
                                        $match = ($f === basename($rec['file_path']) || $f === $rec['file_name'] || $f === $rec['original_name']);
                                        $marker = $match ? ' <span class="success">← CORRESPONDE!</span>' : '';
                                        echo '    - <code>' . htmlspecialchars($f) . '</code> (' . ($isFile ? number_format($size) . ' bytes' : 'DIR') . ')' . $marker . '<br>';
                                    }
                                } else {
                                    echo '  <span class="error">Erro ao listar arquivos do diretório</span><br>';
                                }
                            } else {
                                echo '  <span class="error">Diretório não existe!</span><br>';
                            }
                        }
                    }
                    
                    // Verifica também com original_name (caso especial)
                    if (!empty($rec['original_name']) && !$fileExists1 && !$fileExists2) {
                        if (strpos($relativePath, 'screen-recordings/') === 0) {
                            $pathDir = dirname(preg_replace('#^screen-recordings/#', '', $relativePath));
                            $baseDir = '/home/pixel12digital/hub.pixel12digital.com.br/public/screen-recordings';
                            $filePath3 = $baseDir . '/' . $pathDir . '/' . $rec['original_name'];
                            $fileExists3 = file_exists($filePath3) && is_file($filePath3);
                            
                            if ($fileExists3) {
                                echo '<br><strong style="color: #dcdcaa;">TENTATIVA 3 - Com original_name (ENCONTRADO!):</strong><br>';
                                echo '  <strong>Caminho:</strong> <code>' . htmlspecialchars($filePath3) . '</code><br>';
                                echo '  <strong>Existe:</strong> <span class="success">✓ SIM</span><br>';
                                echo '  <strong>Tamanho:</strong> ' . number_format(filesize($filePath3)) . ' bytes<br>';
                            }
                        }
                    }
                    
                    echo '</div><br>';
                }
                
                echo '</pre>';
            } else {
                echo '<div class="warning">Nenhum registro com token encontrado no banco</div>';
            }
        } catch (\Exception $e) {
            echo '<div class="error">Erro ao verificar arquivos: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<div class="error">Stack trace: <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></div>';
        }
        
        echo '</div>';

        // Nova seção: Análise de logs detalhada
        echo '<h2>8. Análise Detalhada dos Logs do share.php</h2>';
        echo '<div class="section">';
        
        if (!empty($shareLines)) {
            // Agrupa por timestamp
            $grouped = [];
            foreach ($shareLines as $line) {
                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                    $timestamp = $matches[1];
                    if (!isset($grouped[$timestamp])) {
                        $grouped[$timestamp] = [];
                    }
                    $grouped[$timestamp][] = $line;
                }
            }
            
            echo '<div class="info">Encontradas ' . count($shareLines) . ' linhas em ' . count($grouped) . ' execuções diferentes:</div>';
            echo '<pre>';
            
            $count = 0;
            foreach ($grouped as $timestamp => $lines) {
                if ($count++ >= 3) break; // Mostra apenas as 3 últimas execuções
                
                echo '<div class="log-entry share">';
                echo '<strong>Execução em ' . htmlspecialchars($timestamp) . ':</strong><br>';
                foreach ($lines as $line) {
                    // Destaca informações importantes
                    if (stripos($line, 'fileExists') !== false) {
                        if (stripos($line, 'SIM') !== false) {
                            echo '<span class="success">' . htmlspecialchars($line) . '</span>';
                        } else {
                            echo '<span class="error">' . htmlspecialchars($line) . '</span>';
                        }
                    } elseif (stripos($line, 'file_name') !== false || stripos($line, 'file_path') !== false) {
                        echo '<span class="info">' . htmlspecialchars($line) . '</span>';
                    } else {
                        echo htmlspecialchars($line);
                    }
                }
                echo '</div><br>';
            }
            
            echo '</pre>';
        } else {
            echo '<div class="warning">Nenhuma linha do share.php encontrada nos logs</div>';
        }
        
        echo '</div>';

        // Verifica se há acesso ao error_log do PHP
        $phpErrorLog = ini_get('error_log');
        if ($phpErrorLog && file_exists($phpErrorLog)) {
            echo '<h2>9. Log de Erros do PHP</h2>';
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

