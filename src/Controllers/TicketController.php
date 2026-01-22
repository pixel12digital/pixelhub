<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\TicketService;
use PixelHub\Services\ProjectService;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar tickets de suporte
 * 
 * FLUXO DE NEGÓCIO - Tickets vs Projetos:
 * 
 * Tickets são a unidade de suporte pontual vinculada a um cliente.
 * - Cada ticket DEVE estar vinculado a um cliente (tenant_id obrigatório)
 * - project_id é OPCIONAL: usado apenas quando o chamado está claramente ligado a um projeto maior
 * - NÃO criar projetos genéricos para tickets: tickets podem existir sem projeto
 * 
 * Projetos = coisas grandes e recorrentes
 * Tickets = chamados pontuais de suporte vinculados ao cliente
 * 
 * Dentro do bloco de agenda SUPORTE, trabalhe nos tickets pendentes, não crie projetos novos.
 */
class TicketController extends Controller
{
    /**
     * Lista todos os tickets
     */
    public function index(): void
    {
        Auth::requireInternal();
        
        $filters = [
            'tenant_id' => isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int)$_GET['tenant_id'] : null,
            'project_id' => isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null,
            'status' => isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null,
            'prioridade' => isset($_GET['prioridade']) && $_GET['prioridade'] !== '' ? $_GET['prioridade'] : null,
        ];
        
        $tickets = TicketService::getAllTickets($filters);
        
        // Busca lista de tenants para filtro
        $db = DB::getConnection();
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca lista de projetos para filtro
        $projetos = ProjectService::getAllProjects(null, 'ativo');
        
        $this->view('tickets.index', [
            'tickets' => $tickets,
            'tenants' => $tenants,
            'projetos' => $projetos,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Exibe formulário de criação de ticket
     * 
     * Aceita tenant_id via GET para pré-selecionar o cliente
     * Aceita project_id via GET para pré-selecionar o projeto (opcional)
     */
    public function create(): void
    {
        Auth::requireInternal();
        
        // Busca lista de tenants
        $db = DB::getConnection();
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca lista de projetos
        $projetos = ProjectService::getAllProjects(null, 'ativo');
        
        // Pré-seleciona tenant_id se fornecido
        $selectedTenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int)$_GET['tenant_id'] : null;
        
        // Pré-seleciona project_id se fornecido
        $selectedProjectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
        
        // Se project_id foi fornecido, filtra projetos do tenant (se tenant_id também foi fornecido)
        if ($selectedProjectId && $selectedTenantId) {
            $projetos = ProjectService::getAllProjects($selectedTenantId, 'ativo');
        }
        
        $this->view('tickets.create', [
            'tenants' => $tenants,
            'projetos' => $projetos,
            'selectedTenantId' => $selectedTenantId,
            'selectedProjectId' => $selectedProjectId,
        ]);
    }
    
    /**
     * Cria um novo ticket
     */
    public function store(): void
    {
        Auth::requireInternal();
        
        try {
            $user = Auth::user();
            $data = $_POST;
            if ($user) {
                $data['created_by'] = $user['id'];
            }
            
            // Se foi criado a partir de uma tarefa, vincula após criar
            $taskId = isset($data['task_id']) && $data['task_id'] !== '' ? (int)$data['task_id'] : null;
            if ($taskId) {
                unset($data['task_id']); // Remove task_id dos dados do ticket
            }
            
            $id = TicketService::createTicket($data);
            
            // Se foi criado a partir de uma tarefa, vincula agora
            if ($taskId) {
                try {
                    TicketService::linkTaskToTicket($id, $taskId);
                } catch (\Exception $e) {
                    error_log("Erro ao vincular tarefa ao ticket: " . $e->getMessage());
                    // Não falha a criação do ticket, apenas loga o erro
                }
            }
            
            $this->json(['success' => true, 'id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao criar ticket: " . $e->getMessage());
            $this->json(['error' => 'Erro ao criar ticket'], 500);
        }
    }
    
    /**
     * Exibe detalhes de um ticket
     */
    public function show(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        $ticket = TicketService::findTicket($id);
        if (!$ticket) {
            $this->json(['error' => 'Ticket não encontrado'], 404);
            return;
        }
        
        // Busca blocos de agenda relacionados se o ticket tiver tarefa vinculada
        $blocosRelacionados = [];
        if (!empty($ticket['task_id'])) {
            try {
                $blocosRelacionados = \PixelHub\Services\AgendaService::getBlocksForTask((int)$ticket['task_id']);
            } catch (\Exception $e) {
                error_log("Erro ao buscar blocos relacionados ao ticket: " . $e->getMessage());
            }
        }
        
        // Busca tarefas abertas relacionadas (se o ticket estiver aberto)
        $openTasks = [];
        $hasOpenTasks = false;
        if (!TicketService::isClosed($id)) {
            $openTasks = TicketService::getOpenTasksForTicket($id);
            $hasOpenTasks = !empty($openTasks);
        }
        
        // Verifica se há erro de tarefas abertas (vindo do método close)
        $hasOpenTasksError = isset($_GET['has_open_tasks']) && $_GET['has_open_tasks'] === '1';
        
        $this->view('tickets.show', [
            'ticket' => $ticket,
            'blocosRelacionados' => $blocosRelacionados,
            'openTasks' => $openTasks,
            'hasOpenTasks' => $hasOpenTasks,
            'hasOpenTasksError' => $hasOpenTasksError,
        ]);
    }
    
    /**
     * Atualiza um ticket
     */
    public function update(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }
        
        try {
            TicketService::updateTicket($id, $_POST);
            
            // Sincroniza status da tarefa se ticket foi resolvido/cancelado
            TicketService::syncTaskFromTicketStatus($id);
            
            $this->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar ticket: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar ticket'], 500);
        }
    }
    
    /**
     * Cria uma tarefa a partir de um ticket
     */
    public function createTaskFromTicket(): void
    {
        Auth::requireInternal();
        
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        
        if ($ticketId <= 0) {
            $this->json(['error' => 'ID do ticket inválido'], 400);
            return;
        }
        
        try {
            $taskId = TicketService::createTaskFromTicket($ticketId);
            
            // Busca o ticket para pegar o project_id
            $ticket = TicketService::findTicket($ticketId);
            $projectId = $ticket['project_id'] ?? null;
            
            // Redireciona para o quadro de tarefas
            if ($projectId) {
                header('Location: ' . pixelhub_url('/projects/board?project_id=' . $projectId . '&task_id=' . $taskId . '&sucesso=' . urlencode('Tarefa criada com sucesso a partir do ticket')));
            } else {
                header('Location: ' . pixelhub_url('/projects/board?task_id=' . $taskId . '&sucesso=' . urlencode('Tarefa criada com sucesso a partir do ticket')));
            }
            exit;
        } catch (\RuntimeException $e) {
            error_log("Erro ao criar tarefa do ticket: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao criar tarefa do ticket: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode('Erro ao criar tarefa')));
            exit;
        }
    }
    
    /**
     * Exibe formulário de criação de ticket a partir de uma tarefa
     */
    public function createFromTask(): void
    {
        Auth::requireInternal();
        
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
        
        if ($taskId <= 0) {
            header('Location: ' . pixelhub_url('/tickets?erro=' . urlencode('ID da tarefa inválido')));
            exit;
        }
        
        // Busca a tarefa
        $task = \PixelHub\Services\TaskService::findTask($taskId);
        if (!$task) {
            header('Location: ' . pixelhub_url('/tickets?erro=' . urlencode('Tarefa não encontrada')));
            exit;
        }
        
        // Busca o projeto da tarefa para pegar tenant_id
        $project = null;
        $tenantId = null;
        if (!empty($task['project_id'])) {
            $project = ProjectService::findProject((int)$task['project_id']);
            if ($project) {
                $tenantId = $project['tenant_id'] ?? null;
            }
        }
        
        // Busca lista de tenants
        $db = DB::getConnection();
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca lista de projetos
        $projetos = ProjectService::getAllProjects(null, 'ativo');
        
        // Prepara dados sugeridos
        $suggestedTitle = '[Suporte] ' . $task['title'];
        $suggestedDescription = "Ticket criado a partir da tarefa:\n\n" . ($task['description'] ?? 'Sem descrição');
        
        $this->view('tickets.create', [
            'tenants' => $tenants,
            'projetos' => $projetos,
            'selectedTenantId' => $tenantId,
            'selectedProjectId' => $task['project_id'] ?? null,
            'task' => $task,
            'suggestedTitle' => $suggestedTitle,
            'suggestedDescription' => $suggestedDescription,
            'isFromTask' => true,
        ]);
    }
    
    /**
     * Exibe formulário de edição de ticket
     */
    public function edit(): void
    {
        Auth::requireInternal();
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($id <= 0) {
            header('Location: ' . pixelhub_url('/tickets'));
            exit;
        }
        
        $ticket = TicketService::findTicket($id);
        if (!$ticket) {
            header('Location: ' . pixelhub_url('/tickets'));
            exit;
        }
        
        // Busca lista de tenants
        $db = DB::getConnection();
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca lista de projetos
        $projetos = ProjectService::getAllProjects(null, 'ativo');
        
        $this->view('tickets.create', [
            'ticket' => $ticket,
            'tenants' => $tenants,
            'projetos' => $projetos,
            'selectedTenantId' => $ticket['tenant_id'],
            'selectedProjectId' => $ticket['project_id'],
            'isEdit' => true,
        ]);
    }
    
    /**
     * Encerra um ticket
     */
    public function close(): void
    {
        Auth::requireInternal();
        
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $id . '&erro=' . urlencode('ID inválido')));
            exit;
        }
        
        try {
            $closingFeedback = trim($_POST['closing_feedback'] ?? '');
            $forceClose = isset($_POST['force_close']) && $_POST['force_close'] === '1';
            
            // Valida feedback mínimo (opcional, mas recomendado)
            if (empty($closingFeedback) || strlen($closingFeedback) < 5) {
                // Não bloqueia, mas pode avisar o usuário
                // Por enquanto, apenas loga
                error_log("Ticket {$id} sendo encerrado sem feedback ou com feedback muito curto");
            }
            
            $result = TicketService::closeTicket($id, $closingFeedback, null, $forceClose);
            
            if ($result['success']) {
                header('Location: ' . pixelhub_url('/tickets/show?id=' . $id . '&sucesso=' . urlencode('Ticket encerrado com sucesso')));
            } else {
                // Há tarefas abertas e não foi forçado
                // Redireciona de volta para a view com informações das tarefas
                header('Location: ' . pixelhub_url('/tickets/show?id=' . $id . '&erro=' . urlencode($result['message']) . '&has_open_tasks=1'));
            }
            exit;
            
        } catch (\InvalidArgumentException $e) {
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $id . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $id . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao encerrar ticket: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $id . '&erro=' . urlencode('Erro ao encerrar ticket')));
            exit;
        }
    }
    
    /**
     * Adiciona uma nota/ocorrência a um ticket
     */
    public function addNote(): void
    {
        Auth::requireInternal();
        
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $note = trim($_POST['note'] ?? '');
        
        if ($ticketId <= 0) {
            $this->json(['error' => 'ID do ticket inválido'], 400);
            return;
        }
        
        if (empty($note)) {
            $this->json(['error' => 'A nota não pode estar vazia'], 400);
            return;
        }
        
        try {
            $noteId = TicketService::addNote($ticketId, $note);
            $this->json(['success' => true, 'id' => $noteId]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao adicionar nota ao ticket: " . $e->getMessage());
            $this->json(['error' => 'Erro ao adicionar nota'], 500);
        }
    }
}


