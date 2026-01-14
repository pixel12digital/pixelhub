<?php
/**
 * Script web para verificar logs do webhook
 * Acesse via: https://hub.pixel12digital.com.br/check-logs-webhook.php
 */

// Segurança básica - ajustar conforme necessário
$allowedIPs = []; // Deixar vazio para permitir qualquer IP, ou adicionar IPs permitidos
$requireAuth = true; // Requer autenticação básica

if ($requireAuth) {
    // Verifica autenticação básica
    if (!isset($_SERVER['PHP_AUTH_USER']) || 
        !isset($_SERVER['PHP_AUTH_PW']) ||
        $_SERVER['PHP_AUTH_USER'] !== 'admin' || 
        $_SERVER['PHP_AUTH_PW'] !== 'pixel12hub2024') { // MUDAR SENHA!
        header('WWW-Authenticate: Basic realm="Log Checker"');
        header('HTTP/1.0 401 Unauthorized');
        die('Acesso negado');
    }
}

// Verifica IP se configurado
if (!empty($allowedIPs)) {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!in_array($clientIP, $allowedIPs)) {
        http_response_code(403);
        die('Acesso negado para este IP: ' . $clientIP);
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verificação de Logs - Webhook Teste</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .section { background: #252526; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        .info { color: #569cd6; }
        pre { background: #1e1e1e; padding: 10px; border-radius: 3px; overflow-x: auto; }
        h2 { color: #569cd6; border-bottom: 1px solid #3e3e42; padding-bottom: 5px; }
        .timestamp { color: #858585; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>🔍 Verificação de Logs - Webhook Teste</h1>
    <div class="timestamp">Executado em: <?php echo date('Y-m-d H:i:s'); ?></div>
    
    <?php
    $correlationId = '9858a507-cc4c-4632-8f92-462535eab504';
    $testTime = '21:35';
    $containerName = 'gateway-hub';
    
    function execCommand($command) {
        $output = [];
        $returnVar = 0;
        exec($command . ' 2>&1', $output, $returnVar);
        return [
            'output' => $output,
            'return_code' => $returnVar,
            'command' => $command
        ];
    }
    
    // 1. Verifica Docker
    echo '<div class="section">';
    echo '<h2>1. Verificação do Docker</h2>';
    $dockerCheck = execCommand('docker --version');
    if ($dockerCheck['return_code'] === 0) {
        echo '<div class="success">✅ Docker disponível</div>';
        echo '<pre>' . implode("\n", $dockerCheck['output']) . '</pre>';
        $dockerAvailable = true;
    } else {
        echo '<div class="error">❌ Docker não disponível</div>';
        $dockerAvailable = false;
    }
    echo '</div>';
    
    // 2. Lista containers
    if ($dockerAvailable) {
        echo '<div class="section">';
        echo '<h2>2. Containers Disponíveis</h2>';
        $containers = execCommand('docker ps -a --format "{{.Names}}\t{{.Status}}"');
        echo '<pre>';
        foreach ($containers['output'] as $line) {
            if (!empty(trim($line))) {
                // Destaca containers com "hub" no nome
                if (stripos($line, 'hub') !== false) {
                    echo '<span class="info">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
        }
        echo '</pre>';
        
        // Tenta encontrar container do Hub
        $hubContainer = null;
        $allContainers = execCommand('docker ps -a --format "{{.Names}}"');
        foreach ($allContainers['output'] as $name) {
            $name = trim($name);
            if (stripos($name, 'hub') !== false || stripos($name, 'pixel') !== false) {
                $hubContainer = $name;
                break;
            }
        }
        
        if (!$hubContainer) {
            $hubContainer = $containerName;
        }
        
        echo '<div class="info">Usando container: <strong>' . htmlspecialchars($hubContainer) . '</strong></div>';
        echo '</div>';
    } else {
        $hubContainer = null;
    }
    
    // 3. Busca correlation_id
    if ($dockerAvailable && $hubContainer) {
        echo '<div class="section">';
        echo '<h2>3. Buscando correlation_id nos Logs</h2>';
        echo '<div class="info">correlation_id: <strong>' . htmlspecialchars($correlationId) . '</strong></div>';
        
        $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i '$correlationId' | tail -30";
        $result = execCommand($cmd);
        
        if (!empty($result['output'])) {
            echo '<div class="success">✅ Encontradas ' . count($result['output']) . ' linhas:</div>';
            echo '<pre>';
            foreach ($result['output'] as $line) {
                // Destaca linhas importantes
                if (stripos($line, 'HUB_WEBHOOK_IN') !== false) {
                    echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                } elseif (stripos($line, 'HUB_MSG_SAVE') !== false) {
                    echo '<span class="success">' . htmlspecialchars($line) . '</span>' . "\n";
                } elseif (stripos($line, 'HUB_MSG_DROP') !== false) {
                    echo '<span class="warning">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<div class="error">❌ Nenhuma linha encontrada com correlation_id</div>';
            echo '<div class="warning">Comando executado: ' . htmlspecialchars($result['command']) . '</div>';
        }
        echo '</div>';
    }
    
    // 4. Busca HUB_WEBHOOK_IN
    if ($dockerAvailable && $hubContainer) {
        echo '<div class="section">';
        echo '<h2>4. Buscando HUB_WEBHOOK_IN (Entrada do Webhook)</h2>';
        
        $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_WEBHOOK_IN' | tail -20";
        $result = execCommand($cmd);
        
        if (!empty($result['output'])) {
            echo '<div class="success">✅ Encontradas ' . count($result['output']) . ' linhas:</div>';
            echo '<pre>';
            foreach ($result['output'] as $line) {
                // Destaca se contém o horário do teste
                if (strpos($line, $testTime) !== false) {
                    echo '<span class="success" style="background: #264f78; padding: 2px;">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<div class="error">❌ Nenhuma linha encontrada</div>';
        }
        echo '</div>';
    }
    
    // 5. Busca HUB_MSG_SAVE
    if ($dockerAvailable && $hubContainer) {
        echo '<div class="section">';
        echo '<h2>5. Buscando HUB_MSG_SAVE (Persistência de Mensagem)</h2>';
        
        $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_MSG_SAVE' | tail -20";
        $result = execCommand($cmd);
        
        if (!empty($result['output'])) {
            echo '<div class="success">✅ Encontradas ' . count($result['output']) . ' linhas:</div>';
            echo '<pre>';
            foreach ($result['output'] as $line) {
                if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                    echo '<span class="success" style="background: #264f78; padding: 2px;">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<div class="error">❌ Nenhuma linha encontrada</div>';
        }
        echo '</div>';
    }
    
    // 6. Busca HUB_MSG_DROP
    if ($dockerAvailable && $hubContainer) {
        echo '<div class="section">';
        echo '<h2>6. Buscando HUB_MSG_DROP (Mensagens Descartadas)</h2>';
        
        $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -i 'HUB_MSG_DROP' | tail -20";
        $result = execCommand($cmd);
        
        if (!empty($result['output'])) {
            echo '<div class="warning">⚠️ Encontradas ' . count($result['output']) . ' linhas (eventos descartados):</div>';
            echo '<pre>';
            foreach ($result['output'] as $line) {
                if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                    echo '<span class="warning" style="background: #7c4a00; padding: 2px;">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<div class="success">✅ Nenhuma linha encontrada (nenhum evento descartado)</div>';
        }
        echo '</div>';
    }
    
    // 7. Busca erros
    if ($dockerAvailable && $hubContainer) {
        echo '<div class="section">';
        echo '<h2>7. Buscando Erros/Exceções</h2>';
        
        $cmd = "docker logs --since 21:30 $hubContainer 2>&1 | grep -iE 'Exception|Error|Fatal' | tail -20";
        $result = execCommand($cmd);
        
        if (!empty($result['output'])) {
            echo '<div class="error">❌ Encontradas ' . count($result['output']) . ' linhas de erro:</div>';
            echo '<pre>';
            foreach ($result['output'] as $line) {
                if (strpos($line, $testTime) !== false || strpos($line, $correlationId) !== false) {
                    echo '<span class="error" style="background: #5a1d1d; padding: 2px;">' . htmlspecialchars($line) . '</span>' . "\n";
                } else {
                    echo htmlspecialchars($line) . "\n";
                }
            }
            echo '</pre>';
        } else {
            echo '<div class="success">✅ Nenhum erro encontrado</div>';
        }
        echo '</div>';
    }
    
    // 8. Verifica banco de dados
    echo '<div class="section">';
    echo '<h2>8. Verificação no Banco de Dados</h2>';
    try {
        require __DIR__ . '/../src/Core/DB.php';
        require __DIR__ . '/../src/Core/Env.php';
        
        \PixelHub\Core\Env::load();
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                id,
                event_id,
                correlation_id,
                event_type,
                status,
                created_at,
                JSON_EXTRACT(payload, '$.message.id') as message_id
            FROM communication_events 
            WHERE correlation_id = ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$correlationId]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($events) {
            echo '<div class="success">✅ Encontrados ' . count($events) . ' eventos no banco:</div>';
            echo '<pre>';
            foreach ($events as $event) {
                $highlight = '';
                if (strpos($event['created_at'], '21:35') !== false || strpos($event['created_at'], '19:35') !== false) {
                    $highlight = ' style="background: #264f78; padding: 2px;"';
                }
                echo sprintf(
                    '<span%s>[%s] event_id: %s | status: %s | message_id: %s</span>' . "\n",
                    $highlight,
                    htmlspecialchars($event['created_at']),
                    htmlspecialchars($event['event_id']),
                    htmlspecialchars($event['status']),
                    htmlspecialchars($event['message_id'] ?: 'NULL')
                );
            }
            echo '</pre>';
        } else {
            echo '<div class="error">❌ Nenhum evento encontrado no banco com correlation_id: ' . htmlspecialchars($correlationId) . '</div>';
        }
    } catch (\Exception $e) {
        echo '<div class="error">❌ Erro ao acessar banco: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    echo '</div>';
    
    // Resumo
    echo '<div class="section">';
    echo '<h2>📊 Resumo</h2>';
    echo '<div class="info">';
    echo '<strong>correlation_id:</strong> ' . htmlspecialchars($correlationId) . '<br>';
    echo '<strong>Horário do teste:</strong> ~' . htmlspecialchars($testTime) . '<br>';
    echo '<strong>Container:</strong> ' . ($hubContainer ?: 'N/A') . '<br>';
    echo '</div>';
    echo '</div>';
    ?>
    
    <div class="section">
        <h2>🔄 Atualizar</h2>
        <p><a href="?refresh=<?php echo time(); ?>" style="color: #569cd6;">Clique aqui para atualizar</a></p>
    </div>
</body>
</html>

