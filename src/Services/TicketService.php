<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar tickets de suporte
 * 
 * FLUXO DE NEGÓCIO - Tickets vs Projetos:
 * 
 * - Tickets são a unidade de suporte pontual vinculada a um cliente
 * - Cada ticket DEVE estar vinculado a um cliente (tenant_id obrigatório)
 * - project_id é OPCIONAL: usado apenas quando o chamado está claramente ligado a um projeto maior
 * - NÃO criar projetos genéricos para tickets: tickets podem existir sem projeto
 * - Projetos = coisas grandes e recorrentes
 * - Tickets = chamados pontuais de suporte vinculados ao cliente
 * 
 * Para integração com Agenda (bloco SUPORTE):
 * - Use findOpenTickets() para buscar tickets pendentes
 * - Status abertos: 'aberto', 'em_atendimento', 'aguardando_cliente'
 */
class TicketService
{
    /**
     * Lista todos os tickets com filtros opcionais
     * 
     * @param array $filters Filtros (tenant_id, project_id, status, prioridade)
     * @return array Lista de tickets
     */
    public static function getAllTickets(array $filters = []): array
    {
        $db = DB::getConnection();
        
        $sql = "
            SELECT 
                t.*,
                tn.name as tenant_name,
                p.name as project_name,
                u.name as created_by_name,
                closed_by_user.name as closed_by_name,
                task.title as task_title,
                task.status as task_status
            FROM tickets t
            LEFT JOIN tenants tn ON t.tenant_id = tn.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.created_by = u.id
            LEFT JOIN users closed_by_user ON t.closed_by_user_id = closed_by_user.id
            LEFT JOIN tasks task ON t.task_id = task.id
            WHERE 1=1
        ";
        
        $params = [];
        
        if (!empty($filters['tenant_id'])) {
            $sql .= " AND t.tenant_id = ?";
            $params[] = (int)$filters['tenant_id'];
        }
        
        if (!empty($filters['project_id'])) {
            $sql .= " AND t.project_id = ?";
            $params[] = (int)$filters['project_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['prioridade'])) {
            $sql .= " AND t.prioridade = ?";
            $params[] = $filters['prioridade'];
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Busca um ticket por ID
     * 
     * @param int $id ID do ticket
     * @return array|null Dados do ticket ou null se não encontrado
     */
    public static function findTicket(int $id): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                t.*,
                tn.name as tenant_name,
                p.name as project_name,
                u.name as created_by_name,
                closed_by_user.name as closed_by_name,
                task.title as task_title,
                task.status as task_status
            FROM tickets t
            LEFT JOIN tenants tn ON t.tenant_id = tn.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.created_by = u.id
            LEFT JOIN users closed_by_user ON t.closed_by_user_id = closed_by_user.id
            LEFT JOIN tasks task ON t.task_id = task.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Cria um novo ticket e automaticamente cria tarefa e vincula a bloco
     * 
     * @param array $data Dados do ticket
     * @return int ID do ticket criado
     */
    public static function createTicket(array $data): int
    {
        $db = DB::getConnection();
        
        // Validações
        $titulo = trim($data['titulo'] ?? '');
        if (empty($titulo)) {
            throw new \InvalidArgumentException('Título do ticket é obrigatório');
        }
        
        if (strlen($titulo) > 200) {
            throw new \InvalidArgumentException('Título do ticket deve ter no máximo 200 caracteres');
        }
        
        // Valida prioridade
        $allowedPriorities = ['baixa', 'media', 'alta', 'critica'];
        $prioridade = trim($data['prioridade'] ?? 'media');
        if (!in_array($prioridade, $allowedPriorities)) {
            $prioridade = 'media';
        }
        
        // Valida status (incluindo 'cancelado')
        $allowedStatuses = ['aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'cancelado'];
        $status = trim($data['status'] ?? 'aberto');
        if (!in_array($status, $allowedStatuses)) {
            $status = 'aberto';
        }
        
        // Valida origem
        $allowedOrigens = ['cliente', 'interno', 'whatsapp', 'automatico'];
        $origem = trim($data['origem'] ?? 'cliente');
        if (!in_array($origem, $allowedOrigens)) {
            $origem = 'cliente';
        }
        
        // Valida tenant_id (OBRIGATÓRIO)
        if (empty($data['tenant_id'])) {
            throw new \InvalidArgumentException('Cliente (tenant_id) é obrigatório para criar um ticket');
        }
        $tenantId = (int)$data['tenant_id'];
        
        // project_id é OPCIONAL (ticket pode existir sem projeto)
        $projectId = !empty($data['project_id']) ? (int)$data['project_id'] : null;
        $descricao = trim($data['descricao'] ?? '') ?: null;
        $createdBy = !empty($data['created_by']) ? (int)$data['created_by'] : null;
        $prazoSla = !empty($data['prazo_sla']) ? $data['prazo_sla'] : null;
        
        // Inicia transação
        $db->beginTransaction();
        
        try {
            // Cria o ticket
            $stmt = $db->prepare("
                INSERT INTO tickets 
                (tenant_id, project_id, titulo, descricao, prioridade, status, origem, prazo_sla, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $tenantId,
                $projectId,
                $titulo,
                $descricao,
                $prioridade,
                $status,
                $origem,
                $prazoSla,
                $createdBy,
            ]);
            
            $ticketId = (int)$db->lastInsertId();
            
            // NOTA: Removida criação automática de tarefa/projeto genérico
            // Tickets podem existir sem projeto. Se necessário criar tarefa, deve ser feito manualmente
            // ou quando houver um project_id específico vinculado ao ticket.
            
            $db->commit();
            return $ticketId;
            
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Busca tickets abertos (para integração com Agenda - bloco SUPORTE)
     * 
     * Retorna tickets com status: 'aberto', 'em_atendimento', 'aguardando_cliente'
     * 
     * @param array $filters Filtros opcionais (tenant_id, prioridade)
     * @return array Lista de tickets abertos
     */
    public static function findOpenTickets(array $filters = []): array
    {
        $db = DB::getConnection();
        
        $sql = "
            SELECT 
                t.*,
                tn.name as tenant_name,
                p.name as project_name,
                u.name as created_by_name
            FROM tickets t
            INNER JOIN tenants tn ON t.tenant_id = tn.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.status IN ('aberto', 'em_atendimento', 'aguardando_cliente')
        ";
        
        $params = [];
        
        if (!empty($filters['tenant_id'])) {
            $sql .= " AND t.tenant_id = ?";
            $params[] = (int)$filters['tenant_id'];
        }
        
        if (!empty($filters['prioridade'])) {
            $sql .= " AND t.prioridade = ?";
            $params[] = $filters['prioridade'];
        }
        
        $sql .= " ORDER BY 
            CASE t.prioridade 
                WHEN 'critica' THEN 1 
                WHEN 'alta' THEN 2 
                WHEN 'media' THEN 3 
                WHEN 'baixa' THEN 4 
            END,
            t.created_at ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Atualiza um ticket existente
     * 
     * @param int $id ID do ticket
     * @param array $data Dados para atualizar
     * @return bool Sucesso da operação
     */
    public static function updateTicket(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o ticket existe
        $ticket = self::findTicket($id);
        if (!$ticket) {
            throw new \RuntimeException('Ticket não encontrado');
        }
        
        // Validações
        $titulo = isset($data['titulo']) ? trim($data['titulo']) : $ticket['titulo'];
        if (empty($titulo)) {
            throw new \InvalidArgumentException('Título do ticket é obrigatório');
        }
        
        // Valida prioridade
        $allowedPriorities = ['baixa', 'media', 'alta', 'critica'];
        $prioridade = isset($data['prioridade']) ? trim($data['prioridade']) : $ticket['prioridade'];
        if (!in_array($prioridade, $allowedPriorities)) {
            $prioridade = $ticket['prioridade'];
        }
        
        // Valida status (incluindo 'cancelado')
        $allowedStatuses = ['aberto', 'em_atendimento', 'aguardando_cliente', 'resolvido', 'cancelado'];
        $status = isset($data['status']) ? trim($data['status']) : $ticket['status'];
        if (!in_array($status, $allowedStatuses)) {
            $status = $ticket['status'];
        }
        
        // Valida se há tarefas abertas antes de resolver o ticket
        $oldStatus = $ticket['status'];
        if (in_array($status, ['resolvido', 'cancelado']) && !in_array($oldStatus, ['resolvido', 'cancelado'])) {
            $openTasks = self::getOpenTasksForTicket($id);
            if (!empty($openTasks)) {
                throw new \InvalidArgumentException('Não é possível resolver o ticket. Existem tarefas relacionadas que ainda não foram concluídas. Por favor, conclua todas as tarefas antes de resolver o ticket.');
            }
        }
        
        // Valida origem
        $allowedOrigens = ['cliente', 'interno', 'whatsapp', 'automatico'];
        $origem = isset($data['origem']) ? trim($data['origem']) : $ticket['origem'];
        if (!in_array($origem, $allowedOrigens)) {
            $origem = $ticket['origem'];
        }
        
        // Processa dados
        $descricao = isset($data['descricao']) ? (trim($data['descricao']) ?: null) : $ticket['descricao'];
        $prazoSla = isset($data['prazo_sla']) ? ($data['prazo_sla'] ?: null) : $ticket['prazo_sla'];
        
        // Processa project_id: se vier vazio ou como string vazia, define como NULL
        $projectId = null;
        if (isset($data['project_id'])) {
            $projectIdValue = trim($data['project_id']);
            $projectId = !empty($projectIdValue) ? (int)$projectIdValue : null;
        } else {
            $projectId = $ticket['project_id'];
        }
        
        // Se status mudou para resolvido ou cancelado, preenche data_resolucao
        $dataResolucao = $ticket['data_resolucao'];
        if (in_array($status, ['resolvido', 'cancelado']) && empty($dataResolucao)) {
            $dataResolucao = date('Y-m-d H:i:s');
        } elseif (!in_array($status, ['resolvido', 'cancelado'])) {
            $dataResolucao = null;
        }
        
        // Atualiza no banco
        $stmt = $db->prepare("
            UPDATE tickets 
            SET titulo = ?, descricao = ?, prioridade = ?, status = ?, origem = ?, project_id = ?, prazo_sla = ?, data_resolucao = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $titulo,
            $descricao,
            $prioridade,
            $status,
            $origem,
            $projectId,
            $prazoSla,
            $dataResolucao,
            $id,
        ]);
        
        // Arquivamento de projetos é manual (filtro Status na tela de projetos)
        
        return true;
    }
    
    /**
     * Vincula uma tarefa existente a um ticket
     * 
     * @param int $ticketId ID do ticket
     * @param int $taskId ID da tarefa
     * @return void
     * @throws \RuntimeException Se ticket ou tarefa não existirem
     */
    public static function linkTaskToTicket(int $ticketId, int $taskId): void
    {
        $db = DB::getConnection();
        
        // Valida se o ticket existe
        $ticket = self::findTicket($ticketId);
        if (!$ticket) {
            throw new \RuntimeException('Ticket não encontrado');
        }
        
        // Valida se a tarefa existe
        $task = \PixelHub\Services\TaskService::findTask($taskId);
        if (!$task) {
            throw new \RuntimeException('Tarefa não encontrada');
        }
        
        // Atualiza o ticket com o task_id
        $stmt = $db->prepare("UPDATE tickets SET task_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$taskId, $ticketId]);
        
        // Garante que a tarefa tenha task_type = 'client_ticket'
        $currentTaskType = $task['task_type'] ?? 'internal';
        if ($currentTaskType !== 'client_ticket') {
            \PixelHub\Services\TaskService::updateTask($taskId, ['task_type' => 'client_ticket']);
        }
    }
    
    /**
     * Cria uma tarefa a partir de um ticket
     * 
     * Se o ticket já tiver task_id, retorna esse ID.
     * Caso contrário, cria uma nova tarefa vinculada ao ticket.
     * 
     * @param int $ticketId ID do ticket
     * @return int ID da tarefa criada ou existente
     * @throws \RuntimeException Se ticket não existir ou não tiver project_id
     */
    public static function createTaskFromTicket(int $ticketId): int
    {
        $db = DB::getConnection();
        
        // Busca o ticket
        $ticket = self::findTicket($ticketId);
        if (!$ticket) {
            throw new \RuntimeException('Ticket não encontrado');
        }
        
        // Se o ticket já tiver task_id, retorna esse ID
        if (!empty($ticket['task_id'])) {
            return (int)$ticket['task_id'];
        }
        
        // Se o ticket não tiver project_id, cria/busca projeto genérico de "Suporte" para o cliente
        if (empty($ticket['project_id'])) {
            $tenantId = (int)$ticket['tenant_id'];
            if (empty($tenantId)) {
                throw new \RuntimeException('Ticket precisa estar vinculado a um cliente para criar tarefa.');
            }
            
            // Busca ou cria projeto genérico de "Suporte" para este cliente
            $projectId = self::getOrCreateSupportProject($tenantId);
            
            // Atualiza o ticket com o project_id encontrado/criado
            $stmt = $db->prepare("UPDATE tickets SET project_id = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$projectId, $ticketId]);
        } else {
            $projectId = (int)$ticket['project_id'];
        }
        
        // Prepara dados da tarefa
        $taskTitle = '[Ticket #' . $ticketId . '] ' . $ticket['titulo'];
        // Limita título a 200 caracteres (limite da tabela tasks)
        if (strlen($taskTitle) > 200) {
            $taskTitle = substr($taskTitle, 0, 197) . '...';
        }
        
        $taskData = [
            'project_id' => $projectId,
            'title' => $taskTitle,
            'description' => $ticket['descricao'] ?? null,
            'status' => 'em_andamento', // Status inicial para tarefas de ticket
            'task_type' => 'client_ticket',
        ];
        
        // Adiciona created_by se disponível
        if (!empty($ticket['created_by'])) {
            $taskData['created_by'] = (int)$ticket['created_by'];
        }
        
        // Cria a tarefa
        $taskId = \PixelHub\Services\TaskService::createTask($taskData);
        
        // Vincula a tarefa ao ticket
        self::linkTaskToTicket($ticketId, $taskId);
        
        return $taskId;
    }
    
    /**
     * Busca tickets vinculados a uma tarefa (via task_id)
     * 
     * @param int $taskId ID da tarefa
     * @return array Lista de tickets vinculados (normalmente apenas um)
     */
    public static function findTicketsByTaskId(int $taskId): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                t.*,
                tn.name as tenant_name,
                p.name as project_name,
                u.name as created_by_name
            FROM tickets t
            LEFT JOIN tenants tn ON t.tenant_id = tn.id
            LEFT JOIN projects p ON t.project_id = p.id
            LEFT JOIN users u ON t.created_by = u.id
            WHERE t.task_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$taskId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Marca ticket como resolvido quando tarefa vinculada é concluída
     * 
     * @param int $taskId ID da tarefa que foi concluída
     * @return void
     */
    public static function markTicketResolvedFromTask(int $taskId): void
    {
        $db = DB::getConnection();
        
        // Busca tickets vinculados a essa tarefa
        $tickets = self::findTicketsByTaskId($taskId);
        
        foreach ($tickets as $ticket) {
            // Se o ticket estiver em status aberto, atualiza para resolvido
            $status = $ticket['status'];
            if (in_array($status, ['aberto', 'em_atendimento', 'aguardando_cliente'])) {
                self::updateTicket((int)$ticket['id'], [
                    'status' => 'resolvido'
                ]);
            }
        }
    }
    
    /**
     * Sincroniza status da tarefa quando ticket é resolvido/cancelado
     * 
     * @param int $ticketId ID do ticket
     * @return void
     */
    public static function syncTaskFromTicketStatus(int $ticketId): void
    {
        $ticket = self::findTicket($ticketId);
        if (!$ticket || empty($ticket['task_id'])) {
            return; // Ticket não tem tarefa vinculada
        }
        
        $taskId = (int)$ticket['task_id'];
        $ticketStatus = $ticket['status'];
        
        // Se ticket foi resolvido ou cancelado, marca tarefa como concluída
        if (in_array($ticketStatus, ['resolvido', 'cancelado'])) {
            $task = \PixelHub\Services\TaskService::findTask($taskId);
            if ($task && $task['status'] !== 'concluida') {
                \PixelHub\Services\TaskService::updateTask($taskId, [
                    'status' => 'concluida'
                ]);
            }
        }
    }
    
    /**
     * Busca anexos de um ticket (via tarefa vinculada)
     * 
     * @param int $ticketId ID do ticket
     * @return array Lista de anexos (vazia se não houver tarefa vinculada)
     */
    public static function getAttachmentsForTicket(int $ticketId): array
    {
        $ticket = self::findTicket($ticketId);
        if (!$ticket || empty($ticket['task_id'])) {
            return []; // Ticket não tem tarefa vinculada
        }
        
        $taskId = (int)$ticket['task_id'];
        $db = DB::getConnection();
        
        // Busca anexos da tarefa
        try {
            $stmt = $db->prepare("
                SELECT 
                    ta.*,
                    u.name as uploaded_by_name
                FROM task_attachments ta
                LEFT JOIN users u ON ta.uploaded_by = u.id
                WHERE ta.task_id = ?
                ORDER BY ta.uploaded_at DESC
            ");
            $stmt->execute([$taskId]);
            $attachments = $stmt->fetchAll();
            
            // Adiciona informações de existência do arquivo e URL de download
            $basePath = defined('BASE_PATH') ? BASE_PATH : '';
            foreach ($attachments as &$attachment) {
                if (!empty($attachment['file_path'])) {
                    $attachment['file_exists'] = \PixelHub\Core\Storage::fileExists($attachment['file_path']);
                } else {
                    $attachment['file_exists'] = false;
                }
                
                if (!empty($attachment['id']) && $attachment['file_exists']) {
                    $attachment['download_url'] = $basePath . '/tasks/attachments/download?id=' . $attachment['id'];
                } else {
                    $attachment['download_url'] = null;
                }
            }
            unset($attachment);
            
            return $attachments;
        } catch (\Exception $e) {
            error_log("Erro ao buscar anexos do ticket: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca ou cria um projeto genérico de "Suporte" para um cliente
     * 
     * Este método é usado quando um ticket sem projeto precisa criar uma tarefa.
     * Cria automaticamente um projeto genérico de suporte para o cliente.
     * 
     * @param int $tenantId ID do cliente
     * @return int ID do projeto de suporte (criado ou existente)
     */
    private static function getOrCreateSupportProject(int $tenantId): int
    {
        $db = DB::getConnection();
        
        // Busca se já existe um projeto "Suporte" para este cliente
        $stmt = $db->prepare("
            SELECT id 
            FROM projects 
            WHERE tenant_id = ? 
            AND name = 'Suporte' 
            AND status = 'ativo'
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $project = $stmt->fetch();
        
        if ($project) {
            return (int)$project['id'];
        }
        
        // Se não existe, cria um novo projeto genérico de "Suporte"
        $user = \PixelHub\Core\Auth::user();
        $createdBy = $user ? (int)$user['id'] : null;
        
        $projectData = [
            'tenant_id' => $tenantId,
            'name' => 'Suporte',
            'description' => 'Projeto genérico para tickets de suporte e chamados pontuais',
            'status' => 'ativo',
            'priority' => 'media',
            'type' => 'cliente',
            'is_customer_visible' => 0, // Projeto interno de suporte
            'created_by' => $createdBy,
        ];
        
        return \PixelHub\Services\ProjectService::createProject($projectData);
    }
    
    /**
     * Busca tarefas relacionadas a um ticket que não estão concluídas
     * 
     * Busca tarefas do projeto vinculado ao ticket (se houver) que não estão concluídas.
     * Também verifica a tarefa diretamente vinculada ao ticket (task_id).
     * 
     * @param int $ticketId ID do ticket
     * @return array Lista de tarefas abertas relacionadas
     */
    public static function getOpenTasksForTicket(int $ticketId): array
    {
        $ticket = self::findTicket($ticketId);
        if (!$ticket) {
            return [];
        }
        
        $db = DB::getConnection();
        $openTasks = [];
        
        // Busca tarefas do projeto vinculado ao ticket que não estão concluídas
        if (!empty($ticket['project_id'])) {
            $projectId = (int)$ticket['project_id'];
            
            // Tenta com deleted_at primeiro
            try {
                $stmt = $db->prepare("
                    SELECT t.*, p.name as project_name
                    FROM tasks t
                    INNER JOIN projects p ON t.project_id = p.id
                    WHERE t.project_id = ? 
                    AND t.status != 'concluida'
                    AND t.deleted_at IS NULL
                    ORDER BY t.created_at ASC
                ");
                $stmt->execute([$projectId]);
            } catch (\PDOException $e) {
                // Se deu erro, tenta sem deleted_at
                $stmt = $db->prepare("
                    SELECT t.*, p.name as project_name
                    FROM tasks t
                    INNER JOIN projects p ON t.project_id = p.id
                    WHERE t.project_id = ? 
                    AND t.status != 'concluida'
                    ORDER BY t.created_at ASC
                ");
                $stmt->execute([$projectId]);
            }
            
            $projectTasks = $stmt->fetchAll();
            foreach ($projectTasks as $task) {
                $openTasks[] = $task;
            }
        }
        
        // Remove duplicatas (caso a tarefa vinculada diretamente também esteja no projeto)
        $seenIds = [];
        $uniqueTasks = [];
        foreach ($openTasks as $task) {
            if (!in_array($task['id'], $seenIds)) {
                $seenIds[] = $task['id'];
                $uniqueTasks[] = $task;
            }
        }
        
        return $uniqueTasks;
    }
    
    /**
     * Verifica se um ticket possui tarefas relacionadas em aberto
     * 
     * @param int $ticketId ID do ticket
     * @return bool True se houver tarefas abertas, false caso contrário
     */
    public static function hasOpenTasks(int $ticketId): bool
    {
        $openTasks = self::getOpenTasksForTicket($ticketId);
        return !empty($openTasks);
    }
    
    /**
     * Encerra um ticket com feedback
     * 
     * @param int $ticketId ID do ticket
     * @param string $closingFeedback Feedback de encerramento
     * @param int|null $closedByUserId ID do usuário que está encerrando (null = usuário logado)
     * @param bool $forceClose Se true, conclui automaticamente todas as tarefas relacionadas em aberto
     * @return array ['success' => bool, 'message' => string, 'openTasks' => array]
     */
    public static function closeTicket(int $ticketId, string $closingFeedback = '', ?int $closedByUserId = null, bool $forceClose = false): array
    {
        $db = DB::getConnection();
        
        // Busca o ticket
        $ticket = self::findTicket($ticketId);
        if (!$ticket) {
            throw new \RuntimeException('Ticket não encontrado');
        }
        
        // Verifica se já está fechado
        if (in_array($ticket['status'], ['resolvido', 'cancelado'])) {
            throw new \InvalidArgumentException('Ticket já está encerrado');
        }
        
        // Busca tarefas abertas relacionadas
        $openTasks = self::getOpenTasksForTicket($ticketId);
        
        // Se há tarefas abertas e não foi forçado o fechamento, retorna erro
        if (!empty($openTasks) && !$forceClose) {
            return [
                'success' => false,
                'message' => 'Este ticket ainda possui tarefas em aberto. Deseja concluir essas tarefas e encerrar o ticket mesmo assim, ou prefere revisar no Kanban?',
                'openTasks' => $openTasks,
            ];
        }
        
        // Obtém ID do usuário que está encerrando
        if ($closedByUserId === null) {
            $user = \PixelHub\Core\Auth::user();
            $closedByUserId = $user ? (int)$user['id'] : null;
        }
        
        // Inicia transação
        $db->beginTransaction();
        
        try {
            // Se forceClose está ativo, conclui todas as tarefas relacionadas
            if ($forceClose && !empty($openTasks)) {
                foreach ($openTasks as $task) {
                    $taskId = (int)$task['id'];
                    \PixelHub\Services\TaskService::updateTask($taskId, [
                        'status' => 'concluida'
                    ]);
                }
            }
            
            // Atualiza o ticket
            $stmt = $db->prepare("
                UPDATE tickets 
                SET status = 'resolvido',
                    closed_at = NOW(),
                    closed_by_user_id = ?,
                    closing_feedback = ?,
                    data_resolucao = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $closedByUserId,
                trim($closingFeedback) ?: null,
                $ticketId,
            ]);
            
            $db->commit();
            
            return [
                'success' => true,
                'message' => 'Ticket encerrado com sucesso',
                'openTasks' => [],
            ];
            
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Verifica se um ticket está fechado
     * 
     * @param int $ticketId ID do ticket
     * @return bool True se o ticket estiver fechado (resolvido ou cancelado)
     */
    public static function isClosed(int $ticketId): bool
    {
        $ticket = self::findTicket($ticketId);
        if (!$ticket) {
            return false;
        }
        
        return in_array($ticket['status'], ['resolvido', 'cancelado']);
    }
    
    /**
     * Adiciona uma nota/ocorrência a um ticket
     * 
     * @param int $ticketId ID do ticket
     * @param string $note Texto da nota
     * @param int|null $createdBy ID do usuário que está criando a nota (null = usuário logado)
     * @return int ID da nota criada
     * @throws \RuntimeException Se ticket não existir
     */
    public static function addNote(int $ticketId, string $note, ?int $createdBy = null): int
    {
        $db = DB::getConnection();
        
        // Verifica se o ticket existe
        $ticket = self::findTicket($ticketId);
        if (!$ticket) {
            throw new \RuntimeException('Ticket não encontrado');
        }
        
        // Valida a nota
        $note = trim($note);
        if (empty($note)) {
            throw new \InvalidArgumentException('A nota não pode estar vazia');
        }
        
        // Obtém ID do usuário se não fornecido
        if ($createdBy === null) {
            $user = \PixelHub\Core\Auth::user();
            $createdBy = $user ? (int)$user['id'] : null;
        }
        
        // Insere a nota
        $stmt = $db->prepare("
            INSERT INTO ticket_notes (ticket_id, note, created_by, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$ticketId, $note, $createdBy]);
        
        return (int)$db->lastInsertId();
    }
    
    /**
     * Busca todas as notas de um ticket
     * 
     * @param int $ticketId ID do ticket
     * @return array Lista de notas ordenadas por data (mais recente primeiro)
     */
    public static function getNotes(int $ticketId): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                tn.*,
                u.name as created_by_name
            FROM ticket_notes tn
            LEFT JOIN users u ON tn.created_by = u.id
            WHERE tn.ticket_id = ?
            ORDER BY tn.created_at DESC
        ");
        $stmt->execute([$ticketId]);
        
        return $stmt->fetchAll();
    }
}

