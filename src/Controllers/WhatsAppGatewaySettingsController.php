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

            $logs[] = "🔑 Secret (preview): " . substr($secret, 0, 8) . "..." . substr($secret, -4);
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
}

