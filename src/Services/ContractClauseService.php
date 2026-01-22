<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar cláusulas de contrato configuráveis
 */
class ContractClauseService
{
    /**
     * Busca todas as cláusulas ativas ordenadas
     * 
     * @return array Lista de cláusulas
     */
    public static function getActiveClauses(): array
    {
        $db = DB::getConnection();
        
        // Verifica se a tabela existe
        try {
            $stmt = $db->query("
                SELECT id, title, content, order_index
                FROM contract_clauses
                WHERE is_active = 1
                ORDER BY order_index ASC, id ASC
            ");
            return $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            // Se a tabela não existe, retorna array vazio
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Table") !== false) {
                error_log("Tabela contract_clauses não existe. Execute a migration primeiro.");
                return [];
            }
            throw $e;
        }
    }
    
    /**
     * Busca todas as cláusulas (ativas e inativas)
     * 
     * @return array Lista de cláusulas
     */
    public static function getAllClauses(): array
    {
        $db = DB::getConnection();
        
        // Verifica se a tabela existe
        try {
            $stmt = $db->query("
                SELECT id, title, content, order_index, is_active, created_at, updated_at
                FROM contract_clauses
                ORDER BY order_index ASC, id ASC
            ");
            return $stmt->fetchAll() ?: [];
        } catch (\PDOException $e) {
            // Se a tabela não existe, retorna array vazio
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Table") !== false) {
                error_log("Tabela contract_clauses não existe. Execute a migration primeiro.");
                return [];
            }
            throw $e;
        }
    }
    
    /**
     * Busca uma cláusula por ID
     * 
     * @param int $id ID da cláusula
     * @return array|null Cláusula ou null se não encontrada
     */
    public static function findClause(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT id, title, content, order_index, is_active, created_at, updated_at
            FROM contract_clauses
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Cria uma nova cláusula
     * 
     * @param array $data Dados da cláusula
     * @return int ID da cláusula criada
     */
    public static function createClause(array $data): int
    {
        $db = DB::getConnection();
        
        $title = trim($data['title'] ?? '');
        $content = trim($data['content'] ?? '');
        $orderIndex = !empty($data['order_index']) ? (int) $data['order_index'] : 0;
        $isActive = isset($data['is_active']) ? (int) $data['is_active'] : 1;
        
        if (empty($title)) {
            throw new \InvalidArgumentException('Título da cláusula é obrigatório');
        }
        
        if (empty($content)) {
            throw new \InvalidArgumentException('Conteúdo da cláusula é obrigatório');
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO contract_clauses (title, content, order_index, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([$title, $content, $orderIndex, $isActive]);
            
            return (int) $db->lastInsertId();
        } catch (\PDOException $e) {
            // Se a tabela não existe, lança exceção mais clara
            if (strpos($e->getMessage(), "doesn't exist") !== false || 
                strpos($e->getMessage(), "Table") !== false) {
                throw new \RuntimeException('Tabela contract_clauses não existe. Execute a migration primeiro: php database/migrate.php');
            }
            // Para outros erros de PDO, relança com mensagem mais clara
            error_log("Erro PDO ao criar cláusula: " . $e->getMessage());
            throw new \RuntimeException('Erro ao salvar cláusula no banco de dados: ' . $e->getMessage());
        }
    }
    
    /**
     * Atualiza uma cláusula
     * 
     * @param int $id ID da cláusula
     * @param array $data Dados para atualizar
     * @return bool Sucesso da operação
     */
    public static function updateClause(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        $clause = self::findClause($id);
        if (!$clause) {
            throw new \InvalidArgumentException('Cláusula não encontrada');
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['title'])) {
            $title = trim($data['title']);
            if (empty($title)) {
                throw new \InvalidArgumentException('Título da cláusula não pode ser vazio');
            }
            $updates[] = "title = ?";
            $params[] = $title;
        }
        
        if (isset($data['content'])) {
            $content = trim($data['content']);
            if (empty($content)) {
                throw new \InvalidArgumentException('Conteúdo da cláusula não pode ser vazio');
            }
            $updates[] = "content = ?";
            $params[] = $content;
        }
        
        if (isset($data['order_index'])) {
            $updates[] = "order_index = ?";
            $params[] = (int) $data['order_index'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = (int) $data['is_active'];
        }
        
        if (empty($updates)) {
            return true;
        }
        
        $updates[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE contract_clauses SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return true;
    }
    
    /**
     * Deleta uma cláusula
     * 
     * @param int $id ID da cláusula
     * @return bool Sucesso da operação
     */
    public static function deleteClause(int $id): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("DELETE FROM contract_clauses WHERE id = ?");
        $stmt->execute([$id]);
        
        return true;
    }
    
    /**
     * Substitui variáveis no conteúdo das cláusulas
     * 
     * @param string $content Conteúdo da cláusula
     * @param array $variables Variáveis para substituir
     * @return string Conteúdo com variáveis substituídas
     */
    public static function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }
    
    /**
     * Monta o conteúdo completo do contrato com todas as cláusulas
     * 
     * @param array $variables Variáveis para substituir nas cláusulas
     * @return string Conteúdo completo do contrato
     */
    public static function buildContractContent(array $variables): string
    {
        $clauses = self::getActiveClauses();
        $content = [];
        
        foreach ($clauses as $clause) {
            $clauseContent = self::replaceVariables($clause['content'], $variables);
            $content[] = "<h4>{$clause['title']}</h4>";
            $content[] = "<p>" . nl2br(htmlspecialchars($clauseContent)) . "</p>";
        }
        
        return implode("\n\n", $content);
    }
}

