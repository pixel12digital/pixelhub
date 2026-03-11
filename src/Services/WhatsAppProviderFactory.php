<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsApp\WhatsAppProviderInterface;
use PixelHub\Integrations\WhatsApp\MetaOfficialProvider;
use PixelHub\Integrations\WhatsApp\WhapiCloudProvider;

/**
 * Factory para criar instâncias de providers WhatsApp
 * 
 * ARQUITETURA:
 * - Whapi.Cloud: Provider padrão não-oficial - config GLOBAL
 * - Meta Official API: 1 config GLOBAL (is_global=TRUE) para TODOS os clientes
 * 
 * PRIORIDADE:
 * 1. Meta Official API (se selecionado explicitamente)
 * 2. Whapi.Cloud (padrão)
 */
class WhatsAppProviderFactory
{
    /**
     * Obtém provider baseado em escolha explícita ou fallback
     * 
     * @param string|null $providerChoice 'meta_official', 'whapi' ou null (auto)
     * @param int|null $tenantId ID do tenant (para contexto)
     * @return WhatsAppProviderInterface Provider configurado
     */
    public static function getProvider(?string $providerChoice = null, ?int $tenantId = null): WhatsAppProviderInterface
    {
        // Se escolheu explicitamente Meta Official API
        if ($providerChoice === 'meta_official') {
            $metaConfig = self::getGlobalMetaConfig();
            if ($metaConfig) {
                error_log("[WhatsAppProviderFactory] Usando Meta Official API (escolha explícita)");
                return new MetaOfficialProvider($metaConfig);
            }
            error_log("[WhatsAppProviderFactory] Meta escolhido mas não configurado, usando Whapi (fallback)");
        }

        // Padrão e fallback: Whapi.Cloud
        return self::createWhapiProvider();
    }

    /**
     * COMPATIBILIDADE: Mantém método antigo para não quebrar código existente
     * 
     * @deprecated Use getProvider() com providerChoice explícito
     */
    public static function getProviderForTenant(int $tenantId, ?string $forceProviderType = null): WhatsAppProviderInterface
    {
        return self::getProvider($forceProviderType, $tenantId);
    }

    /**
     * Cria provider Whapi.Cloud (provider padrão)
     */
    private static function createWhapiProvider(): WhapiCloudProvider
    {
        $whapiConfig = self::getGlobalWhapiConfig();
        if ($whapiConfig) {
            error_log("[WhatsAppProviderFactory] Usando Whapi.Cloud");
            return new WhapiCloudProvider($whapiConfig);
        }
        // Retorna instância sem config (validação falhará ao tentar enviar)
        error_log("[WhatsAppProviderFactory] AVISO: Whapi.Cloud não configurado. Configure o token em Configurações → WhatsApp");
        return new WhapiCloudProvider([]);
    }

    /**
     * Obtém configuração GLOBAL do Whapi.Cloud
     * 
     * @return array|null Configuração Whapi global ou null se não encontrada
     */
    private static function getGlobalWhapiConfig(): ?array
    {
        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT whapi_api_token, whapi_channel_id
                FROM whatsapp_provider_configs
                WHERE provider_type = 'whapi' 
                  AND is_global = TRUE 
                  AND is_active = 1
                LIMIT 1
            ");
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($config && !empty($config['whapi_api_token'])) {
                error_log("[WhatsAppProviderFactory] Config Whapi global encontrada");
                return $config;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar config Whapi global: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtém configuração GLOBAL do Meta Official API
     * 
     * Meta usa 1 número único para TODOS os clientes (config global)
     * 
     * @return array|null Configuração Meta global ou null se não encontrada
     */
    private static function getGlobalMetaConfig(): ?array
    {
        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT meta_phone_number_id, meta_access_token, 
                       meta_business_account_id, meta_webhook_verify_token
                FROM whatsapp_provider_configs
                WHERE provider_type = 'meta_official' 
                  AND is_global = TRUE 
                  AND is_active = 1
                LIMIT 1
            ");
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($config) {
                error_log("[WhatsAppProviderFactory] Config Meta global encontrada");
            } else {
                error_log("[WhatsAppProviderFactory] Config Meta global não encontrada");
            }
            
            return $config ?: null;
        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar config Meta global: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtém provider para um lead (resolve tenant do lead)
     * 
     * @param int $leadId ID do lead
     * @return WhatsAppProviderInterface Provider configurado
     */
    public static function getProviderForLead(int $leadId): WhatsAppProviderInterface
    {
        $db = DB::getConnection();

        try {
            // Busca tenant_id do lead
            $stmt = $db->prepare("SELECT tenant_id FROM leads WHERE id = ? LIMIT 1");
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$lead || empty($lead['tenant_id'])) {
                error_log("[WhatsAppProviderFactory] Lead {$leadId}: sem tenant, usando Whapi (padrão)");
                return self::createWhapiProvider();
            }

            return self::getProviderForTenant((int)$lead['tenant_id']);

        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar provider para lead {$leadId}: " . $e->getMessage());
            return self::createWhapiProvider();
        }
    }

    /**
     * Obtém provider para uma conversa (resolve tenant da conversa)
     * 
     * @param int $conversationId ID da conversa
     * @return WhatsAppProviderInterface Provider configurado
     */
    public static function getProviderForConversation(int $conversationId): WhatsAppProviderInterface
    {
        $db = DB::getConnection();

        try {
            // Busca tenant_id da conversa
            $stmt = $db->prepare("SELECT tenant_id FROM conversations WHERE id = ? LIMIT 1");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$conversation || empty($conversation['tenant_id'])) {
                error_log("[WhatsAppProviderFactory] Conversa {$conversationId}: sem tenant, usando Whapi (padrão)");
                return self::createWhapiProvider();
            }

            return self::getProviderForTenant((int)$conversation['tenant_id']);

        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar provider para conversa {$conversationId}: " . $e->getMessage());
            return self::createWhapiProvider();
        }
    }

    /**
     * Lista providers disponíveis no sistema
     * 
     * @return array Lista de providers com informações
     */
    public static function getAvailableProviders(): array
    {
        $db = DB::getConnection();
        
        // Verifica se Meta está configurado
        $metaConfigured = false;
        try {
            $stmt = $db->query("
                SELECT COUNT(*) as total 
                FROM whatsapp_provider_configs 
                WHERE provider_type = 'meta_official' AND is_global = TRUE AND is_active = 1
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $metaConfigured = ($result['total'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Ignora erro
        }
        
        // Verifica se Whapi está configurado
        $whapiConfigured = false;
        try {
            $stmt2 = $db->query("
                SELECT COUNT(*) as total 
                FROM whatsapp_provider_configs 
                WHERE provider_type = 'whapi' AND is_global = TRUE AND is_active = 1
            ");
            $result2 = $stmt2->fetch(\PDO::FETCH_ASSOC);
            $whapiConfigured = ($result2['total'] ?? 0) > 0;
        } catch (\Exception $e) {
            // Ignora erro
        }

        return [
            [
                'type' => 'whapi',
                'name' => 'Whapi.Cloud',
                'description' => 'WhatsApp API gerenciada - substitui WPPConnect (recomendado)',
                'is_default' => $whapiConfigured,
                'is_configured' => $whapiConfigured,
                'is_global' => true,
                'supports_base64' => true,
                'supports_templates' => false,
            ],
            [
                'type' => 'meta_official',
                'name' => 'Meta Official API',
                'description' => 'API oficial do WhatsApp Business - 1 número para TODOS os clientes',
                'is_default' => false,
                'is_configured' => $metaConfigured,
                'is_global' => true,
                'supports_base64' => false,
                'supports_templates' => true,
            ],
        ];
    }

    /**
     * Verifica se um tenant tem provider Meta configurado
     * 
     * @param int $tenantId ID do tenant
     * @return bool True se tem Meta configurado e ativo
     */
    public static function tenantHasMetaProvider(int $tenantId): bool
    {
        $config = self::getMetaConfig($tenantId);
        return $config !== null && !empty($config['meta_phone_number_id']) && !empty($config['meta_access_token']);
    }
}
