<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

/**
 * Controller para gerenciar configurações do WhatsApp Gateway
 */
class WhatsAppGatewaySettingsController extends Controller
{
    /**
     * Exibe formulário de configurações do WhatsApp Gateway
     * 
     * GET /settings/whatsapp-gateway
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            // Força recarregar .env para garantir valores atualizados
            Env::load(__DIR__ . '/../../.env', true);
            
            $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
            $secretRaw = Env::get('WPP_GATEWAY_SECRET', '');
            $webhookUrl = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_URL', '');
            $webhookSecret = Env::get('PIXELHUB_WHATSAPP_WEBHOOK_SECRET', '');
            
            // Log para debug
            if (function_exists('pixelhub_log')) {
                pixelhub_log('[WhatsAppGatewaySettings] Carregando configurações. BaseURL do .env: ' . $baseUrl);
            }
            
            // Garante que baseUrl seja uma URL válida (não um caminho relativo)
            if (!empty($baseUrl) && !preg_match('/^https?:\/\//', $baseUrl)) {
                // Se não começa com http:// ou https://, assume que é um caminho relativo incorreto
                if (function_exists('pixelhub_log')) {
                    pixelhub_log('[WhatsAppGatewaySettings] AVISO: BaseURL inválida detectada: ' . $baseUrl . '. Corrigindo para padrão.');
                }
                $baseUrl = 'https://wpp.pixel12digital.com.br';
            }
            
            $hasSecret = !empty($secretRaw);
            
        } catch (\Exception $e) {
            $baseUrl = 'https://wpp.pixel12digital.com.br';
            $hasSecret = false;
            $webhookUrl = '';
            $webhookSecret = '';
            $error = $e->getMessage();
        }

        // Garante valor padrão correto
        $baseUrl = !empty($baseUrl) && filter_var($baseUrl, FILTER_VALIDATE_URL) 
            ? $baseUrl 
            : 'https://wpp.pixel12digital.com.br';

        $this->view('settings.whatsapp_gateway', [
            'baseUrl' => $baseUrl,
            'hasSecret' => $hasSecret ?? false,
            'webhookUrl' => $webhookUrl ?? '',
            'webhookSecret' => $webhookSecret ?? '',
            'error' => $error ?? null,
        ]);
    }

    /**
     * Salva configurações do WhatsApp Gateway
     * 
     * POST /settings/whatsapp-gateway
     */
    public function update(): void
    {
        Auth::requireInternal();

        $baseUrl = trim($_POST['base_url'] ?? 'https://wpp.pixel12digital.com.br');
        $secret = trim($_POST['secret'] ?? '');
        $webhookUrl = trim($_POST['webhook_url'] ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');

        // Validações
        if (empty($baseUrl)) {
            $this->redirect('/settings/whatsapp-gateway?error=base_url_required');
            return;
        }

        // Garante que baseUrl seja uma URL absoluta (não caminho relativo)
        if (!preg_match('/^https?:\/\//', $baseUrl)) {
            // Se não começa com http:// ou https://, é inválido
            $this->redirect('/settings/whatsapp-gateway?error=invalid_base_url&message=' . urlencode('A Base URL deve ser uma URL completa começando com http:// ou https://'));
            return;
        }

        // Valida URL
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $this->redirect('/settings/whatsapp-gateway?error=invalid_base_url&message=' . urlencode('URL inválida. Use uma URL completa como: https://wpp.pixel12digital.com.br'));
            return;
        }

        // Valida webhook URL se fornecida
        if (!empty($webhookUrl) && !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $this->redirect('/settings/whatsapp-gateway?error=invalid_webhook_url');
            return;
        }

        try {
            // Verifica se há secret configurado atualmente
            $currentSecret = Env::get('WPP_GATEWAY_SECRET', '');
            $hasCurrentSecret = !empty($currentSecret);
            
            // Se não foi fornecido novo secret e não há secret atual, é obrigatório
            if (empty($secret) && !$hasCurrentSecret) {
                $this->redirect('/settings/whatsapp-gateway?error=secret_required&message=' . urlencode('O secret é obrigatório na primeira configuração.'));
                return;
            }
            
            // Atualiza o arquivo .env
            $envVars = [
                'WPP_GATEWAY_BASE_URL' => rtrim($baseUrl, '/'),
            ];
            
            // Só atualiza o secret se foi fornecido um novo valor
            if (!empty($secret)) {
                // Criptografa o secret antes de salvar
                $secretEncrypted = CryptoHelper::encrypt($secret);
                $envVars['WPP_GATEWAY_SECRET'] = $secretEncrypted;
            }

            if (!empty($webhookUrl)) {
                $envVars['PIXELHUB_WHATSAPP_WEBHOOK_URL'] = rtrim($webhookUrl, '/');
            }

            if (!empty($webhookSecret)) {
                $envVars['PIXELHUB_WHATSAPP_WEBHOOK_SECRET'] = $webhookSecret;
            }

            $this->updateEnvFile($envVars);

            // Recarrega variáveis de ambiente (força recarregar)
            Env::load(__DIR__ . '/../../.env', true);

            // Para testar a conexão, usa o secret fornecido ou o atual
            $secretForTest = !empty($secret) ? $secret : null;
            
            // Se não forneceu novo secret, precisa descriptografar o atual para testar
            if (empty($secretForTest) && $hasCurrentSecret) {
                try {
                    $secretForTest = CryptoHelper::decrypt($currentSecret);
                } catch (\Exception $e) {
                    // Se não conseguir descriptografar, tenta usar diretamente (pode não estar criptografado)
                    $secretForTest = $currentSecret;
                }
            }
            
            // Testa a conexão apenas se tiver secret disponível
            $testResult = ['success' => false, 'message' => 'Secret não disponível para teste'];
            if (!empty($secretForTest)) {
                $testResult = $this->testConnectionInternal($baseUrl, $secretForTest);
            }
            
            $webhookConfigured = false;
            $webhookMessage = '';
            
            // Se webhook URL foi fornecida, configura no gateway
            if (!empty($webhookUrl) && !empty($secretForTest) && $testResult['success']) {
                try {
                    $gateway = new WhatsAppGatewayClient($baseUrl, $secretForTest);
                    $webhookResult = $gateway->setGlobalWebhook($webhookUrl, !empty($webhookSecret) ? $webhookSecret : null);
                    
                    if ($webhookResult['success']) {
                        $webhookConfigured = true;
                        $webhookMessage = ' Webhook configurado no gateway com sucesso.';
                    } else {
                        $webhookMessage = ' Aviso: Não foi possível configurar o webhook no gateway: ' . ($webhookResult['error'] ?? 'Erro desconhecido');
                    }
                } catch (\Exception $e) {
                    $webhookMessage = ' Aviso: Erro ao configurar webhook: ' . $e->getMessage();
                }
            }
            
            if ($testResult['success']) {
                $message = 'Configurações atualizadas com sucesso! A conexão com o gateway foi validada.' . $webhookMessage;
                $this->redirect('/settings/whatsapp-gateway?success=updated&message=' . urlencode($message));
            } else {
                $message = 'Configurações salvas, mas não foi possível validar a conexão: ' . $testResult['message'] . $webhookMessage;
                $this->redirect('/settings/whatsapp-gateway?warning=connection_not_validated&message=' . urlencode($message));
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar configurações do WhatsApp Gateway: " . $e->getMessage());
            $this->redirect('/settings/whatsapp-gateway?error=update_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Atualiza variáveis no arquivo .env
     */
    private function updateEnvFile(array $variables): void
    {
        $envPath = __DIR__ . '/../../.env';
        
        if (!file_exists($envPath)) {
            // Cria arquivo .env se não existir
            $content = "# Configurações do Pixel Hub\n\n";
            foreach ($variables as $key => $value) {
                $content .= "{$key}={$value}\n";
            }
            file_put_contents($envPath, $content);
            return;
        }

        // Lê o arquivo .env
        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        $updated = [];
        $found = [];

        // Processa cada linha
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Mantém comentários e linhas vazias
            if (empty($trimmed) || strpos($trimmed, '#') === 0) {
                $updated[] = $line;
                continue;
            }

            // Verifica se a linha contém alguma das variáveis que queremos atualizar
            $lineUpdated = false;
            foreach ($variables as $key => $value) {
                if (strpos($trimmed, $key . '=') === 0) {
                    // Atualiza a variável
                    // Log para debug
                    if (function_exists('pixelhub_log') && $key === 'WPP_GATEWAY_BASE_URL') {
                        pixelhub_log("[WhatsAppGatewaySettings] Atualizando {$key}: valor antigo = " . substr($trimmed, strlen($key) + 1) . ", valor novo = {$value}");
                    }
                    $updated[] = "{$key}={$value}";
                    $found[$key] = true;
                    $lineUpdated = true;
                    break;
                }
            }

            // Se não foi atualizada, mantém a linha original
            if (!$lineUpdated) {
                $updated[] = $line;
            }
        }

        // Adiciona variáveis que não existiam no arquivo
        foreach ($variables as $key => $value) {
            if (!isset($found[$key])) {
                $updated[] = "{$key}={$value}";
            }
        }

        // Salva o arquivo
        file_put_contents($envPath, implode("\n", $updated) . "\n");
        
        // Log para debug
        if (function_exists('pixelhub_log')) {
            pixelhub_log('[WhatsAppGatewaySettings] Arquivo .env atualizado. Variáveis: ' . json_encode($variables));
        }
        
        // Recarrega as variáveis de ambiente (força recarregar)
        Env::load($envPath, true);
    }

    /**
     * Testa a conexão com o gateway (método privado para uso interno)
     */
    private function testConnectionInternal(string $baseUrl, string $secret): array
    {
        try {
            $gateway = new WhatsAppGatewayClient($baseUrl, $secret);
            $result = $gateway->listChannels();
            
            if ($result['success']) {
                return ['success' => true, 'message' => 'Conexão estabelecida com sucesso'];
            } else {
                return ['success' => false, 'message' => $result['error'] ?? 'Erro desconhecido'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Testa a conexão com o gateway e retorna logs detalhados
     * 
     * POST /settings/whatsapp-gateway/test
     */
    public function testConnection(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        try {
            $logs = [];
            $logs[] = "🔍 Iniciando teste de conexão com WhatsApp Gateway...";
            $logs[] = "";

            // Carrega configurações
            $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
            $secretRaw = Env::get('WPP_GATEWAY_SECRET', '');
            
            $logs[] = "✅ Configurações carregadas";
            $logs[] = "📋 Base URL: {$baseUrl}";
            
            if (empty($secretRaw)) {
                $this->json([
                    'success' => false,
                    'message' => 'Secret não configurado',
                    'logs' => array_merge($logs, [
                        '❌ Nenhum secret encontrado no .env',
                        'Configure o secret primeiro antes de testar.'
                    ])
                ], 400);
                return;
            }

            // Descriptografa o secret
            $logs[] = "🔐 Processando secret...";
            try {
                $secret = CryptoHelper::decrypt($secretRaw);
                if (empty($secret)) {
                    // Se descriptografia retornou vazio, pode ser que não esteja criptografado
                    $secret = $secretRaw;
                    $logs[] = "⚠️ Secret não parece estar criptografado, usando diretamente";
                } else {
                    $logs[] = "✅ Secret descriptografado com sucesso";
                }
            } catch (\Exception $e) {
                // Se falhar, tenta usar diretamente (pode não estar criptografado)
                $secret = $secretRaw;
                $logs[] = "⚠️ Erro ao descriptografar, usando secret diretamente: " . $e->getMessage();
            }

            // Log do secret descriptografado (para comparação com send_real)
            $secretPreview = !empty($secret) 
                ? (substr($secret, 0, 4) . '...' . substr($secret, -4) . ' (len=' . strlen($secret) . ')')
                : 'VAZIO';
            $logs[] = "🔑 Secret (preview): {$secretPreview}";
            error_log("[WhatsAppGatewaySettings::testConnection] test_connection -> secret (descriptografado) preview: {$secretPreview}");
            $logs[] = "";

            // Teste 1: Listar canais
            $logs[] = "📡 Teste 1: Listando canais (GET /api/channels)...";
            
            $gateway = new WhatsAppGatewayClient($baseUrl, $secret);
            $startTime = microtime(true);
            $result = $gateway->listChannels();
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $logs[] = "⏱️ Tempo de resposta: {$duration}ms";
            $logs[] = "📊 Status HTTP: " . ($result['status'] ?? 'N/A');

            if ($result['success']) {
                $logs[] = "✅ Teste 1: SUCESSO - Conexão estabelecida com sucesso!";
                
                $channels = $result['raw']['channels'] ?? [];
                $channelsCount = is_array($channels) ? count($channels) : 0;
                $logs[] = "📦 Canais encontrados: {$channelsCount}";
                
                if ($channelsCount > 0) {
                    $logs[] = "";
                    $logs[] = "📋 Lista de canais:";
                    foreach (array_slice($channels, 0, 5) as $index => $channel) {
                        $channelId = $channel['id'] ?? $channel['channel_id'] ?? 'N/A';
                        $status = $channel['status'] ?? $channel['connected'] ?? 'N/A';
                        $logs[] = "   " . ($index + 1) . ". {$channelId} (Status: {$status})";
                    }
                    if ($channelsCount > 5) {
                        $logs[] = "   ... e mais " . ($channelsCount - 5) . " canal(is)";
                    }
                }

                // Teste 2: Verificar status do gateway
                $logs[] = "";
                $logs[] = "📡 Teste 2: Verificando status do gateway...";
                
                // Tenta obter informações de um canal se existir
                if ($channelsCount > 0) {
                    $firstChannel = $channels[0];
                    $channelId = $firstChannel['id'] ?? $firstChannel['channel_id'] ?? null;
                    
                    if ($channelId) {
                        $channelResult = $gateway->getChannel($channelId);
                        if ($channelResult['success']) {
                            $logs[] = "✅ Teste 2: SUCESSO - Informações do canal obtidas!";
                            $channelData = $channelResult['raw'] ?? [];
                            if (isset($channelData['status'])) {
                                $logs[] = "   Status: {$channelData['status']}";
                            }
                            if (isset($channelData['connected'])) {
                                $logs[] = "   Conectado: " . ($channelData['connected'] ? 'Sim' : 'Não');
                            }
                        } else {
                            $logs[] = "⚠️ Teste 2: Falhou (mas o teste principal foi bem-sucedido)";
                        }
                    }
                } else {
                    $logs[] = "ℹ️ Teste 2: Nenhum canal disponível para testar";
                }

                $this->json([
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso! O gateway está acessível.',
                    'logs' => $logs,
                    'http_code' => $result['status'] ?? 200,
                    'duration_ms' => $duration,
                    'channels_count' => $channelsCount,
                ]);
                return;

            } else {
                $error = $result['error'] ?? 'Erro desconhecido';
                $logs[] = "❌ Teste 1: FALHOU - {$error}";
                $logs[] = "🔍 Detalhes: " . ($result['status'] ?? 'N/A');
                
                $this->json([
                    'success' => false,
                    'message' => $error,
                    'logs' => $logs,
                    'http_code' => $result['status'] ?? null,
                ], $result['status'] ?? 500);
                return;
            }

        } catch (\Exception $e) {
            $logs[] = "";
            $logs[] = "💥 Erro inesperado: " . $e->getMessage();
            $logs[] = "📍 Arquivo: " . $e->getFile() . " (Linha " . $e->getLine() . ")";
            
            error_log("Erro ao testar conexão com WhatsApp Gateway: " . $e->getMessage());
            
            $this->json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
                'logs' => $logs
            ], 500);
        }
    }

    /**
     * Verifica se todos os arquivos necessários estão presentes em produção
     * 
     * GET /settings/whatsapp-gateway/check
     */
    public function checkProduction(): void
    {
        // Permite acesso sem autenticação para verificação rápida
        // Mas idealmente só deve ser usado em ambiente de desenvolvimento/staging
        
        header('Content-Type: text/html; charset=utf-8');
        
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificação WhatsApp Gateway - Produção</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { 
            color: #023A8D; 
            border-bottom: 3px solid #023A8D;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 30px;
            border-left: 4px solid #023A8D;
            padding-left: 10px;
        }
        .ok { 
            color: green; 
            font-weight: bold;
        }
        .error { 
            color: red; 
            font-weight: bold;
        }
        .warning {
            color: orange;
            font-weight: bold;
        }
        .info { 
            color: blue; 
        }
        pre { 
            background: #f5f5f5; 
            padding: 15px; 
            border-radius: 5px;
            border-left: 4px solid #023A8D;
            overflow-x: auto;
        }
        .check-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #ddd;
            padding-left: 15px;
        }
        .check-item.ok { border-left-color: green; }
        .check-item.error { border-left-color: red; }
        .check-item.warning { border-left-color: orange; }
        .summary {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #023A8D;
        }
        .summary h3 {
            margin-top: 0;
            color: #023A8D;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
<div class=\"container\">";

        echo "<h1>🔍 Verificação WhatsApp Gateway - Produção</h1>\n";
        echo "<p>Este script verifica se todos os arquivos e configurações necessários para o WhatsApp Gateway estão presentes.</p>\n";

        $checks = [];
        $errors = [];
        $warnings = [];

        // 1. Verificar arquivos essenciais
        echo "<h2>1. Arquivos Essenciais</h2>\n";

        $requiredFiles = [
            'src/Controllers/WhatsAppGatewaySettingsController.php' => 'Controller principal de configurações',
            'src/Controllers/WhatsAppGatewayTestController.php' => 'Controller de testes',
            'src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php' => 'Cliente do gateway',
            'views/settings/whatsapp_gateway.php' => 'View de configurações',
            'views/settings/whatsapp_gateway_test.php' => 'View de testes',
        ];

        foreach ($requiredFiles as $file => $description) {
            $fullPath = __DIR__ . '/../../' . $file;
            $exists = file_exists($fullPath);
            
            if ($exists) {
                $checks[] = ['type' => 'ok', 'message' => "✅ {$description}: <code>{$file}</code>"];
                echo "<div class=\"check-item ok\">✅ {$description}: <code>{$file}</code></div>\n";
            } else {
                $error = "❌ {$description}: <code>{$file}</code> NÃO ENCONTRADO";
                $checks[] = ['type' => 'error', 'message' => $error];
                $errors[] = $error;
                echo "<div class=\"check-item error\">{$error}</div>\n";
            }
        }

        // 2. Verificar rotas no index.php
        echo "<h2>2. Rotas Registradas</h2>\n";

        $indexPath = __DIR__ . '/../../public/index.php';
        if (file_exists($indexPath)) {
            $indexContent = file_get_contents($indexPath);
            
            $requiredRoutes = [
                '/settings/whatsapp-gateway' => 'Rota principal de configurações',
                '/settings/whatsapp-gateway/test' => 'Rota de testes',
                'WhatsAppGatewaySettingsController' => 'Controller de configurações referenciado',
                'WhatsAppGatewayTestController' => 'Controller de testes referenciado',
            ];
            
            foreach ($requiredRoutes as $search => $description) {
                if (strpos($indexContent, $search) !== false) {
                    $checks[] = ['type' => 'ok', 'message' => "✅ {$description}: encontrada em index.php"];
                    echo "<div class=\"check-item ok\">✅ {$description}: encontrada em <code>index.php</code></div>\n";
                } else {
                    $error = "❌ {$description}: NÃO encontrada em index.php";
                    $checks[] = ['type' => 'error', 'message' => $error];
                    $errors[] = $error;
                    echo "<div class=\"check-item error\">{$error}</div>\n";
                }
            }
        } else {
            $error = "❌ Arquivo index.php não encontrado!";
            $checks[] = ['type' => 'error', 'message' => $error];
            $errors[] = $error;
            echo "<div class=\"check-item error\">{$error}</div>\n";
        }

        // 3. Verificar menu no layout
        echo "<h2>3. Menu de Navegação</h2>\n";

        $layoutPath = __DIR__ . '/../../views/layout/main.php';
        if (file_exists($layoutPath)) {
            $layoutContent = file_get_contents($layoutPath);
            
            if (strpos($layoutContent, '/settings/whatsapp-gateway') !== false) {
                $checks[] = ['type' => 'ok', 'message' => '✅ Link do WhatsApp Gateway encontrado no menu'];
                echo "<div class=\"check-item ok\">✅ Link do WhatsApp Gateway encontrado no menu (main.php)</div>\n";
            } else {
                $error = "❌ Link do WhatsApp Gateway NÃO encontrado no menu!";
                $checks[] = ['type' => 'error', 'message' => $error];
                $errors[] = $error;
                echo "<div class=\"check-item error\">{$error}</div>\n";
            }
            
            if (strpos($layoutContent, 'WhatsApp Gateway') !== false) {
                $checks[] = ['type' => 'ok', 'message' => '✅ Texto "WhatsApp Gateway" encontrado no menu'];
                echo "<div class=\"check-item ok\">✅ Texto \"WhatsApp Gateway\" encontrado no menu</div>\n";
            } else {
                $warning = "⚠️ Texto \"WhatsApp Gateway\" não encontrado no menu (pode estar usando outra descrição)";
                $checks[] = ['type' => 'warning', 'message' => $warning];
                $warnings[] = $warning;
                echo "<div class=\"check-item warning\">{$warning}</div>\n";
            }
        } else {
            $error = "❌ Arquivo views/layout/main.php não encontrado!";
            $checks[] = ['type' => 'error', 'message' => $error];
            $errors[] = $error;
            echo "<div class=\"check-item error\">{$error}</div>\n";
        }

        // Resumo
        echo "<div class=\"summary\">";
        echo "<h3>📊 Resumo da Verificação</h3>\n";

        $okCount = count(array_filter($checks, fn($c) => $c['type'] === 'ok'));
        $errorCount = count($errors);
        $warningCount = count($warnings);

        echo "<p><strong>Total de verificações:</strong> " . count($checks) . "</p>\n";
        echo "<p class=\"ok\">✅ Sucesso: {$okCount}</p>\n";
        if ($warningCount > 0) {
            echo "<p class=\"warning\">⚠️ Avisos: {$warningCount}</p>\n";
        }
        if ($errorCount > 0) {
            echo "<p class=\"error\">❌ Erros: {$errorCount}</p>\n";
        }

        if ($errorCount === 0) {
            echo "<p class=\"ok\" style=\"font-size: 18px; margin-top: 20px;\">✅ <strong>Todos os arquivos essenciais estão presentes!</strong></p>\n";
            echo "<p>Se ainda não estiver vendo o WhatsApp Gateway no menu, pode ser:</p>\n";
            echo "<ul>\n";
            echo "<li>Cache do navegador - limpe o cache ou use Ctrl+F5</li>\n";
            echo "<li>Cache do servidor - reinicie o servidor web ou limpe opcache do PHP</li>\n";
            echo "<li>Permissões de arquivo - verifique se os arquivos têm permissões corretas</li>\n";
            echo "</ul>\n";
        } else {
            echo "<p class=\"error\" style=\"font-size: 18px; margin-top: 20px;\">❌ <strong>Encontrados {$errorCount} erro(s) que precisam ser corrigidos!</strong></p>\n";
            echo "<p><strong>Arquivos faltando:</strong></p>\n";
            echo "<ul>\n";
            foreach ($errors as $error) {
                echo "<li class=\"error\">" . strip_tags($error) . "</li>\n";
            }
            echo "</ul>\n";
            echo "<p><strong>Ação necessária:</strong> Faça upload dos arquivos faltantes do ambiente local para produção.</p>\n";
        }

        echo "</div>";

        echo "</div></body></html>";
        exit;
    }
}

