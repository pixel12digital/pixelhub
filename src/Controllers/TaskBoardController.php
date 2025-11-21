<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\TaskService;
use PixelHub\Services\ProjectService;

/**
 * Controller para gerenciar o quadro Kanban de tarefas
 * 
 * AUDITORIA (2025-01-XX):
 * - Método board(): Exibe o quadro Kanban com filtros (projeto, cliente, tipo, client_query)
 * - Método store(): Cria nova tarefa via TaskService
 * - Método update(): Atualiza tarefa existente, retorna dados formatados (incluindo datas corrigidas)
 * - Método move(): Move tarefa entre colunas/status
 * - Método show(): Retorna dados completos da tarefa em JSON (incluindo checklist)
 * 
 * Tratamento de datas:
 * - Campos DATE (due_date, start_date) são tratados como strings Y-m-d pura, sem conversão de timezone
 * - Formatação para exibição: Y-m-d → d/m/Y via regex direto (evita bug de -1 dia)
 */
class TaskBoardController extends Controller
{
    /**
     * Exibe o quadro Kanban
     */
    public function board(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();
        
        // Filtros
        $projectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int) $_GET['project_id'] : null;
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;
        $clientQuery = isset($_GET['client_query']) && $_GET['client_query'] !== '' ? trim($_GET['client_query']) : null;
        
        // Busca tarefas agrupadas por status
        $tasks = TaskService::getAllTasks($projectId, $tenantId, $clientQuery);
        
        // Busca lista de projetos para o filtro (com filtro de tipo se aplicável)
        $projects = ProjectService::getAllProjects($tenantId, 'ativo', $type);
        
        // Busca lista de tenants para o filtro
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca resumo do projeto se um projeto específico estiver selecionado
        $projectSummary = null;
        if ($projectId) {
            $projectSummary = TaskService::getProjectSummary($projectId);
        }
        
        $this->view('tasks.board', [
            'tasks' => $tasks,
            'projects' => $projects,
            'tenants' => $tenants,
            'selectedProjectId' => $projectId,
            'selectedTenantId' => $tenantId,
            'selectedType' => $type,
            'selectedClientQuery' => $clientQuery,
            'projectSummary' => $projectSummary,
        ]);
    }

    /**
     * Cria uma nova tarefa
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
            
            $id = TaskService::createTask($data);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao criar tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao criar tarefa'], 500);
        }
    }

    /**
     * Atualiza uma tarefa existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        try {
            TaskService::updateTask($id, $_POST);
            
            // Busca a tarefa atualizada para retornar os dados
            $task = TaskService::findTask($id);
            if (!$task) {
                $this->json(['error' => 'Tarefa não encontrada após atualização'], 404);
                return;
            }
            
            // Formata os dados para o frontend
            // IMPORTANTE: Para campos DATE, formatamos diretamente a string Y-m-d para d/m/Y
            // sem usar strtotime para evitar problemas de timezone
            $dueDateFormatted = null;
            if (!empty($task['due_date'])) {
                // Se a data vier como Y-m-d, converte diretamente
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $task['due_date'], $matches)) {
                    $dueDateFormatted = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
                } else {
                    // Fallback para strtotime apenas se necessário
                    $dueDateFormatted = date('d/m/Y', strtotime($task['due_date']));
                }
            }
            
            $startDateFormatted = null;
            if (!empty($task['start_date'])) {
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $task['start_date'], $matches)) {
                    $startDateFormatted = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
                } else {
                    $startDateFormatted = date('d/m/Y', strtotime($task['start_date']));
                }
            }
            
            $completedAtFormatted = null;
            if (!empty($task['completed_at'])) {
                try {
                    $dt = new \DateTime($task['completed_at']);
                    $completedAtFormatted = $dt->format('d/m/Y H:i');
                } catch (\Exception $e) {
                    $completedAtFormatted = date('d/m/Y H:i', strtotime($task['completed_at']));
                }
            }
            
            // Retorna tarefa atualizada com todos os campos necessários, incluindo description
            // IMPORTANTE: description é incluído explicitamente para garantir que o frontend receba o valor atualizado
            $response = [
                'success' => true,
                'task' => [
                    'id' => $task['id'],
                    'title' => $task['title'],
                    'description' => $task['description'] ?? '',  // Campo description sempre incluído
                    'status' => $task['status'],
                    'status_label' => $this->getStatusLabel($task['status']),
                    'assignee' => $task['assignee'] ?? '',
                    'due_date' => $task['due_date'] ?? null,
                    'due_date_formatted' => $dueDateFormatted,
                    'start_date' => $task['start_date'] ?? null,
                    'start_date_formatted' => $startDateFormatted,
                    'task_type' => $task['task_type'] ?? 'internal',
                    'completed_at' => $task['completed_at'] ?? null,
                    'completed_at_formatted' => $completedAtFormatted,
                    'completed_by' => $task['completed_by'] ?? null,
                    'completed_by_name' => $task['completed_by_name'] ?? null,
                    'completion_note' => $task['completion_note'] ?? null,
                    'project_name' => $task['project_name'] ?? '',
                    'tenant_name' => $task['tenant_name'] ?? '',
                ]
            ];
            
            $this->json($response);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar tarefa'], 500);
        }
    }

    /**
     * Retorna o label do status em português
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'backlog' => 'Backlog',
            'em_andamento' => 'Em Andamento',
            'aguardando_cliente' => 'Aguardando Cliente',
            'concluida' => 'Concluída',
        ];
        
        return $labels[$status] ?? $status;
    }

    /**
     * Move uma tarefa para outra coluna/ordem
     */
    public function move(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        $newStatus = trim($_POST['new_status'] ?? '');
        $newOrder = isset($_POST['new_order']) && $_POST['new_order'] !== '' ? (int) $_POST['new_order'] : null;

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        if (empty($newStatus)) {
            $this->json(['error' => 'Status inválido'], 400);
            return;
        }

        try {
            TaskService::moveTask($id, $newStatus, $newOrder);
            $this->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao mover tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao mover tarefa'], 500);
        }
    }

    /**
     * Retorna dados de uma tarefa (incluindo checklist) em JSON
     */
    public function show(): void
    {
        Auth::requireInternal();

        // Extrai ID da URL (formato /tasks/{id})
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $parts = explode('/', trim($uri, '/'));
        $id = 0;
        
        // Procura o índice 'tasks' e pega o próximo elemento
        $tasksIndex = array_search('tasks', $parts);
        if ($tasksIndex !== false && isset($parts[$tasksIndex + 1])) {
            $id = (int) $parts[$tasksIndex + 1];
        }
        
        // Fallback para GET se não encontrar na URL
        if ($id <= 0) {
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        }

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        try {
            $task = TaskService::findTask($id);
            if (!$task) {
                $this->json(['error' => 'Tarefa não encontrada'], 404);
                return;
            }
            
            // Formata completed_at se existir
            $completedAtFormatted = null;
            if (!empty($task['completed_at'])) {
                try {
                    $dt = new \DateTime($task['completed_at']);
                    $completedAtFormatted = $dt->format('d/m/Y H:i');
                } catch (\Exception $e) {
                    $completedAtFormatted = date('d/m/Y H:i', strtotime($task['completed_at']));
                }
            }
            $task['completed_at_formatted'] = $completedAtFormatted;
            
            // Busca checklist
            $checklist = \PixelHub\Services\TaskChecklistService::getItemsByTask($id);
            $task['checklist'] = $checklist;
            
            // Busca anexos
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT * FROM task_attachments
                WHERE task_id = ?
                ORDER BY uploaded_at DESC, id DESC
            ");
            $stmt->execute([$id]);
            $attachments = $stmt->fetchAll();
            
            // Verifica existência dos arquivos físicos
            foreach ($attachments as &$attachment) {
                if (!empty($attachment['file_path'])) {
                    $attachment['file_exists'] = \PixelHub\Core\Storage::fileExists($attachment['file_path']);
                } else {
                    $attachment['file_exists'] = false;
                }
            }
            unset($attachment);
            
            $task['attachments'] = $attachments;
            
            $this->json($task);
        } catch (\Exception $e) {
            error_log("Erro ao buscar tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar tarefa'], 500);
        }
    }
}

