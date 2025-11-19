<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar tarefas
 */
class TaskService
{
    /**
     * Lista tarefas de um projeto, agrupadas por status
     */
    public static function getTasksByProject(int $projectId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
                   (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done
            FROM tasks t
            WHERE t.project_id = ?
            ORDER BY t.status ASC, t.`order` ASC, t.created_at ASC
        ");
        $stmt->execute([$projectId]);
        $tasks = $stmt->fetchAll();
        
        // Agrupa por status
        $grouped = [
            'backlog' => [],
            'em_andamento' => [],
            'aguardando_cliente' => [],
            'concluida' => [],
        ];
        
        foreach ($tasks as $task) {
            $status = $task['status'];
            if (isset($grouped[$status])) {
                $grouped[$status][] = $task;
            } else {
                $grouped['backlog'][] = $task;
            }
        }
        
        return $grouped;
    }

    /**
     * Lista todas as tarefas (para o quadro Kanban geral)
     * 
     * @param int|null $projectId Filtro por projeto
     * @param int|null $tenantId Filtro por tenant/cliente
     * @param string|null $clientQuery Filtro por texto no nome do cliente (case-insensitive)
     */
    public static function getAllTasks(?int $projectId = null, ?int $tenantId = null, ?string $clientQuery = null): array
    {
        $db = DB::getConnection();
        
        $sql = "
            SELECT t.*, 
                   p.name as project_name,
                   p.tenant_id as project_tenant_id,
                   t2.name as tenant_name,
                   completed_user.name as completed_by_name,
                   (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
                   (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            LEFT JOIN tenants t2 ON p.tenant_id = t2.id
            LEFT JOIN users completed_user ON completed_user.id = t.completed_by
            WHERE 1=1
        ";
        
        $params = [];
        
        if ($projectId !== null) {
            $sql .= " AND t.project_id = ?";
            $params[] = $projectId;
        }
        
        if ($tenantId !== null) {
            $sql .= " AND p.tenant_id = ?";
            $params[] = $tenantId;
        }
        
        // Filtro por texto no nome do cliente (case-insensitive)
        if (!empty($clientQuery)) {
            $sql .= " AND t2.name LIKE ?";
            $searchTerm = '%' . $clientQuery . '%';
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY t.status ASC, t.`order` ASC, t.created_at ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        
        // Agrupa por status
        $grouped = [
            'backlog' => [],
            'em_andamento' => [],
            'aguardando_cliente' => [],
            'concluida' => [],
        ];
        
        foreach ($tasks as $task) {
            $status = $task['status'];
            if (isset($grouped[$status])) {
                $grouped[$status][] = $task;
            } else {
                $grouped['backlog'][] = $task;
            }
        }
        
        return $grouped;
    }

    /**
     * Cria uma nova tarefa
     */
    public static function createTask(array $data): int
    {
        $db = DB::getConnection();
        
        // Validações
        $projectId = isset($data['project_id']) ? (int) $data['project_id'] : 0;
        if ($projectId <= 0) {
            throw new \InvalidArgumentException('ID do projeto é obrigatório');
        }
        
        // Verifica se o projeto existe
        $project = \PixelHub\Services\ProjectService::findProject($projectId);
        if (!$project) {
            throw new \RuntimeException('Projeto não encontrado');
        }
        
        $title = trim($data['title'] ?? '');
        if (empty($title)) {
            throw new \InvalidArgumentException('Título da tarefa é obrigatório');
        }
        
        if (strlen($title) > 200) {
            throw new \InvalidArgumentException('Título da tarefa deve ter no máximo 200 caracteres');
        }
        
        // Valida status
        $allowedStatuses = ['backlog', 'em_andamento', 'aguardando_cliente', 'concluida'];
        $status = trim($data['status'] ?? 'backlog');
        if (!in_array($status, $allowedStatuses)) {
            $status = 'backlog';
        }
        
        // Processa dados
        $description = trim($data['description'] ?? '') ?: null;
        $assignee = trim($data['assignee'] ?? '') ?: null;
        
        // Tratamento de datas: devido ao campo ser DATE (não DATETIME), tratamos como string Y-m-d pura
        // Sem conversão de timezone para evitar perda de 1 dia
        $dueDate = null;
        if (!empty($data['due_date'])) {
            // Se vier como YYYY-MM-DD (formato do input date), usa direto
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
                $dueDate = $data['due_date'];
            } else {
                // Tenta converter outros formatos para Y-m-d
                try {
                    $date = new \DateTime($data['due_date']);
                    $dueDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    error_log("Erro ao converter due_date: " . $e->getMessage());
                }
            }
        }
        
        // Data de início: se não informada, preenche com data atual (timezone America/Sao_Paulo)
        $startDate = null;
        if (!empty($data['start_date'])) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'])) {
                $startDate = $data['start_date'];
            } else {
                try {
                    $date = new \DateTime($data['start_date'], new \DateTimeZone('America/Sao_Paulo'));
                    $startDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    error_log("Erro ao converter start_date: " . $e->getMessage());
                }
            }
        } else {
            // Pré-preenche com data atual no timezone de São Paulo
            $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $startDate = $now->format('Y-m-d');
        }
        
        // Tipo de tarefa: 'internal' ou 'client_ticket', padrão 'internal'
        $taskType = trim($data['task_type'] ?? 'internal');
        if (!in_array($taskType, ['internal', 'client_ticket'])) {
            $taskType = 'internal';
        }
        
        $createdBy = !empty($data['created_by']) ? (int) $data['created_by'] : null;
        
        // Calcula order (maior order da coluna + 1)
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(`order`), 0) + 1 as next_order
            FROM tasks
            WHERE project_id = ? AND status = ?
        ");
        $stmt->execute([$projectId, $status]);
        $result = $stmt->fetch();
        $order = (int) ($result['next_order'] ?? 1);
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO tasks 
            (project_id, title, description, status, `order`, assignee, due_date, start_date, task_type, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $projectId,
            $title,
            $description,
            $status,
            $order,
            $assignee,
            $dueDate,
            $startDate,
            $taskType,
            $createdBy,
        ]);
        
        $taskId = (int) $db->lastInsertId();
        
        return $taskId;
    }

    /**
     * Atualiza uma tarefa existente
     */
    public static function updateTask(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        // Verifica se a tarefa existe
        $task = self::findTask($id);
        if (!$task) {
            throw new \RuntimeException('Tarefa não encontrada');
        }
        
        // Validações
        $title = isset($data['title']) ? trim($data['title']) : $task['title'];
        if (empty($title)) {
            throw new \InvalidArgumentException('Título da tarefa é obrigatório');
        }
        
        if (strlen($title) > 200) {
            throw new \InvalidArgumentException('Título da tarefa deve ter no máximo 200 caracteres');
        }
        
        // Valida status se fornecido
        $status = $task['status'];
        if (isset($data['status'])) {
            $allowedStatuses = ['backlog', 'em_andamento', 'aguardando_cliente', 'concluida'];
            $newStatus = trim($data['status']);
            if (in_array($newStatus, $allowedStatuses)) {
                $status = $newStatus;
            }
        }
        
        // Processa dados
        $description = isset($data['description']) ? (trim($data['description']) ?: null) : $task['description'];
        $assignee = isset($data['assignee']) ? (trim($data['assignee']) ?: null) : $task['assignee'];
        
        // Tratamento de datas: devido ao campo ser DATE (não DATETIME), tratamos como string Y-m-d pura
        // Sem conversão de timezone para evitar perda de 1 dia
        $dueDate = $task['due_date'];
        if (isset($data['due_date'])) {
            if (empty($data['due_date'])) {
                $dueDate = null;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['due_date'])) {
                $dueDate = $data['due_date'];
            } else {
                try {
                    $date = new \DateTime($data['due_date']);
                    $dueDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    error_log("Erro ao converter due_date: " . $e->getMessage());
                }
            }
        }
        
        // Data de início
        $startDate = $task['start_date'] ?? null;
        if (isset($data['start_date'])) {
            if (empty($data['start_date'])) {
                $startDate = null;
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start_date'])) {
                $startDate = $data['start_date'];
            } else {
                try {
                    $date = new \DateTime($data['start_date'], new \DateTimeZone('America/Sao_Paulo'));
                    $startDate = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    error_log("Erro ao converter start_date: " . $e->getMessage());
                }
            }
        }
        
        // Tipo de tarefa
        $taskType = $task['task_type'] ?? 'internal';
        if (isset($data['task_type'])) {
            $newTaskType = trim($data['task_type']);
            if (in_array($newTaskType, ['internal', 'client_ticket'])) {
                $taskType = $newTaskType;
            }
        }
        
        $order = isset($data['order']) ? (int) $data['order'] : $task['order'];
        
        // Lógica de conclusão: preenche ou limpa completed_at/completed_by conforme o status
        $completedAt = $task['completed_at'] ?? null;
        $completedBy = $task['completed_by'] ?? null;
        $completionNote = $task['completion_note'] ?? null;
        
        $oldStatus = $task['status'];
        if ($status === 'concluida') {
            // Se está indo para "concluida", preenche completed_at e completed_by (se ainda não estiver preenchido)
            if (empty($completedAt)) {
                $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                $completedAt = $now->format('Y-m-d H:i:s');
            }
            
            // Preenche completed_by com o usuário logado (se disponível e ainda não preenchido)
            if (empty($completedBy)) {
                $user = \PixelHub\Core\Auth::user();
                if ($user && isset($user['id'])) {
                    $completedBy = (int) $user['id'];
                }
            }
        } else {
            // Se está saindo de "concluida", limpa completed_at e completed_by
            $completedAt = null;
            $completedBy = null;
        }
        
        // Permite atualizar completion_note sempre que a tarefa estiver concluída
        if ($status === 'concluida' && isset($data['completion_note'])) {
            $completionNote = trim($data['completion_note']) ?: null;
        }
        
        // Atualiza no banco
        $stmt = $db->prepare("
            UPDATE tasks 
            SET title = ?, description = ?, status = ?, `order` = ?, assignee = ?, 
                due_date = ?, start_date = ?, task_type = ?, 
                completed_at = ?, completed_by = ?, completion_note = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $title,
            $description,
            $status,
            $order,
            $assignee,
            $dueDate,
            $startDate,
            $taskType,
            $completedAt,
            $completedBy,
            $completionNote,
            $id,
        ]);
        
        return true;
    }

    /**
     * Move uma tarefa para outra coluna (status) e reajusta ordens
     */
    public static function moveTask(int $id, string $newStatus, ?int $newOrder = null): bool
    {
        $db = DB::getConnection();
        
        // Verifica se a tarefa existe
        $task = self::findTask($id);
        if (!$task) {
            throw new \RuntimeException('Tarefa não encontrada');
        }
        
        // Valida status
        $allowedStatuses = ['backlog', 'em_andamento', 'aguardando_cliente', 'concluida'];
        if (!in_array($newStatus, $allowedStatuses)) {
            throw new \InvalidArgumentException('Status inválido');
        }
        
        $oldStatus = $task['status'];
        $oldOrder = $task['order'];
        $projectId = $task['project_id'];
        
        // Se mudou de coluna, precisa reajustar ordens
        if ($oldStatus !== $newStatus) {
            // Remove a tarefa da ordem antiga (desloca as outras para cima)
            $stmt = $db->prepare("
                UPDATE tasks 
                SET `order` = `order` - 1
                WHERE project_id = ? AND status = ? AND `order` > ?
            ");
            $stmt->execute([$projectId, $oldStatus, $oldOrder]);
            
            // Se newOrder não foi fornecido, coloca no final da nova coluna
            if ($newOrder === null) {
                $stmt = $db->prepare("
                    SELECT COALESCE(MAX(`order`), 0) + 1 as next_order
                    FROM tasks
                    WHERE project_id = ? AND status = ?
                ");
                $stmt->execute([$projectId, $newStatus]);
                $result = $stmt->fetch();
                $newOrder = (int) ($result['next_order'] ?? 1);
            } else {
                // Desloca tarefas na nova coluna para abrir espaço
                $stmt = $db->prepare("
                    UPDATE tasks 
                    SET `order` = `order` + 1
                    WHERE project_id = ? AND status = ? AND `order` >= ?
                ");
                $stmt->execute([$projectId, $newStatus, $newOrder]);
            }
        } else {
            // Mesma coluna, apenas reordena
            if ($newOrder === null) {
                $newOrder = $oldOrder;
            } else if ($newOrder !== $oldOrder) {
                if ($newOrder > $oldOrder) {
                    // Moveu para baixo: desloca tarefas entre oldOrder e newOrder para cima
                    $stmt = $db->prepare("
                        UPDATE tasks 
                        SET `order` = `order` - 1
                        WHERE project_id = ? AND status = ? AND `order` > ? AND `order` <= ?
                    ");
                    $stmt->execute([$projectId, $newStatus, $oldOrder, $newOrder]);
                } else {
                    // Moveu para cima: desloca tarefas entre newOrder e oldOrder para baixo
                    $stmt = $db->prepare("
                        UPDATE tasks 
                        SET `order` = `order` + 1
                        WHERE project_id = ? AND status = ? AND `order` >= ? AND `order` < ?
                    ");
                    $stmt->execute([$projectId, $newStatus, $newOrder, $oldOrder]);
                }
            }
        }
        
        // Lógica de conclusão: preenche ou limpa completed_at/completed_by conforme o status
        $completedAt = null;
        $completedBy = null;
        
        if ($newStatus === 'concluida') {
            // Se está indo para "concluida", preenche completed_at e completed_by
            $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $completedAt = $now->format('Y-m-d H:i:s');
            
            // Preenche completed_by com o usuário logado (se disponível)
            $user = \PixelHub\Core\Auth::user();
            if ($user && isset($user['id'])) {
                $completedBy = (int) $user['id'];
            }
        } else {
            // Se está saindo de "concluida", limpa completed_at e completed_by
            $completedAt = null;
            $completedBy = null;
        }
        
        // Atualiza a tarefa
        $stmt = $db->prepare("
            UPDATE tasks 
            SET status = ?, `order` = ?, completed_at = ?, completed_by = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$newStatus, $newOrder, $completedAt, $completedBy, $id]);
        
        return true;
    }

    /**
     * Busca uma tarefa por ID
     */
    public static function findTask(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT t.*, 
                   p.name as project_name,
                   p.tenant_id as project_tenant_id,
                   t2.name as tenant_name,
                   completed_user.name as completed_by_name
            FROM tasks t
            INNER JOIN projects p ON t.project_id = p.id
            LEFT JOIN tenants t2 ON p.tenant_id = t2.id
            LEFT JOIN users completed_user ON completed_user.id = t.completed_by
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retorna resumo de tarefas por status para um projeto específico
     */
    public static function getProjectSummary(int $projectId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'backlog' THEN 1 ELSE 0 END) as backlog,
                SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                SUM(CASE WHEN status = 'aguardando_cliente' THEN 1 ELSE 0 END) as aguardando_cliente,
                SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluida
            FROM tasks
            WHERE project_id = ?
        ");
        $stmt->execute([$projectId]);
        $result = $stmt->fetch();
        
        return [
            'total' => (int) ($result['total'] ?? 0),
            'backlog' => (int) ($result['backlog'] ?? 0),
            'em_andamento' => (int) ($result['em_andamento'] ?? 0),
            'aguardando_cliente' => (int) ($result['aguardando_cliente'] ?? 0),
            'concluida' => (int) ($result['concluida'] ?? 0),
        ];
    }
}

