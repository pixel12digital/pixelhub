<?php
// Cria o arquivo OpportunityProductService.php no servidor
$content = '<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar Produtos/Tags de Oportunidades
 * 
 * Funciona como sistema de tags com normalização anti-duplicidade
 */
class OpportunityProductService
{
    /**
     * Busca produtos para autocomplete (conforme digitação)
     */
    public static function search(string $query, int $limit = 20): array
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        if (strlen($query) < 2) {
            return [];
        }
        
        $searchTerm = \'%\' . trim($query) . \'%\';
        
        $stmt = $db->prepare("
            SELECT id, label, slug
            FROM opportunity_products
            WHERE is_active = 1
            AND (label LIKE ? OR slug LIKE ?)
            ORDER BY 
                CASE WHEN label LIKE ? THEN 1 ELSE 2 END,
                label ASC
            LIMIT ?
        ");
        
        $exactMatch = $query . \'%\';
        $stmt->execute([$searchTerm, $searchTerm, $exactMatch, $limit]);
        
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Lista todos os produtos ativos (para select/filter)
     */
    public static function listActive(): array
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT id, label, slug
            FROM opportunity_products
            WHERE is_active = 1
            ORDER BY label ASC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Busca produto por ID
     */
    public static function findById(int $id): ?array
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT id, label, slug, is_active
            FROM opportunity_products
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        
        $row = $stmt->fetch();
        return $row ?: null;
    }
    
    /**
     * Busca produto por slug
     */
    public static function findBySlug(string $slug): ?array
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT id, label, slug, is_active
            FROM opportunity_products
            WHERE slug = ?
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        
        $row = $stmt->fetch();
        return $row ?: null;
    }
    
    /**
     * Cria ou encontra produto existente (anti-duplicidade)
     * 
     * @param string $label Nome do produto (ex: "E-commerce")
     * @return array Produto encontrado/criado
     */
    public static function findOrCreate(string $label): array
    {
        $label = trim($label);
        if (empty($label)) {
            throw new \InvalidArgumentException(\'Label do produto é obrigatório\');
        }
        
        $slug = self::normalizeSlug($label);
        
        // Verifica se já existe pelo slug
        $existing = self::findBySlug($slug);
        if ($existing) {
            // Se existir mas estiver inativo, reativa
            if (!$existing[\'is_active\']) {
                self::activate($existing[\'id\']);
                $existing[\'is_active\'] = 1;
            }
            return $existing;
        }
        
        // Cria novo produto
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO opportunity_products (label, slug, is_active, created_at)
            VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([$label, $slug]);
        
        $id = (int) $db->lastInsertId();
        
        return [
            \'id\' => $id,
            \'label\' => $label,
            \'slug\' => $slug,
            \'is_active\' => 1
        ];
    }
    
    /**
     * Ativa produto inativo
     */
    public static function activate(int $id): bool
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE opportunity_products
            SET is_active = 1
            WHERE id = ?
        ");
        
        return $stmt->execute([$id]);
    }
    
    /**
     * Desativa produto (não exclui para manter integridade)
     */
    public static function deactivate(int $id): bool
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE opportunity_products
            SET is_active = 0
            WHERE id = ?
        ");
        
        return $stmt->execute([$id]);
    }
    
    /**
     * Atualiza label de produto (mantendo slug)
     */
    public static function update(int $id, string $newLabel): bool
    {
        $newLabel = trim($newLabel);
        if (empty($newLabel)) {
            throw new \InvalidArgumentException(\'Label do produto é obrigatório\');
        }
        
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE opportunity_products
            SET label = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$newLabel, $id]);
    }
    
    /**
     * Retorna estatísticas de uso dos produtos
     */
    public static function getUsageStats(): array
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                p.id,
                p.label,
                p.slug,
                COUNT(o.id) as opportunities_count,
                COALESCE(SUM(o.estimated_value), 0) as total_value,
                COUNT(CASE WHEN o.stage = \'won\' THEN 1 END) as won_count,
                COALESCE(SUM(CASE WHEN o.stage = \'won\' THEN o.estimated_value ELSE 0 END), 0) as won_value
            FROM opportunity_products p
            LEFT JOIN opportunities o ON o.product_id = p.id
            WHERE p.is_active = 1
            GROUP BY p.id, p.label, p.slug
            ORDER BY opportunities_count DESC, total_value DESC
        ");
        $stmt->execute();
        
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Normaliza texto para slug: trim + lowercase + remover acentos + espaços únicos
     */
    public static function normalizeSlug(string $text): string
    {
        $text = trim($text);
        $text = strtolower($text);
        
        // Remover acentos
        $text = iconv(\'UTF-8\', \'ASCII//TRANSLIT\', $text);
        
        // Substituir caracteres especiais
        $text = preg_replace(\'/[^a-z0-9\\s-]/\', \'\', $text);
        
        // Substituir múltiplos espaços/hífens por um único hífen
        $text = preg_replace(\'/[\\s-]+/\', \'-\', $text);
        
        // Remover hífens do início/fim
        $text = trim($text, \'-\');
        
        return $text;
    }
    
    /**
     * Valida se slug é único (para form validation)
     */
    public static function isSlugUnique(string $slug, ?int $excludeId = null): bool
    {
        $db = \PixelHub\Core\DB::getConnection();
        
        $sql = "SELECT COUNT(*) as count FROM opportunity_products WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $row = $stmt->fetch();
        return (int) $row[\'count\'] === 0;
    }
}
';

// Cria o arquivo
$filePath = '/home/pixel12digital/hub.pixel12digital.com.br/src/Services/OpportunityProductService.php';
file_put_contents($filePath, $content);

echo "<h1>Arquivo Criado!</h1>";
echo "<p>✓ OpportunityProductService.php criado em: $filePath</p>";

?>
