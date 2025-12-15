<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\AsaasConfig;
use PixelHub\Core\Env;

/**
 * Controller para gerenciar configurações do Asaas
 */
class AsaasSettingsController extends Controller
{
    /**
     * Exibe formulário de configurações do Asaas
     * 
     * GET /settings/asaas
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            $config = AsaasConfig::getConfig();
            $apiKey = $config['api_key'] ?? '';
            $env = $config['env'] ?? 'production';
            $webhookToken = $config['webhook_token'] ?? '';
            
            // Mascara a chave para exibição (mostra apenas últimos 4 caracteres)
            $apiKeyMasked = $this->maskApiKey($apiKey);
            
        } catch (\Exception $e) {
            // Se não conseguir carregar, mostra campos vazios
            $apiKey = '';
            $apiKeyMasked = '';
            $env = 'production';
            $webhookToken = '';
            $error = $e->getMessage();
        }

        $this->view('settings.asaas', [
            'apiKey' => $apiKey,
            'apiKeyMasked' => $apiKeyMasked ?? '',
            'env' => $env ?? 'production',
            'webhookToken' => $webhookToken ?? '',
            'error' => $error ?? null,
        ]);
    }

    /**
     * Salva configurações do Asaas
     * 
     * POST /settings/asaas
     */
    public function update(): void
    {
        Auth::requireInternal();

        $apiKey = trim($_POST['api_key'] ?? '');
        $env = trim($_POST['env'] ?? 'production');
        $webhookToken = trim($_POST['webhook_token'] ?? '');

        // Validações
        if (empty($apiKey)) {
            $this->redirect('/settings/asaas?error=api_key_required');
            return;
        }

        if (!in_array($env, ['production', 'sandbox'])) {
            $env = 'production';
        }

        try {
            // Atualiza o arquivo .env
            $this->updateEnvFile([
                'ASAAS_API_KEY' => $apiKey,
                'ASAAS_ENV' => $env,
                'ASAAS_WEBHOOK_TOKEN' => $webhookToken,
            ]);

            // Recarrega variáveis de ambiente e limpa cache
            Env::load();
            AsaasConfig::clearCache();

            // Testa a chave fazendo uma requisição simples
            $testResult = $this->testApiKey($apiKey, $env);
            
            if ($testResult['success']) {
                $this->redirect('/settings/asaas?success=updated&message=' . urlencode('Configurações atualizadas com sucesso! A chave de API foi validada.'));
            } else {
                $this->redirect('/settings/asaas?warning=key_not_validated&message=' . urlencode('Configurações salvas, mas não foi possível validar a chave: ' . $testResult['message']));
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar configurações do Asaas: " . $e->getMessage());
            $this->redirect('/settings/asaas?error=update_failed&message=' . urlencode($e->getMessage()));
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
        
        // Recarrega as variáveis de ambiente
        Env::load($envPath);
    }

    /**
     * Testa se a chave de API é válida
     */
    private function testApiKey(string $apiKey, string $env): array
    {
        try {
            $baseUrl = $env === 'sandbox' 
                ? 'https://sandbox.asaas.com/api/v3' 
                : 'https://www.asaas.com/api/v3';

            $ch = curl_init($baseUrl . '/customers?limit=1');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'access_token: ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return ['success' => true, 'message' => 'Chave válida'];
            } elseif ($httpCode === 401) {
                return ['success' => false, 'message' => 'Chave de API inválida ou expirada'];
            } else {
                return ['success' => false, 'message' => "Erro HTTP {$httpCode}"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }


    /**
     * Mascara a chave de API para exibição
     */
    private function maskApiKey(string $apiKey): string
    {
        if (empty($apiKey)) {
            return '';
        }

        $length = strlen($apiKey);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        // Mostra primeiros 4 e últimos 4 caracteres
        $start = substr($apiKey, 0, 4);
        $end = substr($apiKey, -4);
        $middle = str_repeat('*', max(0, $length - 8));
        
        return $start . $middle . $end;
    }
}

