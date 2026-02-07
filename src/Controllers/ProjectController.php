<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProjectService;
use PixelHub\Services\ServiceService;

/**
 * Controller para gerenciar projetos
 * 
 * FLUXO DE NEGÓCIO - Projetos vs Tickets:
 * 
 * Projetos são coisas grandes e recorrentes (ex: desenvolvimento de site, migração, etc.)
 * - Podem ser internos (sem tenant_id) ou de cliente (com tenant_id)
 * - Projetos podem ter múltiplas tarefas vinculadas
 * 
 * IMPORTANTE: Não criar um projeto para cada chamado de suporte.
 * Para chamados pontuais de suporte, use TICKETS:
 * - Tickets são a unidade de suporte vinculada a um cliente (tenant_id obrigatório)
 * - Tickets podem ter project_id opcional (quando o chamado está ligado a um projeto maior)
 * - Use o botão "Abrir ticket relacionado" na view de projetos para criar tickets vinculados
 * 
 * Dentro do bloco de agenda SUPORTE, trabalhe nos tickets pendentes, não crie projetos novos.
 */
class ProjectController extends Controller
{
    /**
     * Lista todos os projetos
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();
        
        // Filtros
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $statusParam = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : 'ativo';
        // "all" = exibir Ativos + Arquivados; caso contrário filtra pelo status informado
        $status = ($statusParam === 'all') ? null : $statusParam;
        $type = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : null;
        
        // Busca projetos
        $projects = ProjectService::getAllProjects($tenantId, $status, $type);
        
        // Busca lista de tenants para o filtro
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        // Busca lista de serviços ativos para o formulário
        $services = ServiceService::getAllServices(null, true);
        
        // Separa projetos por tipo (apenas se não houver filtro de tipo)
        // IMPORTANTE: Um projeto com tenant_id não nulo é sempre considerado projeto de cliente
        // Um projeto é "interno" apenas se tenant_id é nulo
        $internalProjects = [];
        $clientProjects = [];
        if ($type === null) {
            foreach ($projects as $project) {
                // Usa effective_type se disponível (calculado na query), senão calcula aqui
                $effectiveType = $project['effective_type'] ?? null;
                if ($effectiveType === null) {
                    // Um projeto com tenant_id não nulo é sempre cliente, independente do campo type
                    if (!empty($project['tenant_id'])) {
                        $effectiveType = 'cliente';
                    } else {
                        $effectiveType = 'interno';
                    }
                }
                
                if ($effectiveType === 'cliente') {
                    $clientProjects[] = $project;
                } else {
                    $internalProjects[] = $project;
                }
            }
        }
        
        $this->view('projects.index', [
            'projects' => $projects,
            'internalProjects' => $internalProjects,
            'clientProjects' => $clientProjects,
            'tenants' => $tenants,
            'services' => $services,
            'selectedTenantId' => $tenantId,
            'selectedStatus' => $statusParam,
            'selectedType' => $type,
        ]);
    }

    /**
     * Cria um novo projeto
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
            
            $id = ProjectService::createProject($data);
            
            // Se for requisição AJAX (criação inline do kanban), retorna JSON
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                $project = ProjectService::findProject($id);
                $this->json([
                    'success' => true,
                    'id' => $id,
                    'name' => $project['name'] ?? '',
                    'type' => $project['type'] ?? 'interno',
                    'tenant_name' => $project['tenant_name'] ?? null
                ]);
                return;
            }
            
            $this->redirect('/projects?success=created');
        } catch (\InvalidArgumentException $e) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                $this->json(['error' => $e->getMessage()], 400);
                return;
            }
            
            $this->redirect('/projects?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao criar projeto: " . $e->getMessage());
            
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if ($isAjax) {
                $this->json(['error' => 'Erro ao criar projeto'], 500);
                return;
            }
            
            $this->redirect('/projects?error=database_error');
        }
    }

    /**
     * Atualiza um projeto existente
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/projects?error=missing_id');
            return;
        }

        try {
            $user = Auth::user();
            $data = $_POST;
            if ($user) {
                $data['updated_by'] = $user['id'];
            }
            
            ProjectService::updateProject($id, $data);
            $this->redirect('/projects?success=updated');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/projects?error=' . urlencode($e->getMessage()));
        } catch (\RuntimeException $e) {
            $this->redirect('/projects?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao atualizar projeto: " . $e->getMessage());
            $this->redirect('/projects?error=database_error');
        }
    }

    /**
     * Visualiza detalhes de um projeto
     */
    public function show(): void
    {
        Auth::requireInternal();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/projects?error=missing_id');
            return;
        }

        $project = ProjectService::findProject($id);
        
        if (!$project) {
            $this->redirect('/projects?error=not_found');
            return;
        }

        $taskSummary = \PixelHub\Services\TaskService::getProjectSummary($id);
        
        $this->view('projects.show', [
            'project' => $project,
            'taskSummary' => $taskSummary,
        ]);
    }

    /**
     * Arquivar ou desarquivar um projeto
     * POST action: 'archive' (padrão) ou 'unarchive'
     */
    public function archive(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $action = $_POST['action'] ?? 'archive';

        if ($id <= 0) {
            $this->redirect('/projects?error=missing_id');
            return;
        }

        $redirectToShow = !empty($_POST['redirect_to_show']);
        $redirectTo = $_POST['redirect_to'] ?? null;

        try {
            if ($action === 'unarchive') {
                ProjectService::unarchiveProject($id);
                $successParam = 'unarchived';
            } else {
                ProjectService::archiveProject($id);
                $successParam = 'archived';
            }

            if ($redirectToShow) {
                $this->redirect('/projects/show?id=' . $id . '&success=' . $successParam);
            } elseif ($redirectTo && preg_match('#^/[a-zA-Z0-9/_-]+$#', $redirectTo)) {
                $sep = strpos($redirectTo, '?') !== false ? '&' : '?';
                $this->redirect($redirectTo . $sep . 'success=' . $successParam);
            } else {
                $this->redirect('/projects?success=' . $successParam);
            }
        } catch (\RuntimeException $e) {
            $url = $redirectToShow ? '/projects/show?id=' . $id . '&error=' : '/projects?error=';
            $this->redirect($url . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao arquivar/desarquivar projeto: " . $e->getMessage());
            $url = $redirectToShow ? '/projects/show?id=' . $id . '&error=' : '/projects?error=';
            $this->redirect($url . 'database_error');
        }
    }
}

