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
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        try {
            $stmt = $db->prepare("
                SELECT t.*, 
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done
                FROM tasks t
                WHERE t.project_id = ? AND t.deleted_at IS NULL
                ORDER BY t.status ASC, COALESCE(t.completed_at, t.updated_at) DESC, t.`order` ASC
            ");
            $stmt->execute([$projectId]);
        } catch (\PDOException $e) {
            // Se deu erro, tenta sem a condição deleted_at
            $stmt = $db->prepare("
                SELECT t.*, 
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done
                FROM tasks t
                WHERE t.project_id = ?
                ORDER BY t.status ASC, COALESCE(t.completed_at, t.updated_at) DESC, t.`order` ASC
            ");
            $stmt->execute([$projectId]);
        }
        
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
     * @param string|null $agendaFilter Filtro por agenda: 'with' (com agenda), 'without' (sem agenda), null (todas)
     */
    public static function getAllTasks(?int $projectId = null, ?int $tenantId = null, ?string $clientQuery = null, ?string $agendaFilter = null): array
    {
        $db = DB::getConnection();
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        // Se der erro, tenta sem a condição (compatibilidade com banco antigo)
        try {
            $sql = "
                SELECT t.*, 
                       p.name as project_name,
                       p.tenant_id as project_tenant_id,
                       t2.name as tenant_name,
                       completed_user.name as completed_by_name,
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done,
                       CASE WHEN EXISTS (SELECT 1 FROM agenda_block_tasks WHERE task_id = t.id) THEN 1 ELSE 0 END as has_agenda_blocks,
                       (SELECT b.id FROM agenda_block_tasks abt INNER JOIN agenda_blocks b ON abt.bloco_id = b.id WHERE abt.task_id = t.id ORDER BY b.data DESC, b.hora_inicio DESC LIMIT 1) as agenda_block_id,
                       (SELECT b.data FROM agenda_block_tasks abt INNER JOIN agenda_blocks b ON abt.bloco_id = b.id WHERE abt.task_id = t.id ORDER BY b.data DESC, b.hora_inicio DESC LIMIT 1) as agenda_block_date
                FROM tasks t
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants t2 ON p.tenant_id = t2.id
                LEFT JOIN users completed_user ON completed_user.id = t.completed_by
                WHERE t.deleted_at IS NULL
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
            
            // Filtro por agenda
            if ($agendaFilter === 'with') {
                $sql .= " AND EXISTS (SELECT 1 FROM agenda_block_tasks WHERE task_id = t.id)";
            } elseif ($agendaFilter === 'without') {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM agenda_block_tasks WHERE task_id = t.id)";
            }
            
            $sql .= " ORDER BY t.status ASC, COALESCE(t.completed_at, t.updated_at) DESC, t.`order` ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
        } catch (\PDOException $e) {
            // Se deu erro (provavelmente coluna deleted_at não existe), tenta sem a condição
            $sql = "
                SELECT t.*, 
                       p.name as project_name,
                       p.tenant_id as project_tenant_id,
                       t2.name as tenant_name,
                       completed_user.name as completed_by_name,
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id) as checklist_total,
                       (SELECT COUNT(*) FROM task_checklists WHERE task_id = t.id AND is_done = 1) as checklist_done,
                       CASE WHEN EXISTS (SELECT 1 FROM agenda_block_tasks WHERE task_id = t.id) THEN 1 ELSE 0 END as has_agenda_blocks,
                       (SELECT b.id FROM agenda_block_tasks abt INNER JOIN agenda_blocks b ON abt.bloco_id = b.id WHERE abt.task_id = t.id ORDER BY b.data DESC, b.hora_inicio DESC LIMIT 1) as agenda_block_id,
                       (SELECT b.data FROM agenda_block_tasks abt INNER JOIN agenda_blocks b ON abt.bloco_id = b.id WHERE abt.task_id = t.id ORDER BY b.data DESC, b.hora_inicio DESC LIMIT 1) as agenda_block_date
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
            
            // Filtro por agenda
            if ($agendaFilter === 'with') {
                $sql .= " AND EXISTS (SELECT 1 FROM agenda_block_tasks WHERE task_id = t.id)";
            } elseif ($agendaFilter === 'without') {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM agenda_block_tasks WHERE task_id = t.id)";
            }
            
            $sql .= " ORDER BY t.status ASC, COALESCE(t.completed_at, t.updated_at) DESC, t.`order` ASC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll();
        }
        
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
        
        // Tipo de tarefa: 'internal', 'client_ticket', 'finance_overdue', etc.
        $taskType = trim($data['task_type'] ?? 'internal');
        $allowedTaskTypes = ['internal', 'client_ticket', 'finance_overdue', 'lead_followup', 'crm_followup'];
        if (!in_array($taskType, $allowedTaskTypes)) {
            $taskType = 'internal';
        }
        
        $createdBy = !empty($data['created_by']) ? (int) $data['created_by'] : null;
        
        // Verificação de duplicidade: verifica se existe tarefa similar criada nos últimos 60 segundos
        // Considera duplicada quando: mesmo project_id, mesmo title, mesmas datas (quando existirem)
        // Tenta primeiro com deleted_at (se a coluna existir)
        try {
            $duplicateSql = "
                SELECT id 
                FROM tasks 
                WHERE project_id = ? 
                  AND title = ? 
                  AND deleted_at IS NULL
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ";
            $duplicateParams = [$projectId, $title];
            
            if ($startDate) {
                $duplicateSql .= " AND start_date = ?";
                $duplicateParams[] = $startDate;
            } else {
                $duplicateSql .= " AND start_date IS NULL";
            }
            
            if ($dueDate) {
                $duplicateSql .= " AND due_date = ?";
                $duplicateParams[] = $dueDate;
            } else {
                $duplicateSql .= " AND due_date IS NULL";
            }
            
            $duplicateCheck = $db->prepare($duplicateSql);
            $duplicateCheck->execute($duplicateParams);
        } catch (\PDOException $e) {
            // Se deu erro, tenta sem deleted_at
            $duplicateSql = "
                SELECT id 
                FROM tasks 
                WHERE project_id = ? 
                  AND title = ? 
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ";
            $duplicateParams = [$projectId, $title];
            
            if ($startDate) {
                $duplicateSql .= " AND start_date = ?";
                $duplicateParams[] = $startDate;
            } else {
                $duplicateSql .= " AND start_date IS NULL";
            }
            
            if ($dueDate) {
                $duplicateSql .= " AND due_date = ?";
                $duplicateParams[] = $dueDate;
            } else {
                $duplicateSql .= " AND due_date IS NULL";
            }
            
            $duplicateCheck = $db->prepare($duplicateSql);
            $duplicateCheck->execute($duplicateParams);
        }
        
        $duplicate = $duplicateCheck->fetch();
        
        if ($duplicate) {
            // Retorna o ID da tarefa existente em vez de criar duplicada
            return (int) $duplicate['id'];
        }
        
        // Calcula order (maior order da coluna + 1)
        try {
            $stmt = $db->prepare("
                SELECT COALESCE(MAX(`order`), 0) + 1 as next_order
                FROM tasks
                WHERE project_id = ? AND status = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$projectId, $status]);
        } catch (\PDOException $e) {
            // Se deu erro, tenta sem deleted_at
            $stmt = $db->prepare("
                SELECT COALESCE(MAX(`order`), 0) + 1 as next_order
                FROM tasks
                WHERE project_id = ? AND status = ?
            ");
            $stmt->execute([$projectId, $status]);
        }
        $result = $stmt->fetch();
        $order = (int) ($result['next_order'] ?? 1);
        
        // Se status for concluída, preenche completed_at e completed_by (para aparecer no relatório)
        $completedAt = null;
        $completedBy = null;
        if ($status === 'concluida') {
            $now = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
            $completedAt = $now->format('Y-m-d H:i:s');
            $completedBy = $createdBy;
            if (!$completedBy && class_exists(\PixelHub\Core\Auth::class)) {
                $user = \PixelHub\Core\Auth::user();
                $completedBy = $user['id'] ?? null;
            }
        }
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO tasks 
            (project_id, title, description, status, `order`, assignee, due_date, start_date, task_type, created_by, created_at, updated_at, completed_at, completed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
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
            $completedAt,
            $completedBy,
        ]);
        
        $taskId = (int) $db->lastInsertId();
        
        // Checklist: itens enviados junto com a criação (checklist_items[] ou checklist_items)
        $checklistItems = $data['checklist_items'] ?? [];
        if (is_string($checklistItems)) {
            $checklistItems = $checklistItems ? [$checklistItems] : [];
        }
        foreach ($checklistItems as $label) {
            $label = is_array($label) ? trim($label['label'] ?? $label['name'] ?? '') : trim((string) $label);
            if (!empty($label) && strlen($label) <= 255) {
                try {
                    TaskChecklistService::addItem($taskId, $label);
                } catch (\Exception $e) {
                    error_log("Erro ao adicionar item ao checklist na criação: " . $e->getMessage());
                }
            }
        }
        
        // REMOVIDO: Vínculo automático com Agenda
        // Agora as tarefas só são vinculadas manualmente via:
        // - Botão "Agendar na Agenda" no modal da tarefa
        // - Botão "Vincular tarefa existente" dentro do bloco
        
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
            $allowedTaskTypes = ['internal', 'client_ticket', 'finance_overdue', 'lead_followup', 'crm_followup'];
            if (in_array($newTaskType, $allowedTaskTypes)) {
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
            // Valida se há checklists em aberto antes de concluir
            if ($oldStatus !== 'concluida' && \PixelHub\Services\TaskChecklistService::hasOpenChecklists($id)) {
                throw new \InvalidArgumentException('Não é possível concluir a tarefa. Existem itens do checklist que ainda não foram concluídos. Por favor, conclua todos os itens do checklist antes de finalizar a tarefa.');
            }
            
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
        
        // Arquivamento de projetos é manual (filtro Status na tela de projetos)
        
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
                WHERE project_id = ? AND status = ? AND `order` > ? AND deleted_at IS NULL
            ");
            $stmt->execute([$projectId, $oldStatus, $oldOrder]);
            
            // Se newOrder não foi fornecido, coloca no final da nova coluna
            if ($newOrder === null) {
                $stmt = $db->prepare("
                    SELECT COALESCE(MAX(`order`), 0) + 1 as next_order
                    FROM tasks
                    WHERE project_id = ? AND status = ? AND deleted_at IS NULL
                ");
                $stmt->execute([$projectId, $newStatus]);
                $result = $stmt->fetch();
                $newOrder = (int) ($result['next_order'] ?? 1);
            } else {
                // Desloca tarefas na nova coluna para abrir espaço
                $stmt = $db->prepare("
                    UPDATE tasks 
                    SET `order` = `order` + 1
                    WHERE project_id = ? AND status = ? AND `order` >= ? AND deleted_at IS NULL
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
                        WHERE project_id = ? AND status = ? AND `order` > ? AND `order` <= ? AND deleted_at IS NULL
                    ");
                    $stmt->execute([$projectId, $newStatus, $oldOrder, $newOrder]);
                } else {
                    // Moveu para cima: desloca tarefas entre newOrder e oldOrder para baixo
                    $stmt = $db->prepare("
                        UPDATE tasks 
                        SET `order` = `order` + 1
                        WHERE project_id = ? AND status = ? AND `order` >= ? AND `order` < ? AND deleted_at IS NULL
                    ");
                    $stmt->execute([$projectId, $newStatus, $newOrder, $oldOrder]);
                }
            }
        }
        
        // Lógica de conclusão: preenche ou limpa completed_at/completed_by conforme o status
        $completedAt = null;
        $completedBy = null;
        
        if ($newStatus === 'concluida') {
            // Valida se há checklists em aberto antes de concluir
            if ($oldStatus !== 'concluida' && \PixelHub\Services\TaskChecklistService::hasOpenChecklists($id)) {
                throw new \InvalidArgumentException('Não é possível concluir a tarefa. Existem itens do checklist que ainda não foram concluídos. Por favor, conclua todos os itens do checklist antes de finalizar a tarefa.');
            }
            
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
        
        // Arquivamento de projetos é manual (filtro Status na tela de projetos)
        
        return true;
    }

    /**
     * Busca uma tarefa por ID
     */
    public static function findTask(int $id): ?array
    {
        $db = DB::getConnection();
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        try {
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
                WHERE t.id = ? AND t.deleted_at IS NULL
            ");
            $stmt->execute([$id]);
        } catch (\PDOException $e) {
            // Se deu erro, tenta sem a condição deleted_at
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
        }
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Retorna resumo de tarefas por status para um projeto específico
     */
    public static function getProjectSummary(int $projectId): array
    {
        $db = DB::getConnection();
        
        // Tenta primeiro com deleted_at (se a coluna existir)
        try {
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'backlog' THEN 1 ELSE 0 END) as backlog,
                    SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                    SUM(CASE WHEN status = 'aguardando_cliente' THEN 1 ELSE 0 END) as aguardando_cliente,
                    SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) as concluida
                FROM tasks
                WHERE project_id = ? AND deleted_at IS NULL
            ");
            $stmt->execute([$projectId]);
        } catch (\PDOException $e) {
            // Se deu erro, tenta sem a condição deleted_at
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
        }
        
        $result = $stmt->fetch();
        
        $summary = [
            'total' => (int) ($result['total'] ?? 0),
            'backlog' => (int) ($result['backlog'] ?? 0),
            'em_andamento' => (int) ($result['em_andamento'] ?? 0),
            'aguardando_cliente' => (int) ($result['aguardando_cliente'] ?? 0),
            'concluida' => (int) ($result['concluida'] ?? 0),
        ];

        // Contagem de tarefas atrasadas (due_date < hoje, status != concluida)
        $today = date('Y-m-d');
        try {
            $stmtOverdue = $db->prepare("
                SELECT COUNT(*) as overdue
                FROM tasks
                WHERE project_id = ? AND deleted_at IS NULL
                  AND status != 'concluida'
                  AND due_date IS NOT NULL
                  AND due_date < ?
            ");
            $stmtOverdue->execute([$projectId, $today]);
            $summary['overdue'] = (int) ($stmtOverdue->fetch()['overdue'] ?? 0);
        } catch (\PDOException $e) {
            $stmtOverdue = $db->prepare("
                SELECT COUNT(*) as overdue
                FROM tasks
                WHERE project_id = ? AND (deleted_at IS NULL OR deleted_at = '')
                  AND status != 'concluida'
                  AND due_date IS NOT NULL
                  AND due_date < ?
            ");
            $stmtOverdue->execute([$projectId, $today]);
            $summary['overdue'] = (int) ($stmtOverdue->fetch()['overdue'] ?? 0);
        }

        return $summary;
    }

    /**
     * Lista tarefas concluídas em um período (por data de conclusão completed_at)
     * Usado no relatório semanal de produtividade - evolução futura
     *
     * @param string $dataInicio Y-m-d (inclusive)
     * @param string $dataFim Y-m-d (inclusive)
     * @return array Lista de tarefas com project_name, tenant_name, completed_by_name
     */
    public static function getTasksCompletedInPeriod(string $dataInicio, string $dataFim): array
    {
        $db = DB::getConnection();
        $dataFimEnd = $dataFim . ' 23:59:59';

        try {
            $stmt = $db->prepare("
                SELECT t.id, t.title, t.completed_at, t.completed_by,
                       p.name as project_name,
                       t2.name as tenant_name,
                       completed_user.name as completed_by_name
                FROM tasks t
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants t2 ON p.tenant_id = t2.id
                LEFT JOIN users completed_user ON completed_user.id = t.completed_by
                WHERE t.status = 'concluida'
                AND t.completed_at IS NOT NULL
                AND t.completed_at >= ?
                AND t.completed_at <= ?
                AND t.deleted_at IS NULL
                ORDER BY t.completed_at ASC
            ");
            $stmt->execute([$dataInicio . ' 00:00:00', $dataFimEnd]);
        } catch (\PDOException $e) {
            $stmt = $db->prepare("
                SELECT t.id, t.title, t.completed_at, t.completed_by,
                       p.name as project_name,
                       t2.name as tenant_name,
                       completed_user.name as completed_by_name
                FROM tasks t
                INNER JOIN projects p ON t.project_id = p.id
                LEFT JOIN tenants t2 ON p.tenant_id = t2.id
                LEFT JOIN users completed_user ON completed_user.id = t.completed_by
                WHERE t.status = 'concluida'
                AND t.completed_at IS NOT NULL
                AND t.completed_at >= ?
                AND t.completed_at <= ?
                ORDER BY t.completed_at ASC
            ");
            $stmt->execute([$dataInicio . ' 00:00:00', $dataFimEnd]);
        }

        return $stmt->fetchAll();
    }

    /**
     * Exclui uma tarefa (soft delete)
     * Define deleted_at = NOW() sem remover o registro do banco
     * 
     * @param int $id ID da tarefa
     * @param int|null $projectId ID do projeto (opcional, para validação)
     * @return bool
     * @throws \RuntimeException Se a tarefa não for encontrada ou já estiver deletada
     */
    public static function deleteTask(int $id, ?int $projectId = null): bool
    {
        $db = DB::getConnection();
        
        // Verifica se a tarefa existe e não está deletada
        $task = self::findTask($id);
        if (!$task) {
            throw new \RuntimeException('Tarefa não encontrada ou já excluída');
        }
        
        // Valida se a tarefa pertence ao projeto (se projectId foi fornecido)
        if ($projectId !== null && (int) $task['project_id'] !== $projectId) {
            throw new \RuntimeException('Tarefa não pertence ao projeto especificado');
        }
        
        // Realiza soft delete (define deleted_at)
        $stmt = $db->prepare("
            UPDATE tasks 
            SET deleted_at = NOW(), updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL
        ");
        
        $stmt->execute([$id]);
        
        return $stmt->rowCount() > 0;
    }
}

