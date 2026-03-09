<?php

namespace PixelHub\Services;

/**
 * Catálogo unificado de Origens/Canais
 * Fonte única de verdade para todas as telas do sistema
 */
class OriginCatalog
{
    /**
     * Retorna todas as origens/canais disponíveis
     * Sincronizado com TrackingCodesService::getChannels()
     * 
     * @return array Lista de origens com key, label e group
     */
    public static function getAll(): array
    {
        return [
            // Google
            [
                'key' => 'google',
                'label' => 'Google',
                'group' => 'Google'
            ],
            [
                'key' => 'google_ads',
                'label' => 'Google Ads (Pago)',
                'group' => 'Google'
            ],
            [
                'key' => 'google_organic',
                'label' => 'Google Orgânico (SEO)',
                'group' => 'Google'
            ],
            [
                'key' => 'google_maps',
                'label' => 'Google Maps',
                'group' => 'Google'
            ],
            
            // Meta
            [
                'key' => 'meta_ads',
                'label' => 'Meta Ads (Facebook/Instagram - Pago)',
                'group' => 'Meta'
            ],
            [
                'key' => 'facebook',
                'label' => 'Facebook',
                'group' => 'Meta'
            ],
            [
                'key' => 'instagram',
                'label' => 'Instagram',
                'group' => 'Meta'
            ],
            [
                'key' => 'facebook_ads',
                'label' => 'Facebook Ads',
                'group' => 'Meta'
            ],
            [
                'key' => 'instagram_ads',
                'label' => 'Instagram Ads',
                'group' => 'Meta'
            ],
            [
                'key' => 'instagram_organic',
                'label' => 'Instagram Orgânico',
                'group' => 'Meta'
            ],
            [
                'key' => 'facebook_organic',
                'label' => 'Facebook Orgânico',
                'group' => 'Meta'
            ],
            
            // Social
            [
                'key' => 'tiktok',
                'label' => 'TikTok',
                'group' => 'Social'
            ],
            [
                'key' => 'tiktok_organic',
                'label' => 'TikTok Orgânico',
                'group' => 'Social'
            ],
            [
                'key' => 'tiktok_ads',
                'label' => 'TikTok Ads (Pago)',
                'group' => 'Social'
            ],
            [
                'key' => 'youtube',
                'label' => 'YouTube',
                'group' => 'Social'
            ],
            [
                'key' => 'youtube_organic',
                'label' => 'YouTube Orgânico',
                'group' => 'Social'
            ],
            [
                'key' => 'youtube_ads',
                'label' => 'YouTube Ads (Pago)',
                'group' => 'Social'
            ],
            [
                'key' => 'linkedin',
                'label' => 'LinkedIn',
                'group' => 'Social'
            ],
            [
                'key' => 'linkedin_organic',
                'label' => 'LinkedIn Orgânico',
                'group' => 'Social'
            ],
            [
                'key' => 'linkedin_ads',
                'label' => 'LinkedIn Ads (Pago)',
                'group' => 'Social'
            ],
            [
                'key' => 'twitter',
                'label' => 'Twitter/X',
                'group' => 'Social'
            ],
            [
                'key' => 'twitter_organic',
                'label' => 'Twitter/X Orgânico',
                'group' => 'Social'
            ],
            [
                'key' => 'twitter_ads',
                'label' => 'Twitter/X Ads (Pago)',
                'group' => 'Social'
            ],
            
            // Direct
            [
                'key' => 'direct',
                'label' => 'Acesso Direto',
                'group' => 'Direct'
            ],
            [
                'key' => 'whatsapp',
                'label' => 'WhatsApp',
                'group' => 'Direct'
            ],
            [
                'key' => 'whatsapp_direct',
                'label' => 'WhatsApp Direto',
                'group' => 'Direct'
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'group' => 'Direct'
            ],
            [
                'key' => 'email_direct',
                'label' => 'E-mail Direto',
                'group' => 'Direct'
            ],
            [
                'key' => 'telefone',
                'label' => 'Telefone',
                'group' => 'Direct'
            ],
            [
                'key' => 'presencial',
                'label' => 'Presencial',
                'group' => 'Direct'
            ],
            
            // Referral
            [
                'key' => 'referral',
                'label' => 'Referência (Outros Sites)',
                'group' => 'Referral'
            ],
            [
                'key' => 'partnership',
                'label' => 'Parcerias',
                'group' => 'Referral'
            ],
            [
                'key' => 'influencer',
                'label' => 'Influenciadores',
                'group' => 'Referral'
            ],
            
            // Site/Web
            [
                'key' => 'site',
                'label' => 'Site',
                'group' => 'Web'
            ],
            [
                'key' => 'landing_page',
                'label' => 'Landing Page',
                'group' => 'Web'
            ],
            [
                'key' => 'blog',
                'label' => 'Blog',
                'group' => 'Web'
            ],
            [
                'key' => 'formulario',
                'label' => 'Formulário',
                'group' => 'Web'
            ],
            
            // Indicação
            [
                'key' => 'indicacao',
                'label' => 'Indicação',
                'group' => 'Indicação'
            ],
            [
                'key' => 'parceiro',
                'label' => 'Parceiro',
                'group' => 'Indicação'
            ],
            
            // CRM Interno
            [
                'key' => 'crm_manual',
                'label' => 'CRM Manual',
                'group' => 'CRM'
            ],
            [
                'key' => 'importacao',
                'label' => 'Importação',
                'group' => 'CRM'
            ],
            [
                'key' => 'prospecting_google_maps',
                'label' => 'Prospecção Ativa (Google Maps)',
                'group' => 'CRM'
            ],
            [
                'key' => 'prospecting_cnpjws',
                'label' => 'Prospecção Ativa (CNAE/CNPJ.ws)',
                'group' => 'CRM'
            ],
            [
                'key' => 'prospecting_instagram',
                'label' => 'Prospecção Ativa (Instagram)',
                'group' => 'CRM'
            ],
            [
                'key' => 'prospecting_cnae',
                'label' => 'Prospecção Ativa (CNAE)',
                'group' => 'CRM'
            ],
            
            // Outros
            [
                'key' => 'evento',
                'label' => 'Evento',
                'group' => 'Outro'
            ],
            [
                'key' => 'outro',
                'label' => 'Outro',
                'group' => 'Outro'
            ],
            [
                'key' => 'other',
                'label' => 'Outro',
                'group' => 'Outro'
            ],
            [
                'key' => 'offline',
                'label' => 'Offline',
                'group' => 'Outro'
            ],
            [
                'key' => 'unknown',
                'label' => 'Não identificado',
                'group' => 'Outro'
            ]
        ];
    }
    
    /**
     * Retorna lista simples de keys para dropdowns
     * 
     * @return array Array de keys
     */
    public static function getKeys(): array
    {
        return array_column(self::getAll(), 'key');
    }
    
    /**
     * Retorna lista formatada para select HTML
     * 
     * @param string|null $selectedKey Key selecionado
     * @return array Array com key => label
     */
    public static function getForSelect(?string $selectedKey = null): array
    {
        $options = ['' => 'Selecione...'];
        
        foreach (self::getAll() as $origin) {
            $options[$origin['key']] = $origin['label'];
        }
        
        return $options;
    }
    
    /**
     * Retorna lista agrupada por categoria
     * 
     * @return array Array agrupado por group
     */
    public static function getGrouped(): array
    {
        $grouped = [];
        
        foreach (self::getAll() as $origin) {
            $grouped[$origin['group']][] = $origin;
        }
        
        return $grouped;
    }
    
    /**
     * Valida se uma key de origem é válida
     * 
     * @param string $key Key para validar
     * @return bool Se é válida
     */
    public static function isValid(string $key): bool
    {
        return in_array($key, self::getKeys());
    }
    
    /**
     * Retorna label amigável para uma key
     * 
     * @param string $key Key da origem
     * @return string Label amigável
     */
    public static function getLabel(string $key): string
    {
        foreach (self::getAll() as $origin) {
            if ($origin['key'] === $key) {
                return $origin['label'];
            }
        }
        
        return 'Origem não informada';
    }
    
    /**
     * Retorna display amigável (com fallback para unknown)
     * 
     * @param string|null $origin Key da origem
     * @return string Label amigável
     */
    public static function getDisplay(?string $origin): string
    {
        if (!$origin || trim($origin) === '' || strtolower($origin) === 'unknown') {
            return 'Origem não informada';
        }
        
        return self::getLabel($origin);
    }
}
