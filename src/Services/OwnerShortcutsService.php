<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Core\CryptoHelper;

/**
 * Service para gerenciar acessos e links de infraestrutura
 */
class OwnerShortcutsService
{
    /**
     * Lista todos os acessos ordenados por categoria e label
     */
    public static function getAll(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT id, category, label, url, username, password_encrypted, notes, is_favorite, created_at, updated_at
            FROM owner_shortcuts
            ORDER BY category ASC, label ASC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Busca um acesso por ID
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT id, category, label, url, username, password_encrypted, notes, is_favorite, created_at, updated_at
            FROM owner_shortcuts
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Cria um novo acesso
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();

        // Valida categoria
        $allowedCategories = ['hospedagem', 'vps', 'afiliados', 'dominios', 'banco', 'ferramenta', 'outros'];
        $category = trim($data['category'] ?? '');
        if (!in_array($category, $allowedCategories)) {
            throw new \InvalidArgumentException('Categoria inválida');
        }

        // Processa dados
        $label = trim($data['label'] ?? '');
        $url = trim($data['url'] ?? '');
        $username = trim($data['username'] ?? '') ?: null;
        $password = trim($data['password'] ?? '');
        $notes = trim($data['notes'] ?? '') ?: null;
        $isFavorite = isset($data['is_favorite']) ? (int) $data['is_favorite'] : 0;

        // Validações
        if (empty($label)) {
            throw new \InvalidArgumentException('Nome do acesso é obrigatório');
        }
        
        // URL é opcional - pode ser null
        $url = !empty($url) ? $url : null;

        // Criptografa senha se fornecida
        $passwordEncrypted = !empty($password) ? CryptoHelper::encrypt($password) : null;

        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO owner_shortcuts 
            (category, label, url, username, password_encrypted, notes, is_favorite, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $category,
            $label,
            $url,
            $username,
            $passwordEncrypted,
            $notes,
            $isFavorite,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Atualiza um acesso existente
     */
    public static function update(int $id, array $data): bool
    {
        $db = DB::getConnection();

        // Busca acesso atual
        $current = self::findById($id);
        if (!$current) {
            throw new \RuntimeException('Acesso não encontrado');
        }

        // Valida categoria
        $allowedCategories = ['hospedagem', 'vps', 'afiliados', 'dominios', 'banco', 'ferramenta', 'outros'];
        $category = trim($data['category'] ?? $current['category']);
        if (!in_array($category, $allowedCategories)) {
            throw new \InvalidArgumentException('Categoria inválida');
        }

        // Processa dados
        $label = trim($data['label'] ?? $current['label']);
        $url = isset($data['url']) ? (trim($data['url']) ?: null) : ($current['url'] ?? null);
        $username = trim($data['username'] ?? $current['username'] ?? '') ?: null;
        $password = trim($data['password'] ?? '');
        $notes = trim($data['notes'] ?? $current['notes'] ?? '') ?: null;
        $isFavorite = isset($data['is_favorite']) ? (int) $data['is_favorite'] : $current['is_favorite'];

        // Validações
        if (empty($label)) {
            throw new \InvalidArgumentException('Nome do acesso é obrigatório');
        }

        // Se senha foi fornecida, criptografa; senão mantém a anterior
        $passwordEncrypted = $current['password_encrypted'];
        if (!empty($password)) {
            $passwordEncrypted = CryptoHelper::encrypt($password);
        }

        // Atualiza no banco
        $stmt = $db->prepare("
            UPDATE owner_shortcuts 
            SET category = ?, label = ?, url = ?, username = ?, password_encrypted = ?, notes = ?, is_favorite = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $category,
            $label,
            $url,
            $username,
            $passwordEncrypted,
            $notes,
            $isFavorite,
            $id,
        ]);

        return true;
    }

    /**
     * Exclui um acesso
     */
    public static function delete(int $id): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("DELETE FROM owner_shortcuts WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }

    /**
     * Obtém a senha descriptografada de um acesso
     */
    public static function getDecryptedPassword(int $id): string
    {
        $access = self::findById($id);
        if (!$access) {
            throw new \RuntimeException('Acesso não encontrado');
        }

        if (empty($access['password_encrypted'])) {
            throw new \RuntimeException('Senha não cadastrada para este acesso');
        }

        return CryptoHelper::decrypt($access['password_encrypted']);
    }

    /**
     * Retorna as categorias permitidas com seus nomes amigáveis
     */
    public static function getCategoryLabels(): array
    {
        return [
            'hospedagem' => 'Hospedagem',
            'vps' => 'VPS',
            'afiliados' => 'Afiliados',
            'dominios' => 'Domínios',
            'banco' => 'Banco de Dados',
            'ferramenta' => 'Ferramenta',
            'outros' => 'Outros',
        ];
    }
}

