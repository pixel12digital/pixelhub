<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsApp\WhatsAppProviderInterface;
use PixelHub\Integrations\WhatsApp\WppConnectProvider;
use PixelHub\Integrations\WhatsApp\MetaOfficialProvider;

/**
 * Factory para criar instâncias de providers WhatsApp
 * 
 * GARANTIA DE COMPATIBILIDADE:
 * - Se tenant não tem config Meta → usa WPPConnect (comportamento atual)
 * - Se tenant tem config Meta mas está inativa → usa WPPConnect
 * - Fallback automático para WPPConnect em caso de erro
 */
class WhatsAppProviderFactory
{
    /**
     * Obtém provider configurado para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @param string|null $forceProviderType Força uso de provider específico (para testes)
     * @return WhatsAppProviderInterface Provider configurado
     */
    public static function getProviderForTenant(int $tenantId, ?string $forceProviderType = null): WhatsAppProviderInterface
    {
        $db = DB::getConnection();

        // Se forçou provider específico (para testes)
        if ($forceProviderType === 'wppconnect') {
            return self::createWppConnectProvider($tenantId);
        }

        if ($forceProviderType === 'meta_official') {
            $config = self::getMetaConfig($tenantId);
            if ($config) {
                return new MetaOfficialProvider($config);
            }
            // Se não tem config Meta, fallback para WPPConnect
            error_log("[WhatsAppProviderFactory] Tenant {$tenantId}: forçou Meta mas não tem config, usando WPPConnect");
            return self::createWppConnectProvider($tenantId);
        }

        // Busca configuração ativa do tenant
        try {
            $stmt = $db->prepare("
                SELECT provider_type, meta_phone_number_id, meta_access_token, 
                       meta_business_account_id, meta_webhook_verify_token,
                       wppconnect_session_id, config_metadata
                FROM whatsapp_provider_configs
                WHERE tenant_id = ? AND is_active = 1
                ORDER BY 
                    CASE provider_type 
                        WHEN 'meta_official' THEN 1 
                        WHEN 'wppconnect' THEN 2 
                    END
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$config) {
                // Sem config específica → usa WPPConnect (comportamento padrão atual)
                error_log("[WhatsAppProviderFactory] Tenant {$tenantId}: sem config específica, usando WPPConnect (padrão)");
                return self::createWppConnectProvider($tenantId);
            }

            $providerType = $config['provider_type'];

            if ($providerType === 'meta_official') {
                // Valida se tem credenciais Meta
                if (empty($config['meta_phone_number_id']) || empty($config['meta_access_token'])) {
                    error_log("[WhatsAppProviderFactory] Tenant {$tenantId}: config Meta incompleta, usando WPPConnect (fallback)");
                    return self::createWppConnectProvider($tenantId);
                }

                error_log("[WhatsAppProviderFactory] Tenant {$tenantId}: usando Meta Official API");
                return new MetaOfficialProvider($config);
            }

            // Default: WPPConnect
            error_log("[WhatsAppProviderFactory] Tenant {$tenantId}: usando WPPConnect");
            return self::createWppConnectProvider($tenantId);

        } catch (\Exception $e) {
            // Em caso de erro, fallback para WPPConnect (segurança)
            error_log("[WhatsAppProviderFactory] Erro ao buscar config tenant {$tenantId}: " . $e->getMessage() . " - usando WPPConnect (fallback)");
            return self::createWppConnectProvider($tenantId);
        }
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
     * Obtém configuração Meta para um tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array|null Configuração Meta ou null se não encontrada
     */
    private static function getMetaConfig(int $tenantId): ?array
    {
        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT provider_type, meta_phone_number_id, meta_access_token, 
                       meta_business_account_id, meta_webhook_verify_token,
                       config_metadata
                FROM whatsapp_provider_configs
                WHERE tenant_id = ? 
                AND provider_type = 'meta_official'
                AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $config = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $config ?: null;

        } catch (\Exception $e) {
            error_log("[WhatsAppProviderFactory] Erro ao buscar config Meta tenant {$tenantId}: " . $e->getMessage());
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
     * Lista providers disponíveis
     * 
     * @return array Lista de providers com informações
     */
    public static function getAvailableProviders(): array
    {
        return [
            [
                'type' => 'wppconnect',
                'name' => 'WPPConnect Gateway',
                'description' => 'Gateway WPPConnect próprio (VPS Hostinger)',
                'supports_base64' => true,
                'supports_url' => true,
                'requires_public_urls' => false,
                'is_default' => true
            ],
            [
                'type' => 'meta_official',
                'name' => 'Meta Official API',
                'description' => 'WhatsApp Business API oficial do Meta/Facebook',
                'supports_base64' => false,
                'supports_url' => true,
                'requires_public_urls' => true,
                'is_default' => false,
                'requires_credentials' => [
                    'Phone Number ID',
                    'Access Token',
                    'Business Account ID'
                ]
            ]
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
