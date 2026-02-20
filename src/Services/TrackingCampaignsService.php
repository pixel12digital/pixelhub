<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar campanhas de rastreamento
 */
class TrackingCampaignsService
{
    /**
     * Lista campanhas de um código
     */
    public static function listByTrackingCode(int $trackingCodeId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tc.*, u.name as created_by_name
            FROM tracking_campaigns tc
            LEFT JOIN users u ON tc.created_by = u.id
            WHERE tc.tracking_code_id = ?
            ORDER BY tc.created_at DESC
        ");
        $stmt->execute([$trackingCodeId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Lista todas as campanhas
     */
    public static function listAll(): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tc.*, tc.code as tracking_code, tc.source as tracking_source
            FROM tracking_campaigns tc
            LEFT JOIN tracking_codes t ON tc.tracking_code_id = t.id
            ORDER BY tc.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Cria nova campanha
     */
    public static function create(array $data, ?int $userId = null): int
    {
        $db = DB::getConnection();

        $name = trim($data['name'] ?? '');
        $trackingCodeId = (int) ($data['tracking_code_id'] ?? 0);
        $channel = $data['channel'] ?? 'organic';
        $platform = trim($data['platform'] ?? '');
        $destinationUrl = trim($data['destination_url'] ?? '');
        $description = trim($data['description'] ?? '');

        if (empty($name)) {
            throw new \InvalidArgumentException('Nome da campanha é obrigatório');
        }
        if (!$trackingCodeId) {
            throw new \InvalidArgumentException('Código de rastreamento é obrigatório');
        }

        $stmt = $db->prepare("
            INSERT INTO tracking_campaigns 
            (name, tracking_code_id, channel, platform, destination_url, description, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([$name, $trackingCodeId, $channel, $platform, $destinationUrl, $description, $userId]);

        return (int) $db->lastInsertId();
    }

    /**
     * Atualiza campanha
     */
    public static function update(int $id, array $data): bool
    {
        $db = DB::getConnection();

        $fields = [];
        $params = [];

        foreach (['name', 'channel', 'platform', 'destination_url', 'description'] as $field) {
            if (array_key_exists($data, $field)) {
                $fields[] = "{$field} = ?";
                $params[] = trim($data[$field]);
            }
        }

        if (isset($data['is_active'])) {
            $fields[] = "is_active = ?";
            $params[] = (bool) $data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = NOW()";
        $params[] = $id;

        $stmt = $db->prepare("UPDATE tracking_campaigns SET " . implode(', ', $fields) . " WHERE id = ?");
        return $stmt->execute($params);
    }

    /**
     * Remove campanha
     */
    public static function delete(int $id): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("DELETE FROM tracking_campaigns WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Ativa/Desativa campanha
     */
    public static function toggleActive(int $id, bool $active): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE tracking_campaigns SET is_active = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$active, $id]);
    }

    /**
     * Busca campanha por ID
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tc.*, u.name as created_by_name
            FROM tracking_campaigns tc
            LEFT JOIN users u ON tc.created_by = u.id
            WHERE tc.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Canais disponíveis
     */
    public static function getChannels(): array
    {
        return [
            'organic' => 'Orgânico',
            'ads' => 'Anúncios',
            'social' => 'Social Media',
            'email' => 'E-mail',
            'referral' => 'Indicação',
            'direct' => 'Acesso Direto',
            'other' => 'Outro'
        ];
    }

    /**
     * Plataformas comuns
     */
    public static function getPlatforms(): array
    {
        return [
            'google_ads' => 'Google Ads',
            'facebook_ads' => 'Facebook Ads',
            'instagram_ads' => 'Instagram Ads',
            'linkedin_ads' => 'LinkedIn Ads',
            'twitter_ads' => 'Twitter Ads',
            'tiktok_ads' => 'TikTok Ads',
            'google_organic' => 'Google Orgânico',
            'facebook_organic' => 'Facebook Orgânico',
            'instagram_organic' => 'Instagram Orgânico',
            'linkedin_organic' => 'LinkedIn Orgânico',
            'youtube_organic' => 'YouTube Orgânico',
            'direct' => 'Acesso Direto'
        ];
    }
}
