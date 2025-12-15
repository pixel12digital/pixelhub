<?php

namespace PixelHub\Services;

use PixelHub\Core\Env;

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
        $apiKey = trim($apiKeyRaw ?: ($baseConfig['api_key'] ?? ''));
        
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

