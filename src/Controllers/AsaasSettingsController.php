<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\AsaasConfig;
use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;

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
            // Não expõe a chave descriptografada na view
            $hasApiKey = !empty($config['api_key']);
            $env = $config['env'] ?? 'production';
            $webhookToken = $config['webhook_token'] ?? '';
            
        } catch (\Exception $e) {
            // Se não conseguir carregar, mostra campos vazios
            $hasApiKey = false;
            $env = 'production';
            $webhookToken = '';
            $error = $e->getMessage();
        }

        $this->view('settings.asaas', [
            'hasApiKey' => $hasApiKey ?? false,
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
            // Criptografa a chave de API antes de salvar
            $apiKeyEncrypted = CryptoHelper::encrypt($apiKey);
            
            // Atualiza o arquivo .env com a chave criptografada
            $this->updateEnvFile([
                'ASAAS_API_KEY' => $apiKeyEncrypted,
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
     * Testa a conexão com o Asaas e retorna logs detalhados
     * 
     * POST /settings/asaas/test
     */
    public function testConnection(): void
    {
        Auth::requireInternal();

        header('Content-Type: application/json');

        try {
            // Tenta usar a mesma lógica do AsaasConfig para descriptografar
            $logs = [];
            $logs[] = "🔍 Iniciando teste de conexão com Asaas...";
            $logs[] = "";
            
            // Verifica se há chave configurada
            try {
                $config = AsaasConfig::getConfig();
                $apiKey = $config['api_key'] ?? null;
                $env = $config['env'] ?? 'production';
                
                $logs[] = "✅ Configuração carregada com sucesso";
                $logs[] = "📋 Ambiente: " . ($env === 'sandbox' ? 'Sandbox (Testes)' : 'Produção');
                
                // Verifica se a chave parece criptografada
                $apiKeyRaw = $config['api_key'] ?? '';
                $keyLength = strlen($apiKeyRaw);
                $isLikelyEncrypted = $keyLength > 50;
                $logs[] = "🔑 Status da chave:";
                $logs[] = "   - Tamanho: {$keyLength} caracteres";
                $logs[] = "   - Parece criptografada: " . ($isLikelyEncrypted ? 'Sim ⚠️' : 'Não');
                
                if ($isLikelyEncrypted) {
                    $logs[] = "⚠️ AVISO: A chave parece estar criptografada mas não foi descriptografada corretamente!";
                    $logs[] = "💡 Se você vê caracteres estranhos (começando com $aact_ ou base64),";
                    $logs[] = "   isso significa que a descriptografia falhou.";
                    $logs[] = "💡 SOLUÇÃO: Cole a chave de API do Asaas novamente e salve.";
                }
            } catch (\Exception $e) {
                $logs[] = "❌ Erro ao carregar configuração: " . $e->getMessage();
                $logs[] = "";
                $logs[] = "💡 Verificando .env diretamente...";
                
                // Tenta ler diretamente do .env
                $envPath = __DIR__ . '/../../.env';
                if (!file_exists($envPath)) {
                    $this->json([
                        'success' => false,
                        'message' => 'Arquivo .env não encontrado',
                        'logs' => $logs
                    ], 400);
                    return;
                }
                
                $apiKeyEncrypted = '';
                $env = 'production';
                $lines = file($envPath, FILE_IGNORE_NEW_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), 'ASAAS_API_KEY=') === 0) {
                        $apiKeyEncrypted = trim(substr($line, strlen('ASAAS_API_KEY=')));
                    }
                    if (strpos(trim($line), 'ASAAS_ENV=') === 0) {
                        $env = trim(substr($line, strlen('ASAAS_ENV=')));
                    }
                }
                
                if (empty($apiKeyEncrypted)) {
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
                
                // Verifica se parece ser uma chave criptografada (base64 normalmente tem mais de 50 caracteres)
                $isEncrypted = strlen($apiKeyEncrypted) > 50 && (strpos($apiKeyEncrypted, '$') === 0 || base64_decode($apiKeyEncrypted, true) !== false);
                
                $logs[] = "🔐 Status da chave:";
                $logs[] = "   - Tamanho: " . strlen($apiKeyEncrypted) . " caracteres";
                $logs[] = "   - Parece criptografada: " . ($isEncrypted ? 'Sim' : 'Não');
                
                // Tenta descriptografar
                if ($isEncrypted) {
                    $logs[] = "🔓 Tentando descriptografar chave...";
                    try {
                        $decrypted = \PixelHub\Core\CryptoHelper::decrypt($apiKeyEncrypted);
                        if (!empty($decrypted)) {
                            $logs[] = "✅ Chave descriptografada com sucesso!";
                            $logs[] = "   - Tamanho após descriptografia: " . strlen($decrypted) . " caracteres";
                            $apiKey = $decrypted;
                        } else {
                            $logs[] = "❌ ERRO CRÍTICO: Descriptografia retornou vazio!";
                            $logs[] = "💡 A chave está criptografada mas não pode ser descriptografada.";
                            $logs[] = "💡 Possíveis causas:";
                            $logs[] = "   1. A chave INFRA_SECRET_KEY foi alterada após criptografar";
                            $logs[] = "   2. A chave foi corrompida";
                            $logs[] = "💡 SOLUÇÃO: Cole a chave de API do Asaas novamente e salve as configurações.";
                            $apiKey = ''; // Força erro para não usar chave inválida
                        }
                    } catch (\Exception $decryptError) {
                        $logs[] = "❌ ERRO ao descriptografar: " . $decryptError->getMessage();
                        $logs[] = "💡 A chave está criptografada mas não pode ser descriptografada.";
                        $logs[] = "💡 SOLUÇÃO: Cole a chave de API do Asaas novamente e salve as configurações.";
                        $apiKey = ''; // Força erro para não usar chave inválida
                    }
                } else {
                    $logs[] = "✅ Chave em texto plano detectada, usando diretamente";
                    $apiKey = $apiKeyEncrypted;
                }
            }

            if (empty($apiKey)) {
                $this->json([
                    'success' => false,
                    'message' => 'Chave de API não configurada ou vazia',
                    'logs' => array_merge($logs, [
                        '❌ A chave de API está vazia após processamento'
                    ])
                ], 400);
                return;
            }

            $baseUrl = $env === 'sandbox' 
                ? 'https://sandbox.asaas.com/api/v3' 
                : 'https://www.asaas.com/api/v3';

            $logs[] = "";
            $logs[] = "🌐 URL Base: {$baseUrl}";
            
            // Log parcial da chave (apenas primeiros e últimos caracteres para debug)
            $keyLength = strlen($apiKey);
            $keyPreview = $keyLength > 12 
                ? substr($apiKey, 0, 6) . '...' . substr($apiKey, -6) 
                : substr($apiKey, 0, 6) . '...';
            $logs[] = "🔑 Chave de API (preview): {$keyPreview} (tamanho: {$keyLength} caracteres)";
            
            // Verifica se a chave parece válida (geralmente chaves Asaas têm ~60-70 caracteres)
            if ($keyLength < 20) {
                $logs[] = "⚠️ AVISO: A chave parece muito curta (menos de 20 caracteres). Verifique se foi descriptografada corretamente.";
            }

            // Teste 1: Listar customers (endpoint mais simples)
            $logs[] = "";
            $logs[] = "📡 Teste 1: Listando clientes (GET /customers?limit=1)...";
            
            // Prepara headers
            $apiKeyTrimmed = trim($apiKey); // Remove espaços em branco que podem estar causando problemas
            $headers = [
                'access_token: ' . $apiKeyTrimmed,
                'Content-Type: application/json',
            ];
            
            $logs[] = "📤 Headers HTTP:";
            $logs[] = "   - Content-Type: application/json";
            $logs[] = "   - access_token: " . substr($apiKeyTrimmed, 0, 8) . "..." . substr($apiKeyTrimmed, -4) . " (tamanho: " . strlen($apiKeyTrimmed) . ")";
            
            $url = $baseUrl . '/customers?limit=1';
            $logs[] = "🔗 URL completa: {$url}";
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_VERBOSE => false,
            ]);

            $startTime = microtime(true);
            $response = curl_exec($ch);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            curl_close($ch);

            $logs[] = "⏱️ Tempo de resposta: {$duration}ms";
            $logs[] = "📊 Código HTTP: {$httpCode}";

            if ($curlErrno) {
                $logs[] = "❌ Erro cURL: {$curlError} (Código: {$curlErrno})";
                $this->json([
                    'success' => false,
                    'message' => 'Erro de conexão: ' . $curlError,
                    'logs' => $logs,
                    'http_code' => null,
                    'response' => null
                ], 500);
                return;
            }

            $responseData = json_decode($response, true);
            
            // Log da resposta recebida
            $logs[] = "📥 Resposta recebida:";
            if (strlen($response) > 500) {
                $logs[] = "   (truncada - primeiros 500 caracteres)";
                $logs[] = "   " . substr($response, 0, 500) . "...";
            } else {
                $logs[] = "   " . $response;
            }
            
            if ($httpCode === 200) {
                $logs[] = "✅ Teste 1: SUCESSO - Conexão estabelecida com sucesso!";
                $logs[] = "📦 Resposta recebida: " . (is_array($responseData) ? json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : substr($response, 0, 200));
                
                // Teste 2: Obter informações da conta
                $logs[] = "";
                $logs[] = "📡 Teste 2: Obtendo informações da conta (GET /myAccount)...";
                
                $ch2 = curl_init($baseUrl . '/myAccount');
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'access_token: ' . $apiKey,
                        'Content-Type: application/json',
                    ],
                    CURLOPT_TIMEOUT => 15,
                ]);

                $startTime2 = microtime(true);
                $response2 = curl_exec($ch2);
                $endTime2 = microtime(true);
                $duration2 = round(($endTime2 - $startTime2) * 1000, 2);

                $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                curl_close($ch2);

                $logs[] = "⏱️ Tempo de resposta: {$duration2}ms";
                $logs[] = "📊 Código HTTP: {$httpCode2}";

                if ($httpCode2 === 200) {
                    $accountData = json_decode($response2, true);
                    $logs[] = "✅ Teste 2: SUCESSO - Informações da conta obtidas!";
                    if (isset($accountData['name'])) {
                        $logs[] = "👤 Nome da conta: " . $accountData['name'];
                    }
                    if (isset($accountData['email'])) {
                        $logs[] = "📧 E-mail: " . $accountData['email'];
                    }
                    if (isset($accountData['company'])) {
                        $logs[] = "🏢 Empresa: " . $accountData['company'];
                    }
                } else {
                    $logs[] = "⚠️ Teste 2: Falhou (HTTP {$httpCode2}) - Mas o teste principal foi bem-sucedido.";
                }

                $this->json([
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso! A chave de API está válida.',
                    'logs' => $logs,
                    'http_code' => $httpCode,
                    'response' => $responseData,
                    'duration_ms' => $duration
                ]);
                return;

            } elseif ($httpCode === 401) {
                $logs[] = "❌ Teste 1: FALHOU - Chave de API inválida ou expirada";
                $logs[] = "🔍 Detalhes: A API retornou 401 (Unauthorized)";
                $logs[] = "💡 Solução: Verifique se a chave está correta no painel do Asaas";
                
                $this->json([
                    'success' => false,
                    'message' => 'Chave de API inválida ou expirada',
                    'logs' => $logs,
                    'http_code' => $httpCode,
                    'response' => $responseData
                ], 401);
                return;

            } elseif ($httpCode === 403) {
                $logs[] = "❌ Teste 1: FALHOU - Acesso negado";
                $logs[] = "🔍 Detalhes: A API retornou 403 (Forbidden)";
                $logs[] = "💡 Solução: Verifique se sua chave tem as permissões necessárias";
                
                $this->json([
                    'success' => false,
                    'message' => 'Acesso negado. Verifique as permissões da chave de API',
                    'logs' => $logs,
                    'http_code' => $httpCode,
                    'response' => $responseData
                ], 403);
                return;

            } else {
                $logs[] = "❌ Teste 1: FALHOU - Código HTTP inesperado: {$httpCode}";
                $logs[] = "📦 Resposta: " . substr($response, 0, 500);
                
                $this->json([
                    'success' => false,
                    'message' => "Erro HTTP {$httpCode}",
                    'logs' => $logs,
                    'http_code' => $httpCode,
                    'response' => $responseData
                ], $httpCode);
                return;
            }

        } catch (\Exception $e) {
            $logs[] = "";
            $logs[] = "💥 Erro inesperado: " . $e->getMessage();
            $logs[] = "📍 Arquivo: " . $e->getFile() . " (Linha " . $e->getLine() . ")";
            
            error_log("Erro ao testar conexão com Asaas: " . $e->getMessage());
            
            $this->json([
                'success' => false,
                'message' => 'Erro ao testar conexão: ' . $e->getMessage(),
                'logs' => $logs
            ], 500);
        }
    }

}

