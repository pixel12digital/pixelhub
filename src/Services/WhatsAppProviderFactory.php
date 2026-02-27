<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsApp\WhatsAppProviderInterface;
use PixelHub\Integrations\WhatsApp\WppConnectProvider;
use PixelHub\Integrations\WhatsApp\MetaOfficialProvider;

/**
 * Factory para criar instâncias de providers WhatsApp
 * 
 * ARQUITETURA:
 * - Meta Official API: 1 config GLOBAL (is_global=TRUE) para TODOS os clientes
 * - WPPConnect: 1 config por tenant/sessão (is_global=FALSE)
 * 
 * GARANTIA DE COMPATIBILIDADE:
 * - Fallback automático para WPPConnect em caso de erro
 * - WPPConnect continua funcionando 100% igual
 */
class WhatsAppProviderFactory
{
    /**
     * Obtém provider baseado em escolha explícita ou fallback
     * 
     * @param string|null $providerChoice 'meta_official' ou 'wppconnect' ou null (auto)
     * @param int|null $tenantId ID do tenant (para WPPConnect)
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
            // Se não tem config Meta, fallback para WPPConnect
            error_log("[WhatsAppProviderFactory] Meta escolhido mas não configurado, usando WPPConnect (fallback)");
            return self::createWppConnectProvider($tenantId);
        }

        // Se escolheu explicitamente WPPConnect ou não escolheu nada
        error_log("[WhatsAppProviderFactory] Usando WPPConnect" . ($tenantId ? " (tenant {$tenantId})" : ""));
        return self::createWppConnectProvider($tenantId);
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
     * Cria provider WPPConnect para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @return WppConnectProvider Provider WPPConnect configurado
     */
    private static function createWppConnectProvider(int $tenantId): WppConnectProvider
    {
        $db = DB::getConnection();

        // Busca channel_id do tenant (tabela tenant_message_channels)
        try {
            $stmt = $db->prepare("
                SELECT channel_id, session_id
                FROM tenant_message_channels
                WHERE tenant_id = ? 
                AND provider = 'wpp_gateway'
                AND is_enabled = 1
                ORDER BY id ASC
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $channel = $stmt->fetch(\PDO::FETCH_ASSOC);

            $channelId = null;
            if ($channel) {
                // Prioriza session_id se existir, senão usa channel_id
                $channelId = !empty($channel['session_id']) ? $channel['session_id'] : $channel['channel_id'];
            }

            return new WppConnectProvider([
                'channel_id' => $channelId,
                'tenant_id' => $tenantId
            ]);

        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar channel WPPConnect tenant {$tenantId}: " . $e->getMessage());
            // Retorna provider sem channel_id (será necessário passar via metadata)
            return new WppConnectProvider(['tenant_id' => $tenantId]);
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
                // Lead sem tenant → usa WPPConnect padrão
                error_log("[WhatsAppProviderFactory] Lead {$leadId}: sem tenant, usando WPPConnect (padrão)");
                return new WppConnectProvider([]);
            }

            return self::getProviderForTenant((int)$lead['tenant_id']);

        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar provider para lead {$leadId}: " . $e->getMessage());
            return new WppConnectProvider([]);
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
                // Conversa sem tenant → usa WPPConnect padrão
                error_log("[WhatsAppProviderFactory] Conversa {$conversationId}: sem tenant, usando WPPConnect (padrão)");
                return new WppConnectProvider([]);
            }

            return self::getProviderForTenant((int)$conversation['tenant_id']);

        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar provider para conversa {$conversationId}: " . $e->getMessage());
            return new WppConnectProvider([]);
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
        
        return [
            [
                'type' => 'wppconnect',
                'name' => 'WPPConnect Gateway',
                'description' => 'Gateway próprio rodando na VPS (atual)',
                'is_default' => true,
                'is_configured' => true,
                'is_global' => false,
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
