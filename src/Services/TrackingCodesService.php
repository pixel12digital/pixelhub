<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar códigos de rastreamento com contexto completo
 */
class TrackingCodesService
{
    /**
     * Canais disponíveis (separados por tipo)
     */
    public static function getChannels(): array
    {
        return [
            'google' => [
                'google_ads' => 'Google Ads (Pago)',
                'google_organic' => 'Google Orgânico (SEO)',
                'google_maps' => 'Google Maps'
            ],
            'meta' => [
                'meta_ads' => 'Meta Ads (Facebook/Instagram - Pago)',
                'instagram_organic' => 'Instagram Orgânico',
                'facebook_organic' => 'Facebook Orgânico'
            ],
            'social' => [
                'tiktok_organic' => 'TikTok Orgânico',
                'tiktok_ads' => 'TikTok Ads (Pago)',
                'youtube_organic' => 'YouTube Orgânico',
                'youtube_ads' => 'YouTube Ads (Pago)',
                'linkedin_organic' => 'LinkedIn Orgânico',
                'linkedin_ads' => 'LinkedIn Ads (Pago)',
                'twitter_organic' => 'Twitter/X Orgânico',
                'twitter_ads' => 'Twitter/X Ads (Pago)'
            ],
            'direct' => [
                'direct' => 'Acesso Direto',
                'whatsapp_direct' => 'WhatsApp Direto',
                'email_direct' => 'E-mail Direto'
            ],
            'referral' => [
                'referral' => 'Referência (Outros Sites)',
                'partnership' => 'Parcerias',
                'influencer' => 'Influenciadores'
            ],
            'other' => [
                'other' => 'Outro',
                'offline' => 'Offline',
                'unknown' => 'Não identificado'
            ]
        ];
    }

    /**
     * Posições de CTA disponíveis
     */
    public static function getCtaPositions(): array
    {
        return [
            'header' => 'Header (Topo)',
            'hero' => 'Hero (Principal)',
            'content' => 'Conteúdo',
            'sidebar' => 'Barra Lateral',
            'footer' => 'Rodapé',
            'popup' => 'Popup',
            'floating_button' => 'Botão Flutuante',
            'form' => 'Formulário',
            'button' => 'Botão Específico',
            'link' => 'Link no Texto',
            'banner' => 'Banner',
            'carousel' => 'Carrossel',
            'other' => 'Outro'
        ];
    }

    /**
     * Verifica se código já existe
     */
    public static function codeExists(string $code): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT id FROM tracking_codes WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch() !== false;
    }

    /**
     * Lista todos os códigos com contexto
     */
    public static function listAll(): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tc.*, u.name as created_by_name
            FROM tracking_codes tc
            LEFT JOIN users u ON tc.created_by = u.id
            ORDER BY tc.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Cria novo código com contexto
     */
    public static function create(array $data, ?int $userId = null): int
    {
        $db = DB::getConnection();

        $code = trim($data['code'] ?? '');
        $channel = $data['channel'] ?? 'other';
        $originPage = trim($data['origin_page'] ?? '');
        $ctaPosition = $data['cta_position'] ?? null;
        $campaignName = trim($data['campaign_name'] ?? '');
        $campaignId = trim($data['campaign_id'] ?? '');
        $adGroup = trim($data['ad_group'] ?? '');
        $adName = trim($data['ad_name'] ?? '');
        $description = trim($data['description'] ?? '');

        // Validações
        if (empty($code)) {
            throw new \InvalidArgumentException('Código é obrigatório');
        }

        if (self::codeExists($code)) {
            throw new \InvalidArgumentException("Código '{$code}' já existe. Use um código único.");
        }

        // Validações específicas por canal
        if ($channel === 'google_ads' && empty($campaignName)) {
            throw new \InvalidArgumentException('Para Google Ads, o nome da campanha é obrigatório');
        }

        if ($channel === 'meta_ads' && empty($campaignName)) {
            throw new \InvalidArgumentException('Para Meta Ads, o nome da campanha é obrigatório');
        }

        // Remove campos de campanha para canais orgânicos
        if (strpos($channel, '_organic') !== false || $channel === 'direct' || $channel === 'referral') {
            $campaignName = null;
            $campaignId = null;
            $adGroup = null;
            $adName = null;
        }

        // Monta metadados
        $metadata = [
            'created_at' => date('Y-m-d H:i:s'),
            'validation_rules' => [
                'channel' => $channel,
                'requires_campaign' => in_array($channel, ['google_ads', 'meta_ads', 'tiktok_ads', 'youtube_ads', 'linkedin_ads', 'twitter_ads'])
            ]
        ];

        $stmt = $db->prepare("
            INSERT INTO tracking_codes 
            (code, source, description, is_active, created_by, created_at, updated_at,
             channel, origin_page, cta_position, campaign_name, campaign_id, ad_group, ad_name, context_metadata)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $code, 
            self::getSourceFromChannel($channel), 
            $description, 
            1, // is_active como integer
            $userId,
            $channel,
            $originPage,
            $ctaPosition,
            $campaignName,
            $campaignId,
            $adGroup,
            $adName,
            json_encode($metadata)
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Atualiza código com contexto
     */
    public static function update(int $id, array $data): bool
    {
        $db = DB::getConnection();

        $fields = [];
        $params = [];

        // Campos básicos
        foreach (['code', 'description', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = trim($data[$field]);
            }
        }

        // Campos de contexto
        foreach (['channel', 'origin_page', 'cta_position', 'campaign_name', 'campaign_id', 'ad_group', 'ad_name'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = trim($data[$field] ?? '');
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $params[] = $id;

        $stmt = $db->prepare("UPDATE tracking_codes SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    /**
     * Busca código por ID
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tc.*, u.name as created_by_name
            FROM tracking_codes tc
            LEFT JOIN users u ON tc.created_by = u.id
            WHERE tc.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Remove código
     */
    public static function delete(int $id): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("DELETE FROM tracking_codes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Ativa/Desativa código
     */
    public static function toggleActive(int $id, bool $active): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE tracking_codes SET is_active = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$active, $id]);
    }

    /**
     * Detecta código em mensagem com contexto completo
     */
    public static function detectFromMessage(string $message): ?array
    {
        if (empty($message)) {
            return null;
        }

        $db = DB::getConnection();
        
        // Busca todos os códigos ativos com contexto
        $stmt = $db->prepare("
            SELECT * FROM tracking_codes 
            WHERE is_active = 1
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        $codes = $stmt->fetchAll() ?: [];

        if (empty($codes)) {
            return null;
        }

        $upperMessage = strtoupper($message);
        
        // Procura cada código na mensagem
        foreach ($codes as $codeRow) {
            $code = strtoupper($codeRow['code']);
            if (str_contains($upperMessage, $code)) {
                return [
                    'tracking_code' => $codeRow['code'],
                    'tracking_source' => $codeRow['source'],
                    'tracking_auto_detected' => true,
                    'tracking_metadata' => json_encode([
                        'detected_at' => date('Y-m-d H:i:s'),
                        'description' => $codeRow['description'],
                        'channel' => $codeRow['channel'],
                        'origin_page' => $codeRow['origin_page'],
                        'cta_position' => $codeRow['cta_position'],
                        'campaign_name' => $codeRow['campaign_name'],
                        'campaign_id' => $codeRow['campaign_id'],
                        'ad_group' => $codeRow['ad_group'],
                        'ad_name' => $codeRow['ad_name'],
                        'context_metadata' => $codeRow['context_metadata'] ? json_decode($codeRow['context_metadata'], true) : null
                    ])
                ];
            }
        }

        return null;
    }

    /**
     * Converte canal para source (legado)
     */
    private static function getSourceFromChannel(string $channel): string
    {
        if (strpos($channel, 'google') !== false) return 'google';
        if (strpos($channel, 'meta') !== false || strpos($channel, 'instagram') !== false || strpos($channel, 'facebook') !== false) return 'instagram';
        if (strpos($channel, 'tiktok') !== false || strpos($channel, 'youtube') !== false || strpos($channel, 'linkedin') !== false || strpos($channel, 'twitter') !== false) return 'social';
        if (strpos($channel, 'whatsapp') !== false || strpos($channel, 'email') !== false) return 'whatsapp';
        if (strpos($channel, 'referral') !== false) return 'indicacao';
        return 'outro';
    }

    /**
     * Estatísticas por canal
     */
    public static function getChannelStats(): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT 
                channel,
                COUNT(*) as total_codes,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_codes,
                COUNT(CASE WHEN campaign_name IS NOT NULL THEN 1 END) as with_campaign
            FROM tracking_codes 
            GROUP BY channel
            ORDER BY total_codes DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }
}
