<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;

/**
 * Controller para gerenciar configurações de IA (OpenAI)
 */
class AISettingsController extends Controller
{
    /**
     * Exibe formulário de configurações de IA
     * 
     * GET /settings/ai
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            // Verifica se há chave configurada
            $apiKeyRaw = Env::get('OPENAI_API_KEY');
            $hasApiKey = !empty($apiKeyRaw);
            
            // Carrega configurações
            $isActive = Env::get('OPENAI_ACTIVE', '1') === '1';
            $model = Env::get('OPENAI_MODEL', 'gpt-4o');
            $temperature = Env::get('OPENAI_TEMPERATURE', '0.7');
            $maxTokens = Env::get('OPENAI_MAX_TOKENS', '800');
            
        } catch (\Exception $e) {
            $hasApiKey = false;
            $isActive = true;
            $model = 'gpt-4o';
            $temperature = '0.7';
            $maxTokens = '800';
            $error = $e->getMessage();
        }

        $this->view('settings.ai', [
            'hasApiKey' => $hasApiKey ?? false,
            'isActive' => $isActive ?? true,
            'model' => $model ?? 'gpt-4o',
            'temperature' => $temperature ?? '0.7',
            'maxTokens' => $maxTokens ?? '800',
            'error' => $error ?? null,
        ]);
    }

    /**
     * Salva configurações de IA
     * 
     * POST /settings/ai
     */
    public function update(): void
    {
        Auth::requireInternal();

        // Se não for enviada nova chave, mantém a existente
        $apiKey = trim($_POST['api_key'] ?? '');
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? '1' : '0';
        $model = trim($_POST['model'] ?? 'gpt-4o');
        $temperature = trim($_POST['temperature'] ?? '0.7');
        $maxTokens = trim($_POST['max_tokens'] ?? '800');

        // Validações
        if (!empty($apiKey)) {
            // Valida formato básico da chave OpenAI (geralmente começa com "sk-")
            if (strlen($apiKey) < 20 || (strpos($apiKey, 'sk-') !== 0 && strpos($apiKey, 'pk-') !== 0)) {
                $this->redirect('/settings/ai?warning=invalid_format&message=' . urlencode('A chave de API parece estar em formato inválido. Chaves OpenAI geralmente começam com "sk-" ou "pk-".'));
                // Continua mesmo assim, pode ser uma chave válida de outro formato
            }
        }

        // Valida temperatura (0.0 a 2.0)
        $temperatureFloat = (float) $temperature;
        if ($temperatureFloat < 0.0 || $temperatureFloat > 2.0) {
            $temperature = '0.7';
        }

        // Valida max tokens (mínimo 100, máximo 4096)
        $maxTokensInt = (int) $maxTokens;
        if ($maxTokensInt < 100 || $maxTokensInt > 4096) {
            $maxTokens = '800';
        }

        // Modelos válidos
        $validModels = ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-4', 'gpt-3.5-turbo'];
        if (!in_array($model, $validModels)) {
            $model = 'gpt-4o';
        }

        try {
            // Se não foi enviada nova chave, mantém a existente mas atualiza outras configurações
            $envVars = [];
            
            // Só adiciona chave se foi fornecida
            if (!empty($apiKey)) {
                // Criptografa a chave de API antes de salvar
                $apiKeyEncrypted = CryptoHelper::encrypt($apiKey);
                $envVars['OPENAI_API_KEY'] = $apiKeyEncrypted;
            }
            
            // Sempre atualiza outras configurações
            $envVars['OPENAI_ACTIVE'] = $isActive;
            $envVars['OPENAI_MODEL'] = $model;
            $envVars['OPENAI_TEMPERATURE'] = (string) $temperatureFloat;
            $envVars['OPENAI_MAX_TOKENS'] = (string) $maxTokensInt;

            
            // Atualiza o arquivo .env
            $this->updateEnvFile($envVars);

            // Recarrega variáveis de ambiente
            Env::load();

            // Se forneceu chave nova, testa
            if (!empty($apiKey)) {
                $testResult = $this->testApiKey($apiKey);
                
                if ($testResult['success']) {
                    $this->redirect('/settings/ai?success=updated&message=' . urlencode('Configurações atualizadas com sucesso! A chave de API foi validada.'));
                } else {
                    $this->redirect('/settings/ai?warning=key_not_validated&message=' . urlencode('Configurações salvas, mas não foi possível validar a chave: ' . $testResult['message']));
                }
            } else {
                $this->redirect('/settings/ai?success=updated&message=' . urlencode('Configurações atualizadas com sucesso!'));
            }
        } catch (\Exception $e) {
            error_log("Erro ao atualizar configurações de IA: " . $e->getMessage());
            $this->redirect('/settings/ai?error=update_failed&message=' . urlencode($e->getMessage()));
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
     * 
     * POST /settings/ai/test
     */
    public function testConnection(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        try {
            $logs = [];
            $logs[] = "🔍 Iniciando teste de conexão com OpenAI...";
            $logs[] = "";

            // Carrega chave do .env
            $apiKeyRaw = Env::get('OPENAI_API_KEY');
            if (empty($apiKeyRaw)) {
                $this->json([
                    'success' => false,
                    'message' => 'Chave de API não configurada',
                    'logs' => array_merge($logs, [
                        '❌ Nenhuma chave de API encontrada no .env',
                        'Configure a chave primeiro antes de testar.'
                    ])
                ], 400);
                return;
            }

            // Descriptografa se necessário
            $apiKey = $this->decryptApiKey($apiKeyRaw);
            if (empty($apiKey)) {
                $this->json([
                    'success' => false,
                    'message' => 'Chave de API inválida ou não pode ser descriptografada',
                    'logs' => array_merge($logs, [
                        '❌ Erro ao processar chave de API',
                        'Verifique se a chave está corretamente configurada.'
                    ])
                ], 400);
                return;
            }

            $logs[] = "✅ Chave de API carregada";
            $logs[] = "🔑 Chave (preview): " . substr($apiKey, 0, 8) . "..." . substr($apiKey, -4);
            $logs[] = "";
            $logs[] = "📡 Testando conexão com OpenAI (GET /v1/models)...";

            // Testa com uma requisição simples ao OpenAI
            $url = 'https://api.openai.com/v1/models';
            $ch = curl_init($url);
            
            $startTime = microtime(true);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $logs[] = "⏱️ Tempo de resposta: {$duration}ms";
            $logs[] = "📊 Código HTTP: {$httpCode}";

            if ($curlError) {
                $logs[] = "❌ Erro cURL: {$curlError}";
                $this->json([
                    'success' => false,
                    'message' => 'Erro de conexão: ' . $curlError,
                    'logs' => $logs,
                    'http_code' => null,
                ], 500);
                return;
            }

            if ($httpCode === 200) {
                $responseData = json_decode($response, true);
                $modelsCount = is_array($responseData['data'] ?? null) ? count($responseData['data']) : 0;
                
                $logs[] = "✅ Teste bem-sucedido!";
                $logs[] = "📦 Modelos disponíveis: {$modelsCount}";
                
                $this->json([
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso! A chave de API está válida.',
                    'logs' => $logs,
                    'http_code' => $httpCode,
                    'duration_ms' => $duration,
                    'models_count' => $modelsCount,
                ]);
            } elseif ($httpCode === 401) {
                $logs[] = "❌ Falha: Chave de API inválida ou expirada";
                $this->json([
                    'success' => false,
                    'message' => 'Chave de API inválida ou expirada',
                    'logs' => $logs,
                    'http_code' => $httpCode,
                ], 401);
            } else {
                $logs[] = "❌ Falha: Código HTTP {$httpCode}";
                $this->json([
                    'success' => false,
                    'message' => "Erro HTTP {$httpCode}",
                    'logs' => $logs,
                    'http_code' => $httpCode,
                ], $httpCode);
            }

        } catch (\Exception $e) {
            $logs[] = "";
            $logs[] = "💥 Erro inesperado: " . $e->getMessage();
            
            error_log("Erro ao testar conexão com OpenAI: " . $e->getMessage());
            
            $this->json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
                'logs' => $logs
            ], 500);
        }
    }

    /**
     * Descriptografa a chave de API se necessário
     */
    private function decryptApiKey(?string $apiKeyRaw): string
    {
        if (empty($apiKeyRaw)) {
            return '';
        }
        
        $apiKeyRaw = trim($apiKeyRaw);
        
        // Chaves OpenAI geralmente começam com "sk-" ou "pk-"
        if (strpos($apiKeyRaw, 'sk-') === 0 || strpos($apiKeyRaw, 'pk-') === 0) {
            return $apiKeyRaw;
        }
        
        // Se é muito longa (>100 chars), provavelmente é criptografada
        if (strlen($apiKeyRaw) > 100) {
            try {
                $decrypted = CryptoHelper::decrypt($apiKeyRaw);
                if (!empty($decrypted) && (strpos($decrypted, 'sk-') === 0 || strpos($decrypted, 'pk-') === 0)) {
                    return $decrypted;
                }
            } catch (\Exception $e) {
                error_log("Erro ao descriptografar chave OpenAI: " . $e->getMessage());
                return '';
            }
        }
        
        return $apiKeyRaw;
    }

    /**
     * Testa se a chave de API é válida (método privado para uso interno)
     */
    private function testApiKey(string $apiKey): array
    {
        try {
            // Testa com uma requisição simples ao OpenAI (models endpoint)
            $ch = curl_init('https://api.openai.com/v1/models');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
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
}

