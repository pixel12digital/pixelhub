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
        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT id, name, slug, is_active, sort_order
                FROM hosting_providers
                WHERE is_active = 1
                ORDER BY sort_order ASC, name ASC
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Garante que sempre retorna um array
            if (!is_array($result)) {
                return [];
            }
            
            // Filtra resultados caso algum venha com is_active != 1 (proteção adicional)
            $filtered = array_filter($result, function($provider) {
                return isset($provider['is_active']) && 
                       ($provider['is_active'] === 1 || $provider['is_active'] === '1' || $provider['is_active'] === true);
            });
            
            return array_values($filtered);
        } catch (\Throwable $e) {
            // Log erro mas retorna array vazio para não quebrar a aplicação
            if (function_exists('pixelhub_log')) {
                pixelhub_log("HostingProviderService::getAllActive: Erro ao buscar provedores: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
            } else {
                @error_log("HostingProviderService::getAllActive: Erro ao buscar provedores: " . $e->getMessage());
            }
            return [];
        }
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
        // Adiciona opção especial para sites sem hospedagem ativa (apenas backup)
        $map['nenhum_backup'] = 'Nenhum (Somente backup externo)';
        return $map;
    }
}

