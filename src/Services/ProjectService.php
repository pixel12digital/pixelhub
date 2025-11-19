<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar projetos
 */
class ProjectService
{
    /**
     * Lista todos os projetos com filtros opcionais
     */
    public static function getAllProjects(?int $tenantId = null, ?string $status = null, ?string $type = null, ?int $customerVisible = null): array
    {
        $db = DB::getConnection();
        
        $sql = "
            SELECT p.*, t.name as tenant_name
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($tenantId !== null) {
            $sql .= " AND p.tenant_id = ?";
            $params[] = $tenantId;
        }
        
        if ($status !== null) {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        if ($type !== null) {
            $sql .= " AND p.type = ?";
            $params[] = $type;
        }
        
        if ($customerVisible !== null) {
            $sql .= " AND p.is_customer_visible = ?";
            $params[] = $customerVisible;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca um projeto por ID
     */
    public static function findProject(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, t.name as tenant_name
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Cria um novo projeto
     */
    public static function createProject(array $data): int
    {
        $db = DB::getConnection();
        
        // Validações
        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('Nome do projeto é obrigatório');
        }
        
        if (strlen($name) > 150) {
            throw new \InvalidArgumentException('Nome do projeto deve ter no máximo 150 caracteres');
        }
        
        // Valida status
        $allowedStatuses = ['ativo', 'arquivado'];
        $status = trim($data['status'] ?? 'ativo');
        if (!in_array($status, $allowedStatuses)) {
            $status = 'ativo';
        }
        
        // Valida prioridade
        $allowedPriorities = ['baixa', 'media', 'alta', 'critica'];
        $priority = trim($data['priority'] ?? 'media');
        if (!in_array($priority, $allowedPriorities)) {
            $priority = 'media';
        }
        
        // Valida type
        $allowedTypes = ['interno', 'cliente'];
        $type = trim($data['type'] ?? 'interno');
        if (!in_array($type, $allowedTypes)) {
            $type = 'interno';
        }
        
        // Processa dados
        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;
        $description = trim($data['description'] ?? '') ?: null;
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
        $createdBy = !empty($data['created_by']) ? (int) $data['created_by'] : null;
        $isCustomerVisible = isset($data['is_customer_visible']) ? (int) $data['is_customer_visible'] : 0;
        
        // Se type = 'interno', força is_customer_visible = 0 por padrão
        if ($type === 'interno' && !isset($data['is_customer_visible'])) {
            $isCustomerVisible = 0;
        }
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO projects 
            (tenant_id, name, description, status, priority, type, is_customer_visible, template, due_date, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $tenantId,
            $name,
            $description,
            $status,
            $priority,
            $type,
            $isCustomerVisible,
            $dueDate,
            $createdBy,
        ]);
        
        return (int) $db->lastInsertId();
    }

    /**
     * Atualiza um projeto existente
     */
    public static function updateProject(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o projeto existe
        $project = self::findProject($id);
        if (!$project) {
            throw new \RuntimeException('Projeto não encontrado');
        }
        
        // Validações
        $name = trim($data['name'] ?? $project['name']);
        if (empty($name)) {
            throw new \InvalidArgumentException('Nome do projeto é obrigatório');
        }
        
        if (strlen($name) > 150) {
            throw new \InvalidArgumentException('Nome do projeto deve ter no máximo 150 caracteres');
        }
        
        // Valida status
        $allowedStatuses = ['ativo', 'arquivado'];
        $status = isset($data['status']) ? trim($data['status']) : $project['status'];
        if (!in_array($status, $allowedStatuses)) {
            $status = $project['status'];
        }
        
        // Valida prioridade
        $allowedPriorities = ['baixa', 'media', 'alta', 'critica'];
        $priority = isset($data['priority']) ? trim($data['priority']) : $project['priority'];
        if (!in_array($priority, $allowedPriorities)) {
            $priority = $project['priority'];
        }
        
        // Valida type
        $allowedTypes = ['interno', 'cliente'];
        $type = isset($data['type']) ? trim($data['type']) : ($project['type'] ?? 'interno');
        if (!in_array($type, $allowedTypes)) {
            $type = $project['type'] ?? 'interno';
        }
        
        // Processa dados
        $tenantId = isset($data['tenant_id']) ? (!empty($data['tenant_id']) ? (int) $data['tenant_id'] : null) : $project['tenant_id'];
        $description = isset($data['description']) ? (trim($data['description']) ?: null) : $project['description'];
        $dueDate = isset($data['due_date']) ? (!empty($data['due_date']) ? $data['due_date'] : null) : $project['due_date'];
        $updatedBy = !empty($data['updated_by']) ? (int) $data['updated_by'] : null;
        $isCustomerVisible = isset($data['is_customer_visible']) ? (int) $data['is_customer_visible'] : ($project['is_customer_visible'] ?? 0);
        
        // Se type = 'interno', força is_customer_visible = 0
        if ($type === 'interno') {
            $isCustomerVisible = 0;
        }
        
        // Atualiza no banco
        $stmt = $db->prepare("
            UPDATE projects 
            SET tenant_id = ?, name = ?, description = ?, status = ?, priority = ?, type = ?, is_customer_visible = ?, template = NULL, due_date = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $tenantId,
            $name,
            $description,
            $status,
            $priority,
            $type,
            $isCustomerVisible,
            $dueDate,
            $updatedBy,
            $id,
        ]);
        
        return true;
    }

    /**
     * Arquivar um projeto (seta status = 'arquivado')
     */
    public static function archiveProject(int $id): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o projeto existe
        $project = self::findProject($id);
        if (!$project) {
            throw new \RuntimeException('Projeto não encontrado');
        }
        
        $stmt = $db->prepare("
            UPDATE projects 
            SET status = 'arquivado', updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        
        return true;
    }

    /**
     * Retorna lista de projetos para uso em selects
     * Formato: id => "Nome (Cliente)" ou "Nome (Interno)"
     */
    public static function getProjectOptionsForSelect(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT p.id, p.name, t.name as tenant_name
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            WHERE p.status = 'ativo'
            ORDER BY p.name ASC
        ");
        
        $projects = $stmt->fetchAll();
        $options = [];
        
        foreach ($projects as $project) {
            $clientName = $project['tenant_name'] ?: 'Interno';
            $options[$project['id']] = "{$project['name']} ({$clientName})";
        }
        
        return $options;
    }
}

