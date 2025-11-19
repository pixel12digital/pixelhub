<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Service para gerenciar provedores de hospedagem
 * 
 * Fornece métodos para buscar e manipular provedores de hospedagem.
 */
class HostingProviderService
{
    /**
     * Retorna todos os provedores ativos ordenados por sort_order e name
     * 
     * @return array
     */
    public static function getAllActive(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT id, name, slug, is_active, sort_order
            FROM hosting_providers
            WHERE is_active = 1
            ORDER BY sort_order ASC, name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os provedores (ativos e inativos) ordenados por sort_order e name
     * 
     * @return array
     */
    public static function getAll(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT id, name, slug, is_active, sort_order
            FROM hosting_providers
            ORDER BY sort_order ASC, name ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca um provedor por ID
     * 
     * @param int $id
     * @return array|null
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosting_providers WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Busca um provedor por slug
     * 
     * @param string $slug
     * @return array|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM hosting_providers WHERE slug = ?");
        $stmt->execute([$slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Retorna um mapa de slug => name para uso em views
     * 
     * @return array
     */
    public static function getSlugToNameMap(): array
    {
        $providers = self::getAll();
        $map = [];
        foreach ($providers as $provider) {
            $map[$provider['slug']] = $provider['name'];
        }
        return $map;
    }
}

