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
     * Lista tickets abertos de um cliente específico (JSON para dropdown da agenda)
     */
    public function listByTenant(): void
    {
        Auth::requireInternal();
        
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int)$_GET['tenant_id'] : 0;
        
        if ($tenantId <= 0) {
            $this->json(['success' => false, 'error' => 'ID do cliente inválido'], 400);
            return;
        }
        
        try {
            // Busca apenas tickets abertos (não resolvidos nem cancelados)
            $filters = [
                'tenant_id' => $tenantId,
            ];
            
            $allTickets = TicketService::getAllTickets($filters);
            
            // Filtra apenas tickets abertos (status: aberto, em_atendimento, aguardando_cliente)
            $openTickets = array_filter($allTickets, function($ticket) {
                $status = $ticket['status'] ?? '';
                return in_array($status, ['aberto', 'em_atendimento', 'aguardando_cliente']);
            });
            
            // Formata para o dropdown (apenas campos necessários)
            $tickets = array_map(function($ticket) {
                return [
                    'id' => (int)$ticket['id'],
                    'titulo' => $ticket['titulo'],
                    'status' => $ticket['status'],
                    'prioridade' => $ticket['prioridade'],
                ];
            }, array_values($openTickets));
            
            $this->json([
                'success' => true,
                'tickets' => $tickets,
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao listar tickets por tenant: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao buscar tickets'], 500);
        }
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
    
    /**
     * Marca um ticket como faturável
     */
    public function markBillable(): void
    {
        Auth::requireInternal();
        
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        
        if ($ticketId <= 0) {
            header('Location: ' . pixelhub_url('/tickets?erro=' . urlencode('ID do ticket inválido')));
            exit;
        }
        
        try {
            $billingData = [
                'billed_value' => $_POST['billed_value'] ?? null,
                'service_id' => !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null,
                'billing_due_date' => $_POST['billing_due_date'] ?? null,
                'billing_notes' => $_POST['billing_notes'] ?? null,
            ];
            
            TicketService::markAsBillable($ticketId, $billingData);
            
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&sucesso=' . urlencode('Ticket marcado como faturável com sucesso')));
            exit;
            
        } catch (\InvalidArgumentException $e) {
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao marcar ticket como faturável: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode('Erro ao marcar ticket como faturável')));
            exit;
        }
    }
    
    /**
     * Gera cobrança no Asaas para um ticket faturável
     */
    public function generateBilling(): void
    {
        Auth::requireInternal();
        
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        
        if ($ticketId <= 0) {
            header('Location: ' . pixelhub_url('/tickets?erro=' . urlencode('ID do ticket inválido')));
            exit;
        }
        
        try {
            $options = [
                'billing_type' => $_POST['billing_type'] ?? 'BOLETO',
                'description' => !empty($_POST['description']) ? trim($_POST['description']) : null,
                'interest_value' => !empty($_POST['interest_value']) ? $_POST['interest_value'] : null,
                'fine_value' => !empty($_POST['fine_value']) ? $_POST['fine_value'] : null,
                'discount_value' => !empty($_POST['discount_value']) ? $_POST['discount_value'] : null,
                'discount_type' => !empty($_POST['discount_type']) ? $_POST['discount_type'] : 'FIXED',
                'discount_days_before_due' => !empty($_POST['discount_days_before_due']) ? $_POST['discount_days_before_due'] : null,
                'installment_count' => !empty($_POST['installment_count']) ? (int)$_POST['installment_count'] : 1,
            ];
            
            $result = TicketService::generateBilling($ticketId, $options);
            
            if ($result['success']) {
                $message = 'Cobrança gerada com sucesso no Asaas!';
                if (!empty($result['invoice_url'])) {
                    $message .= ' Link: ' . $result['invoice_url'];
                }
                header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&sucesso=' . urlencode($message)));
            } else {
                header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode($result['message'] ?? 'Erro ao gerar cobrança')));
            }
            exit;
            
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao gerar cobrança do ticket: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode('Erro ao gerar cobrança. Verifique se o cliente possui CPF/CNPJ cadastrado.')));
            exit;
        }
    }
    
    /**
     * Cancela cobrança de um ticket
     */
    public function cancelBilling(): void
    {
        Auth::requireInternal();
        
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        
        if ($ticketId <= 0) {
            header('Location: ' . pixelhub_url('/tickets?erro=' . urlencode('ID do ticket inválido')));
            exit;
        }
        
        try {
            TicketService::cancelBilling($ticketId, 'Cancelado manualmente pelo usuário');
            
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&sucesso=' . urlencode('Cobrança cancelada com sucesso')));
            exit;
            
        } catch (\RuntimeException $e) {
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode($e->getMessage())));
            exit;
        } catch (\Exception $e) {
            error_log("Erro ao cancelar cobrança do ticket: " . $e->getMessage());
            header('Location: ' . pixelhub_url('/tickets/show?id=' . $ticketId . '&erro=' . urlencode('Erro ao cancelar cobrança')));
            exit;
        }
    }
    
    /**
     * Alterna status faturável do ticket (via AJAX)
     */
    public function toggleBillable(): void
    {
        Auth::requireInternal();
        
        $ticketId = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0;
        $isBillable = isset($_POST['is_billable']) && $_POST['is_billable'] === '1';
        
        if ($ticketId <= 0) {
            $this->json(['error' => 'ID do ticket inválido'], 400);
            return;
        }
        
        try {
            $db = DB::getConnection();
            
            if ($isBillable) {
                // Marcar como faturável com status pending
                $stmt = $db->prepare("
                    UPDATE tickets 
                    SET is_billable = 1, 
                        billing_status = 'pending',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$ticketId]);
                
                $this->json(['success' => true, 'message' => 'Ticket marcado como faturável']);
            } else {
                // Verifica se já tem cobrança gerada
                $ticket = TicketService::findTicket($ticketId);
                if (!empty($ticket['billing_invoice_id'])) {
                    $this->json(['error' => 'Não é possível desmarcar. Cobrança já foi gerada.'], 400);
                    return;
                }
                
                // Desmarcar como faturável e limpar dados
                $stmt = $db->prepare("
                    UPDATE tickets 
                    SET is_billable = 0, 
                        billing_status = NULL,
                        billed_value = NULL,
                        billing_due_date = NULL,
                        billing_notes = NULL,
                        service_id = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$ticketId]);
                
                $this->json(['success' => true, 'message' => 'Ticket desmarcado como faturável']);
            }
            
        } catch (\Exception $e) {
            error_log("Erro ao alternar faturável: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar ticket'], 500);
        }
    }
}


