<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\TaskService;
use PixelHub\Services\ProjectService;
use PixelHub\Services\TaskChecklistService;

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
        $agendaFilter = isset($_GET['agenda_filter']) && $_GET['agenda_filter'] !== '' ? $_GET['agenda_filter'] : null;
        
        // Valida filtro de agenda
        if ($agendaFilter && !in_array($agendaFilter, ['with', 'without'])) {
            $agendaFilter = null;
        }
        
        // Busca tarefas agrupadas por status
        $tasks = TaskService::getAllTasks($projectId, $tenantId, $clientQuery, $agendaFilter);
        
        // Define effectiveTenantId: prioriza tenant_id da URL, senão tenta identificar pelo client_query
        $effectiveTenantId = $tenantId;
        if (!$effectiveTenantId && $clientQuery) {
            // Busca tenant_ids que correspondem ao client_query
            // Remove caracteres especiais e faz busca mais flexível
            $searchTerm = '%' . str_replace(['|', '+'], '%', $clientQuery) . '%';
            $stmt = $db->prepare("SELECT id FROM tenants WHERE name LIKE ? ORDER BY 
                CASE WHEN name = ? THEN 1 
                     WHEN name LIKE ? THEN 2 
                     ELSE 3 END
                LIMIT 1");
            $exactMatch = $clientQuery;
            $startsWith = $clientQuery . '%';
            $stmt->execute([$searchTerm, $exactMatch, $startsWith]);
            $tenantMatch = $stmt->fetch();
            if ($tenantMatch) {
                $effectiveTenantId = (int) $tenantMatch['id'];
            } else {
                // Tenta buscar por partes do nome (para casos como "Robson Wagner | CFC Bom Conselho")
                $parts = explode('|', $clientQuery);
                if (count($parts) > 1) {
                    // Tenta com a primeira parte (nome do cliente)
                    $firstPart = trim($parts[0]);
                    $stmt = $db->prepare("SELECT id FROM tenants WHERE name LIKE ? LIMIT 1");
                    $stmt->execute(['%' . $firstPart . '%']);
                    $tenantMatch = $stmt->fetch();
                    if ($tenantMatch) {
                        $effectiveTenantId = (int) $tenantMatch['id'];
                    }
                }
            }
        }
        
        // Busca lista de projetos para o filtro
        // IMPORTANTE: Mostra TODOS os projetos ativos, mesmo os concluídos
        // Isso permite que o usuário filtre por qualquer projeto ativo, independente do status de conclusão
        // Se um projeto está ativo mas concluído, ainda deve aparecer no select para permitir filtro/visualização
        $projects = ProjectService::getAllProjects($effectiveTenantId, 'ativo', $type);
        
        // Para o modal de criação, SEMPRE busca TODOS os projetos ativos (igual ao /projects)
        // IMPORTANTE: O modal deve mostrar TODOS os projetos ativos, independente de filtros
        // Isso garante consistência com a tela /projects onde o usuário vê todos os projetos
        // 
        // Não aplica NENHUM filtro (tenant, tipo, etc) - mostra tudo que está ativo
        // Isso resolve problemas de unificação de clientes e garante que todos os projetos apareçam
        $allActiveProjects = ProjectService::getAllProjects(null, 'ativo', null);
        
        // Debug temporário - remover depois
        // error_log("[KANBAN MODAL] Total de projetos encontrados: " . count($allActiveProjects));
        // foreach ($allActiveProjects as $proj) {
        //     if (stripos($proj['name'], 'CFC') !== false || stripos($proj['name'], 'Bom Conselho') !== false) {
        //         error_log("[KANBAN MODAL] Projeto encontrado: ID=" . $proj['id'] . ", Name=" . $proj['name'] . ", Status=" . ($proj['status'] ?? 'N/A') . ", TenantID=" . ($proj['tenant_id'] ?? 'NULL'));
        //     }
        // }
        
        // Calcula contexto para simplificar criação de tarefas
        // Se há um projeto selecionado OU apenas um projeto válido no contexto, podemos ocultar o campo
        $contextProjectId = $projectId; // Projeto já selecionado no filtro
        $availableProjectsCount = count($projects);
        $shouldHideProjectField = false;
        
        if ($projectId) {
            // Se já tem projeto selecionado, não precisa mostrar o campo
            $shouldHideProjectField = true;
        } elseif ($availableProjectsCount === 1) {
            // Se há apenas um projeto válido, usa ele automaticamente
            $contextProjectId = $projects[0]['id'];
            $shouldHideProjectField = true;
        } elseif ($availableProjectsCount === 0) {
            // Se não há projetos, precisa mostrar para criar (ou mostrar mensagem)
            $shouldHideProjectField = false;
        }
        
        // Busca lista de tenants para o filtro
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca resumo do projeto se um projeto específico estiver selecionado
        $projectSummary = null;
        $selectedProject = null;
        if ($projectId) {
            $projectSummary = TaskService::getProjectSummary($projectId);
            // Busca dados do projeto para breadcrumb e botão arquivar (em allActiveProjects que tem todos)
            foreach ($allActiveProjects as $p) {
                if ((int)$p['id'] === $projectId) {
                    $selectedProject = $p;
                    break;
                }
            }
        }
        
        $createTaskOnLoad = isset($_GET['create_task']) && $_GET['create_task'] === '1';
        $taskIdToOpen = isset($_GET['task_id']) && $_GET['task_id'] !== '' ? (int) $_GET['task_id'] : null;
        
        $this->view('tasks.board', [
            'tasks' => $tasks,
            'projects' => $projects, // Projetos para filtro (sem concluídos)
            'allActiveProjects' => $allActiveProjects, // Todos os projetos ativos para o modal
            'tenants' => $tenants,
            'selectedProjectId' => $projectId,
            'selectedProject' => $selectedProject,
            'selectedTenantId' => $tenantId,
            'selectedType' => $type,
            'selectedClientQuery' => $clientQuery,
            'selectedAgendaFilter' => $agendaFilter,
            'projectSummary' => $projectSummary,
            'contextProjectId' => $contextProjectId, // Projeto inferido do contexto
            'shouldHideProjectField' => $shouldHideProjectField, // Se deve ocultar campo projeto
            'availableProjectsCount' => $availableProjectsCount, // Quantidade de projetos disponíveis
            'createTaskOnLoad' => $createTaskOnLoad,
            'taskIdToOpen' => $taskIdToOpen,
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
        $markAllChecklistOk = isset($_POST['mark_all_checklist_ok']) && $_POST['mark_all_checklist_ok'] === '1';

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        // Ao mudar para Concluída: verifica checklists pendentes
        $newStatus = isset($_POST['status']) ? trim($_POST['status']) : null;
        if ($newStatus === 'concluida') {
            $task = TaskService::findTask($id);
            if ($task && ($task['status'] ?? '') !== 'concluida') {
                $pendingCount = TaskChecklistService::getPendingCount($id);
                if ($pendingCount > 0 && !$markAllChecklistOk) {
                    $this->json([
                        'success' => false,
                        'checklist_pending' => true,
                        'pending_count' => $pendingCount,
                        'message' => "Esta tarefa possui {$pendingCount} item(ns) do checklist pendente(s). Deseja marcar todos como concluídos e finalizar a tarefa mesmo assim?"
                    ], 200);
                    return;
                }
                if ($pendingCount > 0 && $markAllChecklistOk) {
                    TaskChecklistService::markAllAsDone($id);
                }
            }
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
            
            // Busca checklist para incluir na resposta (evita perda ao re-renderizar modal)
            $checklist = \PixelHub\Services\TaskChecklistService::getItemsByTask($id);
            
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
                    'checklist' => $checklist,
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
        $markAllChecklistOk = isset($_POST['mark_all_checklist_ok']) && $_POST['mark_all_checklist_ok'] === '1';

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        if (empty($newStatus)) {
            $this->json(['error' => 'Status inválido'], 400);
            return;
        }

        // Ao mover para Concluída: verifica checklists pendentes
        if ($newStatus === 'concluida') {
            $task = TaskService::findTask($id);
            if ($task && $task['status'] !== 'concluida') {
                $pendingCount = TaskChecklistService::getPendingCount($id);
                if ($pendingCount > 0) {
                    if (!$markAllChecklistOk) {
                        $this->json([
                            'success' => false,
                            'checklist_pending' => true,
                            'pending_count' => $pendingCount,
                            'message' => "Esta tarefa possui {$pendingCount} item(ns) do checklist pendente(s). Deseja marcar todos como concluídos e finalizar a tarefa mesmo assim?"
                        ], 200);
                        return;
                    }
                    TaskChecklistService::markAllAsDone($id);
                }
            }
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
            
            // Busca blocos de agenda relacionados
            try {
                $blocosRelacionados = \PixelHub\Services\AgendaService::getBlocksForTask($id);
                $task['blocos_relacionados'] = $blocosRelacionados;
            } catch (\Exception $e) {
                error_log("Erro ao buscar blocos relacionados: " . $e->getMessage());
                $task['blocos_relacionados'] = [];
            }
            
            // Busca tickets vinculados a esta tarefa
            try {
                $ticketsVinculados = \PixelHub\Services\TicketService::findTicketsByTaskId($id);
                $task['tickets_vinculados'] = $ticketsVinculados;
            } catch (\Exception $e) {
                error_log("Erro ao buscar tickets vinculados: " . $e->getMessage());
                $task['tickets_vinculados'] = [];
            }
            
            // Busca anexos com informações do usuário que fez upload
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    ta.*,
                    u.name as uploaded_by_name
                FROM task_attachments ta
                LEFT JOIN users u ON ta.uploaded_by = u.id
                WHERE ta.task_id = ?
                ORDER BY ta.uploaded_at DESC, ta.id DESC
            ");
            $stmt->execute([$id]);
            $attachments = $stmt->fetchAll();
            
            // Constrói BASE_URL para links de compartilhamento
            if (defined('BASE_URL')) {
                $baseUrl = rtrim(BASE_URL, '/');
            } else {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                $baseUrl = $protocol . $domainName . $basePath;
            }
            
            // Verifica existência dos arquivos físicos e adiciona URL de download
            foreach ($attachments as &$attachment) {
                if (!empty($attachment['file_path'])) {
                    $attachment['file_exists'] = \PixelHub\Core\Storage::fileExists($attachment['file_path']);
                } else {
                    $attachment['file_exists'] = false;
                }
                
                // Adiciona URL de download para uso no player de vídeo
                if (!empty($attachment['id']) && $attachment['file_exists']) {
                    // Monta URL de download usando BASE_PATH
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    $attachment['download_url'] = $basePath . '/tasks/attachments/download?id=' . $attachment['id'];
                } else {
                    $attachment['download_url'] = null;
                }
                
                // Para gravações de tela, busca ou cria link de compartilhamento
                // Detecta por recording_type ou por mime_type e nome do arquivo
                $isScreenRecording = (!empty($attachment['recording_type']) && $attachment['recording_type'] === 'screen_recording') ||
                                     (!empty($attachment['mime_type']) && strpos($attachment['mime_type'], 'video/') === 0 &&
                                      (strpos($attachment['file_name'], 'screen-recording') !== false || 
                                       strpos($attachment['original_name'], 'screen-recording') !== false));
                
                if ($isScreenRecording) {
                    // Busca se já existe registro na screen_recordings para este anexo
                    $srStmt = $db->prepare("
                        SELECT id, public_token 
                        FROM screen_recordings 
                        WHERE task_id = ? AND file_name = ?
                        LIMIT 1
                    ");
                    $srStmt->execute([$id, $attachment['file_name']]);
                    $screenRecording = $srStmt->fetch();
                    
                    if ($screenRecording && !empty($screenRecording['public_token'])) {
                        // Já existe token público
                        $attachment['public_url'] = $baseUrl . '/screen-recordings/share?token=' . urlencode($screenRecording['public_token']);
                    } else {
                        // Cria registro na screen_recordings com public_token
                        $publicToken = bin2hex(random_bytes(16)); // 32 caracteres
                        
                        // Usa o caminho real do arquivo (storage/tasks/{taskId}/arquivo.webm)
                        // Isso permite que o share.php encontre o arquivo corretamente
                        $filePath = $attachment['file_path'];
                        $relativePath = ltrim($filePath, '/');
                        
                        try {
                            $insertStmt = $db->prepare("
                                INSERT INTO screen_recordings 
                                (task_id, file_path, file_name, original_name, mime_type, size_bytes, duration_seconds, has_audio, public_token, created_at, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $insertStmt->execute([
                                $id, // task_id - importante para identificar que é de uma tarefa
                                $relativePath, // Usa o caminho real do arquivo
                                $attachment['file_name'],
                                $attachment['original_name'],
                                $attachment['mime_type'] ?? 'video/webm',
                                $attachment['file_size'] ?? 0,
                                $attachment['duration'] ?? null,
                                0, // has_audio (assume false se não informado)
                                $publicToken,
                                $attachment['uploaded_at'] ?? date('Y-m-d H:i:s'),
                                $attachment['uploaded_by'] ?? null
                            ]);
                            
                            $attachment['public_url'] = $baseUrl . '/screen-recordings/share?token=' . urlencode($publicToken);
                        } catch (\Exception $e) {
                            error_log("Erro ao criar registro de compartilhamento para anexo {$attachment['id']}: " . $e->getMessage());
                            $attachment['public_url'] = null;
                        }
                    }
                } else {
                    $attachment['public_url'] = null;
                }
                
                // Garante que recording_type e duration estejam presentes (mesmo que null)
                if (!isset($attachment['recording_type'])) {
                    $attachment['recording_type'] = null;
                }
                if (!isset($attachment['duration'])) {
                    $attachment['duration'] = null;
                }
                
                // Garante que uploaded_by_name esteja presente (mesmo que null)
                if (!isset($attachment['uploaded_by_name'])) {
                    $attachment['uploaded_by_name'] = null;
                }
            }
            unset($attachment);
            
            $task['attachments'] = $attachments;
            
            // Adiciona dados do tenant (cliente) para compartilhamento via WhatsApp
            if (!empty($task['project_tenant_id'])) {
                $tenantStmt = $db->prepare("
                    SELECT id, name, phone 
                    FROM tenants 
                    WHERE id = ?
                ");
                $tenantStmt->execute([$task['project_tenant_id']]);
                $tenant = $tenantStmt->fetch();
                
                if ($tenant) {
                    $task['tenant'] = [
                        'id' => $tenant['id'],
                        'name' => $tenant['name'],
                        'phone' => $tenant['phone'] ?? null
                    ];
                    
                    // Normaliza telefone para WhatsApp usando função existente
                    if (!empty($tenant['phone'])) {
                        $phoneNormalized = \PixelHub\Services\WhatsAppBillingService::normalizePhone($tenant['phone']);
                        if ($phoneNormalized) {
                            $task['tenant']['whatsapp_link'] = 'https://wa.me/' . $phoneNormalized;
                        } else {
                            $task['tenant']['whatsapp_link'] = null;
                        }
                    } else {
                        $task['tenant']['whatsapp_link'] = null;
                    }
                } else {
                    $task['tenant'] = null;
                }
            } else {
                $task['tenant'] = null;
            }
            
            $this->json($task);
        } catch (\Exception $e) {
            error_log("Erro ao buscar tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao buscar tarefa'], 500);
        }
    }
    
    /**
     * Retorna HTML do modal de detalhes da tarefa para uso via AJAX
     * Reutiliza a mesma lógica do método show() mas retorna HTML renderizado
     */
    public function modal(): void
    {
        Auth::requireInternal();
        
        $taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($taskId <= 0) {
            http_response_code(400);
            echo '<p style="color: #c33;">Task ID inválido</p>';
            return;
        }
        
        try {
            $task = TaskService::findTask($taskId);
            if (!$task) {
                http_response_code(404);
                echo '<p style="color: #c33;">Tarefa não encontrada</p>';
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
            $checklist = \PixelHub\Services\TaskChecklistService::getItemsByTask($taskId);
            $task['checklist'] = $checklist;
            
            // Busca blocos de agenda relacionados
            try {
                $blocosRelacionados = \PixelHub\Services\AgendaService::getBlocksForTask($taskId);
                $task['blocos_relacionados'] = $blocosRelacionados;
            } catch (\Exception $e) {
                error_log("Erro ao buscar blocos relacionados: " . $e->getMessage());
                $task['blocos_relacionados'] = [];
            }
            
            // Busca anexos
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT 
                    ta.*,
                    u.name as uploaded_by_name
                FROM task_attachments ta
                LEFT JOIN users u ON ta.uploaded_by = u.id
                WHERE ta.task_id = ?
                ORDER BY ta.uploaded_at DESC, ta.id DESC
            ");
            $stmt->execute([$taskId]);
            $attachments = $stmt->fetchAll();
            
            // Verifica existência dos arquivos físicos
            foreach ($attachments as &$attachment) {
                if (!empty($attachment['file_path'])) {
                    $attachment['file_exists'] = \PixelHub\Core\Storage::fileExists($attachment['file_path']);
                } else {
                    $attachment['file_exists'] = false;
                }
                
                if (!empty($attachment['id']) && $attachment['file_exists']) {
                    $basePath = defined('BASE_PATH') ? BASE_PATH : '';
                    $attachment['download_url'] = $basePath . '/tasks/attachments/download?id=' . $attachment['id'];
                } else {
                    $attachment['download_url'] = null;
                }
                
                if (!isset($attachment['recording_type'])) {
                    $attachment['recording_type'] = null;
                }
                if (!isset($attachment['duration'])) {
                    $attachment['duration'] = null;
                }
                if (!isset($attachment['uploaded_by_name'])) {
                    $attachment['uploaded_by_name'] = null;
                }
            }
            unset($attachment);
            
            $task['attachments'] = $attachments;
            
            // Renderiza o modal usando a mesma view do board
            // Passa os dados para serem renderizados via JavaScript no lado do cliente
            // Ou retorna JSON para o JavaScript renderizar
            header('Content-Type: application/json');
            echo json_encode($task);
            
        } catch (\Exception $e) {
            error_log("Erro ao carregar modal da tarefa: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar tarefa']);
        }
    }
    
    /**
     * Atualiza apenas o status da tarefa
     */
    public function updateTaskStatus(): void
    {
        Auth::requireInternal();
        
        $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : null;
        $markAllChecklistOk = isset($_POST['mark_all_checklist_ok']) && $_POST['mark_all_checklist_ok'] === '1';
        
        if ($taskId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID de tarefa inválido']);
            return;
        }
        
        if (!$status) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Status não informado']);
            return;
        }
        
        $allowedStatuses = ['backlog', 'em_andamento', 'aguardando_cliente', 'concluida'];
        if (!in_array($status, $allowedStatuses)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Status inválido']);
            return;
        }
        
        try {
            // Busca a tarefa atual para verificar se o status mudou
            $task = TaskService::findTask($taskId);
            if (!$task) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Tarefa não encontrada']);
                return;
            }
            
            $oldStatus = $task['status'] ?? null;
            
            // Ao mudar para Concluída: verifica checklists pendentes
            if ($status === 'concluida' && $oldStatus !== 'concluida') {
                $pendingCount = TaskChecklistService::getPendingCount($taskId);
                if ($pendingCount > 0 && !$markAllChecklistOk) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'checklist_pending' => true,
                        'pending_count' => $pendingCount,
                        'message' => "Esta tarefa possui {$pendingCount} item(ns) do checklist pendente(s). Deseja marcar todos como concluídos e finalizar a tarefa mesmo assim?"
                    ]);
                    return;
                }
                if ($pendingCount > 0 && $markAllChecklistOk) {
                    TaskChecklistService::markAllAsDone($taskId);
                }
            }
            
            // Se o status mudou, usa moveTask para ajustar a ordem corretamente
            // Isso garante que a tarefa apareça na coluna correta no quadro Kanban
            if ($oldStatus !== $status) {
                // moveTask ajusta a ordem automaticamente quando o status muda
                TaskService::moveTask($taskId, $status, null);
                
                // Se a tarefa foi concluída e está vinculada a um ticket, marca ticket como resolvido
                if ($status === 'concluida') {
                    try {
                        \PixelHub\Services\TicketService::markTicketResolvedFromTask($taskId);
                    } catch (\Exception $e) {
                        // Loga mas não quebra o fluxo se houver erro na sincronização
                        error_log("Erro ao sincronizar ticket ao concluir tarefa: " . $e->getMessage());
                    }
                }
            } else {
                // Se o status não mudou, apenas atualiza outros campos se necessário
                TaskService::updateTask($taskId, ['status' => $status]);
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            // Retorna mensagem amigável para validações (ex: checklists em aberto)
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar status da tarefa: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status da tarefa']);
        }
    }

    /**
     * Exclui uma tarefa (soft delete)
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $taskId = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int) $_POST['project_id'] : null;

        if ($taskId <= 0) {
            $this->json(['error' => 'ID da tarefa é obrigatório'], 400);
            return;
        }

        try {
            TaskService::deleteTask($taskId, $projectId);
            $this->json(['success' => true, 'message' => 'Tarefa excluída com sucesso']);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao excluir tarefa: " . $e->getMessage());
            $this->json(['error' => 'Erro ao excluir tarefa'], 500);
        }
    }
}

