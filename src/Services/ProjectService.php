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
     * 
     * Lógica de tipo:
     * - Projeto de Cliente: tem tenant_id não nulo E type = 'cliente'
     * - Projeto Interno: tenant_id é nulo OU type = 'interno'
     */
    public static function getAllProjects(?int $tenantId = null, ?string $status = null, ?string $type = null, ?int $customerVisible = null): array
    {
        $db = DB::getConnection();
        
        $sql = "
            SELECT p.*, t.name as tenant_name, s.name as service_name,
                   CASE 
                       WHEN p.tenant_id IS NOT NULL THEN 'cliente'
                       ELSE 'interno'
                   END as effective_type
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            LEFT JOIN services s ON p.service_id = s.id
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
            // Se filtro por tipo 'cliente', busca projetos com tenant_id não nulo (projetos de cliente)
            // Se filtro por tipo 'interno', busca projetos sem tenant_id (projetos internos)
            // IMPORTANTE: Um projeto com tenant_id não nulo é sempre considerado projeto de cliente
            if ($type === 'cliente') {
                $sql .= " AND p.tenant_id IS NOT NULL";
            } else {
                $sql .= " AND p.tenant_id IS NULL";
            }
        }
        
        if ($customerVisible !== null) {
            $sql .= " AND p.is_customer_visible = ?";
            $params[] = $customerVisible;
        }
        
        // Ordena por nome para facilitar localização (mais intuitivo que created_at)
        $sql .= " ORDER BY p.name ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll();
        
        // Debug temporário - remover depois
        // if ($status === 'ativo' && $tenantId === null && $type === null) {
        //     foreach ($results as $proj) {
        //         if (stripos($proj['name'], 'CFC') !== false || stripos($proj['name'], 'Bom Conselho') !== false) {
        //             error_log("[ProjectService] Projeto encontrado: ID=" . $proj['id'] . ", Name=" . $proj['name'] . ", Status=" . ($proj['status'] ?? 'N/A') . ", TenantID=" . ($proj['tenant_id'] ?? 'NULL'));
        //         }
        //     }
        // }
        
        return $results;
    }

    /**
     * Busca um projeto por ID
     */
    public static function findProject(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT p.*, t.name as tenant_name, s.name as service_name
            FROM projects p
            LEFT JOIN tenants t ON p.tenant_id = t.id
            LEFT JOIN services s ON p.service_id = s.id
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
        
        // Processa dados primeiro
        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;
        $serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        
        // IMPORTANTE: Se tem tenant_id, o tipo deve ser 'cliente'
        // Se não tem tenant_id, o tipo deve ser 'interno'
        // O tipo é determinado pelo tenant_id, não pelo campo type do formulário
        if (!empty($tenantId)) {
            $type = 'cliente';
        } else {
            $type = 'interno';
        }
        $description = trim($data['description'] ?? '') ?: null;
        $dueDate = !empty($data['due_date']) ? $data['due_date'] : null;
        $createdBy = !empty($data['created_by']) ? (int) $data['created_by'] : null;
        $isCustomerVisible = isset($data['is_customer_visible']) ? (int) $data['is_customer_visible'] : 0;
        
        // Campos para projetos satélites
        $slug = !empty($data['slug']) ? trim($data['slug']) : null;
        $baseUrl = !empty($data['base_url']) ? trim($data['base_url']) : null;
        $externalProjectId = !empty($data['external_project_id']) ? trim($data['external_project_id']) : null;
        
        // Se type = 'interno', força is_customer_visible = 0 por padrão
        if ($type === 'interno' && !isset($data['is_customer_visible'])) {
            $isCustomerVisible = 0;
        }
        
        // Gera slug automático se não fornecido
        if (empty($slug) && !empty($name)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        }
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO projects 
            (tenant_id, service_id, name, slug, external_project_id, base_url, description, status, priority, type, is_customer_visible, template, due_date, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $tenantId,
            $serviceId,
            $name,
            $slug,
            $externalProjectId,
            $baseUrl,
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
        $serviceId = isset($data['service_id']) ? (!empty($data['service_id']) ? (int) $data['service_id'] : null) : ($project['service_id'] ?? null);
        $description = isset($data['description']) ? (trim($data['description']) ?: null) : $project['description'];
        $dueDate = isset($data['due_date']) ? (!empty($data['due_date']) ? $data['due_date'] : null) : $project['due_date'];
        $updatedBy = !empty($data['updated_by']) ? (int) $data['updated_by'] : null;
        $isCustomerVisible = isset($data['is_customer_visible']) ? (int) $data['is_customer_visible'] : ($project['is_customer_visible'] ?? 0);
        
        // IMPORTANTE: Se tem tenant_id, o tipo deve ser 'cliente'
        // Se não tem tenant_id, o tipo deve ser 'interno'
        if (!empty($tenantId)) {
            $type = 'cliente';
        } else {
            $type = 'interno';
        }
        
        // Campos para projetos satélites
        $slug = isset($data['slug']) ? (!empty($data['slug']) ? trim($data['slug']) : null) : ($project['slug'] ?? null);
        $baseUrl = isset($data['base_url']) ? (!empty($data['base_url']) ? trim($data['base_url']) : null) : ($project['base_url'] ?? null);
        $externalProjectId = isset($data['external_project_id']) ? (!empty($data['external_project_id']) ? trim($data['external_project_id']) : null) : ($project['external_project_id'] ?? null);
        
        // Gera slug automático se não fornecido e nome mudou
        if (empty($slug) && !empty($name) && $name !== ($project['name'] ?? '')) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
            $slug = preg_replace('/-+/', '-', $slug);
            $slug = trim($slug, '-');
        }
        
        // Se type = 'interno', força is_customer_visible = 0
        if ($type === 'interno') {
            $isCustomerVisible = 0;
        }
        
        // Atualiza no banco
        $stmt = $db->prepare("
            UPDATE projects 
            SET tenant_id = ?, service_id = ?, name = ?, slug = ?, external_project_id = ?, base_url = ?, description = ?, status = ?, priority = ?, type = ?, is_customer_visible = ?, template = NULL, due_date = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $tenantId,
            $serviceId,
            $name,
            $slug,
            $externalProjectId,
            $baseUrl,
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
     * Desarquivar um projeto (seta status = 'ativo')
     */
    public static function unarchiveProject(int $id): bool
    {
        $db = DB::getConnection();
        
        $project = self::findProject($id);
        if (!$project) {
            throw new \RuntimeException('Projeto não encontrado');
        }
        
        $stmt = $db->prepare("
            UPDATE projects 
            SET status = 'ativo', updated_at = NOW()
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

    /**
     * Verifica se um projeto está concluído
     * Um projeto é considerado concluído quando:
     * - Possui tarefas E todas estão concluídas (sem tarefas pendentes ou em andamento)
     * - Possui tickets E todos estão resolvidos (sem tickets pendentes ou em atendimento)
     * - Se não tem tarefas nem tickets, não é considerado concluído (é apenas um projeto novo/vazio)
     * 
     * @param int $projectId ID do projeto
     * @return bool True se o projeto está concluído, False caso contrário
     */
    public static function isProjectCompleted(int $projectId): bool
    {
        $db = DB::getConnection();
        
        // Verifica total de tarefas e quantas estão pendentes
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status != 'concluida' AND deleted_at IS NULL THEN 1 ELSE 0 END) as pending
                FROM tasks
                WHERE project_id = ?
                  AND deleted_at IS NULL
            ");
            $stmt->execute([$projectId]);
        } catch (\PDOException $e) {
            // Se deleted_at não existe, tenta sem essa condição
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status != 'concluida' THEN 1 ELSE 0 END) as pending
                FROM tasks
                WHERE project_id = ?
            ");
            $stmt->execute([$projectId]);
        }
        
        $result = $stmt->fetch();
        $totalTasks = (int) ($result['total'] ?? 0);
        $pendingTasks = (int) ($result['pending'] ?? 0);
        
        // Verifica total de tickets e quantos estão pendentes
        $totalTickets = 0;
        $pendingTickets = 0;
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status NOT IN ('resolvido', 'fechado', 'cancelado') THEN 1 ELSE 0 END) as pending
                FROM tickets
                WHERE project_id = ?
            ");
            $stmt->execute([$projectId]);
            $result = $stmt->fetch();
            $totalTickets = (int) ($result['total'] ?? 0);
            $pendingTickets = (int) ($result['pending'] ?? 0);
        } catch (\PDOException $e) {
            // Se a tabela tickets não existir, ignora
        }
        
        // Se há tarefas ou tickets pendentes, o projeto não está concluído
        if ($pendingTasks > 0 || $pendingTickets > 0) {
            return false;
        }
        
        // Projeto está concluído apenas se TEM tarefas/tickets E todos estão concluídos
        // Se não tem nenhuma tarefa nem ticket, não é considerado concluído (projeto vazio)
        return ($totalTasks > 0 || $totalTickets > 0);
    }

    /**
     * Lista projetos ativos excluindo os que estão concluídos
     * Usado para filtrar projetos concluídos do seletor do kanban
     * 
     * @param int|null $tenantId Filtro por tenant/cliente
     * @param string|null $type Filtro por tipo ('interno' ou 'cliente')
     * @return array Lista de projetos não concluídos
     */
    public static function getActiveNonCompletedProjects(?int $tenantId = null, ?string $type = null): array
    {
        $projects = self::getAllProjects($tenantId, 'ativo', $type);
        
        // Filtra projetos concluídos
        $nonCompletedProjects = [];
        foreach ($projects as $project) {
            if (!self::isProjectCompleted((int) $project['id'])) {
                $nonCompletedProjects[] = $project;
            }
        }
        
        return $nonCompletedProjects;
    }

    /**
     * Arquivar automaticamente projetos concluídos
     * Verifica todos os projetos ativos e arquiva aqueles que estão concluídos
     * 
     * @return array Array com informações sobre projetos arquivados
     */
    public static function autoArchiveCompletedProjects(): array
    {
        $db = DB::getConnection();
        
        // Busca todos os projetos ativos
        $projects = self::getAllProjects(null, 'ativo', null);
        
        $archived = [];
        foreach ($projects as $project) {
            $projectId = (int) $project['id'];
            
            // Verifica se está concluído
            if (self::isProjectCompleted($projectId)) {
                try {
                    self::archiveProject($projectId);
                    $archived[] = [
                        'id' => $projectId,
                        'name' => $project['name'],
                    ];
                } catch (\Exception $e) {
                    error_log("Erro ao arquivar projeto {$projectId}: " . $e->getMessage());
                }
            }
        }
        
        return $archived;
    }
}

