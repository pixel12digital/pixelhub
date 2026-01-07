<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;
use PixelHub\Core\CryptoHelper;

/**
 * Classe de configuração do Asaas
 * 
 * Centraliza a leitura de configurações do Asaas, priorizando variáveis de ambiente.
 */
class AsaasConfig
{
    private static ?array $config = null;

    /**
     * Retorna a configuração completa do Asaas
     * 
     * @return array
     * @throws \RuntimeException Se a API key não estiver configurada quando necessário
     */
    public static function getConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        // Carrega configuração base do arquivo
        $configFile = __DIR__ . '/../../config/asaas.php';
        $baseConfig = file_exists($configFile) ? require $configFile : [];

        // Lê variáveis de ambiente usando Env::get() (que lê do .env carregado)
        // Usa trim() para remover espaços e considera vazio apenas se realmente vazio ou placeholder
        $apiKeyRaw = Env::get('ASAAS_API_KEY');
        $apiKeyEncrypted = trim($apiKeyRaw ?: ($baseConfig['api_key'] ?? ''));
        
        // Tenta descriptografar a chave (pode estar criptografada ou em texto plano para compatibilidade)
        $apiKey = '';
        if (!empty($apiKeyEncrypted)) {
            // Detecta se parece ser uma chave criptografada
            // Chaves do Asaas em texto plano começam com "$aact_" (ex: $aact_prod_...)
            // Chaves criptografadas são base64 (sem $ no início) e muito mais longas
            $isLikelyEncrypted = false;
            
            // Se começa com $aact_, é uma chave do Asaas em texto plano
            if (strpos($apiKeyEncrypted, '$aact_') === 0) {
                $isLikelyEncrypted = false; // É texto plano, não precisa descriptografar
            } 
            // Se é muito longa (>100 chars) e não começa com $, provavelmente é base64 criptografado
            elseif (strlen($apiKeyEncrypted) > 100 && strpos($apiKeyEncrypted, '$') !== 0) {
                // Testa se é base64 válido
                $decoded = @base64_decode($apiKeyEncrypted, true);
                if ($decoded !== false && strlen($decoded) > 16) {
                    $isLikelyEncrypted = true;
                }
            }
            
            if ($isLikelyEncrypted) {
                try {
                    // Tenta descriptografar
                    $decrypted = CryptoHelper::decrypt($apiKeyEncrypted);
                    if (!empty($decrypted)) {
                        // Verifica se a chave descriptografada parece válida (chaves Asaas começam com $aact_)
                        if (strpos($decrypted, '$aact_') === 0) {
                            $apiKey = $decrypted;
                        } else {
                            // Descriptografou mas não parece uma chave válida do Asaas
                            error_log('[ASAAS WARNING] Chave descriptografada mas não parece válida (não começa com $aact_)');
                            error_log('[ASAAS WARNING] Possível causa: INFRA_SECRET_KEY diferente entre ambientes');
                            // Tenta usar mesmo assim, mas loga o problema
                            $apiKey = $decrypted;
                        }
                    } else {
                        // Se retornou vazio após descriptografar, a INFRA_SECRET_KEY está errada
                        error_log('[ASAAS ERROR] Chave parece criptografada mas descriptografia retornou vazio');
                        error_log('[ASAAS ERROR] A INFRA_SECRET_KEY local é diferente da usada para criptografar a chave.');
                        error_log('[ASAAS ERROR] SOLUÇÃO: Cole a chave de API do Asaas novamente no ambiente local.');
                        throw new \RuntimeException(
                            'A chave de API está criptografada mas não pode ser descriptografada. ' .
                            'Isso geralmente acontece quando a INFRA_SECRET_KEY é diferente entre ambientes. ' .
                            'SOLUÇÃO: Acesse as configurações do Asaas e cole a chave de API novamente para que ela seja criptografada com a chave local.'
                        );
                    }
                } catch (\RuntimeException $e) {
                    // Re-lança RuntimeExceptions (erros de descriptografia)
                    throw $e;
                } catch (\Exception $e) {
                    // Se falhar ao descriptografar, pode ser problema com INFRA_SECRET_KEY
                    error_log('[ASAAS ERROR] Falha ao descriptografar chave: ' . $e->getMessage());
                    error_log('[ASAAS ERROR] A chave no .env está criptografada mas não pode ser descriptografada.');
                    error_log('[ASAAS ERROR] Possível causa: INFRA_SECRET_KEY foi alterada após criptografar a chave.');
                    error_log('[ASAAS ERROR] SOLUÇÃO: Cole a chave de API do Asaas novamente no ambiente local.');
                    throw new \RuntimeException(
                        'A chave de API está criptografada mas não pode ser descriptografada. ' .
                        'Isso geralmente acontece quando a INFRA_SECRET_KEY é diferente entre ambientes. ' .
                        'SOLUÇÃO: Acesse as configurações do Asaas e cole a chave de API novamente para que ela seja criptografada com a chave local.'
                    );
                }
            } else {
                // Não parece criptografada, usa como texto plano
                $apiKey = $apiKeyEncrypted;
            }
        }
        
        $envRaw = Env::get('ASAAS_ENV');
        $env = trim($envRaw ?: ($baseConfig['env'] ?? 'production'));
        
        $baseUrlRaw = Env::get('ASAAS_API_BASE_URL');
        $baseUrl = trim($baseUrlRaw ?: ($baseConfig['base_url'] ?? ''));
        
        $webhookTokenRaw = Env::get('ASAAS_WEBHOOK_TOKEN');
        $webhookToken = trim($webhookTokenRaw ?: ($baseConfig['webhook_token'] ?? ''));

        // Log de debug temporário (apenas primeiros 8 caracteres e tamanho)
        if ($apiKey) {
            error_log('[ASAAS DEBUG] API KEY START: ' . substr($apiKey, 0, 8) . ' LEN=' . strlen($apiKey));
        } else {
            error_log('[ASAAS DEBUG] API KEY: VAZIA ou não encontrada');
        }

        // Valida se API key está configurada (considera inválido apenas se vazio ou placeholder)
        if ($apiKey === '' || $apiKey === 'coloque_sua_chave_aqui') {
            throw new \RuntimeException(
                'Asaas não está configurado. Verifique ASAAS_API_KEY, ASAAS_ENV e ASAAS_WEBHOOK_TOKEN no arquivo .env.'
            );
        }

        // Define base_url baseado no env se não foi fornecido
        if ($baseUrl === '' || $baseUrl === null) {
            if ($env === 'sandbox') {
                $baseUrl = 'https://sandbox.asaas.com/api/v3';
            } else {
                $baseUrl = 'https://www.asaas.com/api/v3';
            }
        }

        self::$config = [
            'api_key'       => $apiKey,
            'env'           => $env,
            'base_url'      => $baseUrl,
            'webhook_token' => $webhookToken,
        ];

        return self::$config;
    }

    /**
     * Verifica se a API key está configurada
     * 
     * @return bool
     */
    public static function hasApiKey(): bool
    {
        $config = self::getConfig();
        return !empty($config['api_key']);
    }

    /**
     * Retorna a API key ou lança exceção
     * 
     * @return string
     * @throws \RuntimeException
     */
    public static function getApiKey(): string
    {
        $config = self::getConfig();
        
        if (empty($config['api_key'])) {
            throw new \RuntimeException(
                'ASAAS_API_KEY não configurada. Configure a variável de ambiente ASAAS_API_KEY no arquivo .env'
            );
        }

        return $config['api_key'];
    }

    /**
     * Retorna o token do webhook
     * 
     * @return string|null
     */
    public static function getWebhookToken(): ?string
    {
        $config = self::getConfig();
        return $config['webhook_token'] ?? null;
    }

    /**
     * Limpa o cache da configuração (útil após atualizar o .env)
     */
    public static function clearCache(): void
    {
        self::$config = null;
    }
}

