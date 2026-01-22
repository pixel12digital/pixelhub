<?php

/**
 * Script de Teste Web - Integração WhatsApp Gateway
 * 
 * Testa o envio de mensagens via gateway através da interface web
 * 
 * Acesse via: http://localhost/painel.pixel12digital/database/test-whatsapp-via-web.php
 */

// Simula o ambiente do index.php
define('BASE_PATH', dirname(__DIR__));

// Autoload manual
spl_autoload_register(function ($class) {
    $prefix = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    
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

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
try {
    Env::load(__DIR__ . '/../.env');
} catch (\Exception $e) {
    // Ignora se não existir
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste WhatsApp Gateway - PixelHub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 4px solid #4CAF50;
        }
        .section.error {
            border-left-color: #f44336;
        }
        .section.warning {
            border-left-color: #ff9800;
        }
        .section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .status.ok { background: #4CAF50; color: white; }
        .status.error { background: #f44336; color: white; }
        .status.warning { background: #ff9800; color: white; }
        .info {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin-top: 10px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover { background: #45a049; }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .test-form {
            margin-top: 20px;
            padding: 20px;
            background: white;
            border-radius: 6px;
            border: 2px solid #4CAF50;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Teste de Integração WhatsApp Gateway</h1>
        <p class="subtitle">Verificação de configuração e teste de envio</p>

        <?php
        $errors = [];
        $warnings = [];
        $infos = [];

        // 1. Verificar variáveis de ambiente
        echo '<div class="section">';
        echo '<h2>1. Variáveis de Ambiente <span class="status ' . (Env::get('WPP_GATEWAY_SECRET') ? 'ok' : 'error') . '">' . (Env::get('WPP_GATEWAY_SECRET') ? 'OK' : 'ERRO') . '</span></h2>';
        
        $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
        $secret = Env::get('WPP_GATEWAY_SECRET', '');
        
        echo '<div class="info">';
        echo '<strong>Base URL:</strong> ' . htmlspecialchars($baseUrl) . '<br>';
        echo '<strong>Secret:</strong> ' . ($secret ? str_repeat('*', min(strlen($secret), 20)) . ' (' . strlen($secret) . ' caracteres)' : '<span style="color: red;">NÃO CONFIGURADO</span>');
        echo '</div>';
        
        if (empty($secret)) {
            $errors[] = 'WPP_GATEWAY_SECRET não configurado no .env';
        }
        echo '</div>';

        // 2. Verificar banco de dados
        echo '<div class="section">';
        echo '<h2>2. Banco de Dados <span class="status ok">OK</span></h2>';
        try {
            $db = DB::getConnection();
            echo '<div class="info">✅ Conexão estabelecida</div>';
        } catch (\Exception $e) {
            echo '<div class="info" style="color: red;">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $errors[] = 'Erro ao conectar ao banco: ' . $e->getMessage();
        }
        echo '</div>';

        // 3. Verificar tabela tenant_message_channels
        echo '<div class="section">';
        echo '<h2>3. Estrutura do Banco <span class="status ok">OK</span></h2>';
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'tenant_message_channels'");
            if ($checkStmt->rowCount() === 0) {
                echo '<div class="info" style="color: red;">❌ Tabela tenant_message_channels não existe</div>';
                $errors[] = 'Tabela tenant_message_channels não existe. Execute a migration.';
            } else {
                echo '<div class="info">✅ Tabela tenant_message_channels existe</div>';
            }
        } catch (\Exception $e) {
            echo '<div class="info" style="color: red;">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $errors[] = 'Erro ao verificar tabela: ' . $e->getMessage();
        }
        echo '</div>';

        // 4. Listar tenants com channels
        echo '<div class="section">';
        echo '<h2>4. Tenants com Channels Configurados</h2>';
        try {
            $stmt = $db->prepare("
                SELECT 
                    tmc.id,
                    tmc.tenant_id,
                    tmc.channel_id,
                    tmc.is_enabled,
                    tmc.webhook_configured,
                    t.name as tenant_name,
                    t.phone as tenant_phone
                FROM tenant_message_channels tmc
                INNER JOIN tenants t ON tmc.tenant_id = t.id
                WHERE tmc.provider = 'wpp_gateway'
                AND tmc.is_enabled = 1
                ORDER BY tmc.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($channels)) {
                echo '<div class="info" style="color: orange;">⚠️ Nenhum tenant com channel WhatsApp configurado</div>';
                $warnings[] = 'Nenhum tenant com channel configurado. Configure um channel antes de testar.';
            } else {
                echo '<div class="info">✅ Encontrados ' . count($channels) . ' tenant(s) com channel configurado</div>';
                echo '<table>';
                echo '<tr><th>Tenant</th><th>Channel ID</th><th>Telefone</th><th>Webhook</th></tr>';
                foreach ($channels as $channel) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($channel['tenant_name']) . ' (ID: ' . $channel['tenant_id'] . ')</td>';
                    echo '<td>' . htmlspecialchars($channel['channel_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($channel['tenant_phone'] ?? 'N/A') . '</td>';
                    echo '<td>' . ($channel['webhook_configured'] ? '✅ Sim' : '❌ Não') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        } catch (\Exception $e) {
            echo '<div class="info" style="color: red;">❌ Erro: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $errors[] = 'Erro ao buscar channels: ' . $e->getMessage();
        }
        echo '</div>';

        // 5. Teste de conexão com gateway (se secret configurado)
        if (!empty($secret) && empty($errors)) {
            echo '<div class="section">';
            echo '<h2>5. Teste de Conexão com Gateway</h2>';
            try {
                $gateway = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
                $result = $gateway->listChannels();
                
                if ($result['success']) {
                    echo '<div class="info">✅ Conexão estabelecida com sucesso</div>';
                    echo '<div class="info">Status HTTP: ' . $result['status'] . '</div>';
                    if (isset($result['raw']['channels'])) {
                        echo '<div class="info">Canais encontrados: ' . count($result['raw']['channels']) . '</div>';
                    }
                } else {
                    echo '<div class="info" style="color: red;">❌ Erro ao conectar: ' . htmlspecialchars($result['error'] ?? 'Erro desconhecido') . '</div>';
                    echo '<div class="info">Status HTTP: ' . ($result['status'] ?? 'N/A') . '</div>';
                    $errors[] = 'Erro ao conectar com gateway: ' . ($result['error'] ?? 'Erro desconhecido');
                }
            } catch (\Exception $e) {
                echo '<div class="info" style="color: red;">❌ Exceção: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $errors[] = 'Exceção ao conectar: ' . $e->getMessage();
            }
            echo '</div>';
        }

        // 6. Formulário de teste (se tudo OK)
        if (empty($errors) && !empty($channels)) {
            $selectedChannel = $channels[0];
            echo '<div class="test-form">';
            echo '<h2>6. Teste de Envio de Mensagem</h2>';
            echo '<form method="POST" id="testForm">';
            echo '<input type="hidden" name="action" value="send_test">';
            echo '<input type="hidden" name="channel_id" value="' . htmlspecialchars($selectedChannel['channel_id']) . '">';
            echo '<input type="hidden" name="tenant_id" value="' . $selectedChannel['tenant_id'] . '">';
            
            echo '<div class="form-group">';
            echo '<label>Channel Selecionado:</label>';
            echo '<input type="text" value="' . htmlspecialchars($selectedChannel['tenant_name']) . ' - ' . htmlspecialchars($selectedChannel['channel_id']) . '" readonly>';
            echo '</div>';
            
            echo '<div class="form-group">';
            echo '<label>Telefone Destino (formato: 5511999999999):</label>';
            echo '<input type="text" name="phone" value="' . htmlspecialchars($selectedChannel['tenant_phone'] ?? '') . '" required placeholder="5511999999999">';
            echo '</div>';
            
            echo '<div class="form-group">';
            echo '<label>Mensagem:</label>';
            echo '<textarea name="message" required>Teste de integração WhatsApp Gateway - PixelHub

Esta é uma mensagem de teste enviada automaticamente pelo sistema.

Data: ' . date('d/m/Y H:i:s') . '</textarea>';
            echo '</div>';
            
            echo '<button type="submit" class="btn">Enviar Mensagem de Teste</button>';
            echo '</form>';
            echo '</div>';
        }

        // Processar envio se solicitado
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_test') {
            echo '<div class="section">';
            echo '<h2>Resultado do Teste</h2>';
            
            try {
                $channelId = $_POST['channel_id'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $message = $_POST['message'] ?? '';
                $tenantId = (int)($_POST['tenant_id'] ?? 0);
                
                if (empty($channelId) || empty($phone) || empty($message)) {
                    throw new \Exception('Dados incompletos');
                }
                
                // Normaliza telefone
                $phoneNormalized = \PixelHub\Services\WhatsAppBillingService::normalizePhone($phone);
                if (empty($phoneNormalized)) {
                    throw new \Exception('Telefone inválido: ' . $phone);
                }
                
                // Envia via gateway
                $gateway = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
                $result = $gateway->sendText(
                    $channelId,
                    $phoneNormalized,
                    $message,
                    [
                        'test' => true,
                        'source' => 'test_web_interface',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                );
                
                if ($result['success']) {
                    echo '<div class="info" style="color: green; font-weight: bold;">✅ Mensagem enviada com sucesso!</div>';
                    echo '<div class="info">Message ID: ' . htmlspecialchars($result['message_id'] ?? 'N/A') . '</div>';
                    echo '<div class="info">Status HTTP: ' . $result['status'] . '</div>';
                    
                    if (isset($result['raw'])) {
                        echo '<div class="info">Resposta do gateway:<br><pre>' . htmlspecialchars(json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></div>';
                    }
                } else {
                    echo '<div class="info" style="color: red; font-weight: bold;">❌ Erro ao enviar mensagem</div>';
                    echo '<div class="info">Erro: ' . htmlspecialchars($result['error'] ?? 'Erro desconhecido') . '</div>';
                    echo '<div class="info">Status HTTP: ' . ($result['status'] ?? 'N/A') . '</div>';
                    
                    if (isset($result['raw'])) {
                        echo '<div class="info">Resposta do gateway:<br><pre>' . htmlspecialchars(json_encode($result['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre></div>';
                    }
                }
            } catch (\Exception $e) {
                echo '<div class="info" style="color: red; font-weight: bold;">❌ Exceção: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            echo '</div>';
        }
        ?>

        <div class="section">
            <h2>📋 Próximos Passos</h2>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <?php if (!empty($errors)): ?>
                    <li style="color: red;">Corrija os erros acima antes de continuar</li>
                <?php endif; ?>
                <?php if (empty($secret)): ?>
                    <li>Configure <code>WPP_GATEWAY_SECRET</code> no arquivo <code>.env</code></li>
                <?php endif; ?>
                <?php if (empty($channels)): ?>
                    <li>Configure um channel WhatsApp para um tenant na tabela <code>tenant_message_channels</code></li>
                <?php endif; ?>
                <li>Acesse o <a href="/communication-hub" target="_blank">Painel de Comunicação</a> para enviar mensagens pela interface</li>
                <li>Verifique os logs em caso de erro</li>
            </ul>
        </div>
    </div>
</body>
</html>

