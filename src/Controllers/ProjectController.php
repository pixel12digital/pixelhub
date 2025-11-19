<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\ProjectService;

/**
 * Controller para gerenciar projetos
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
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
        
        // Busca projetos
        $projects = ProjectService::getAllProjects($tenantId, $status);
        
        // Busca lista de tenants para o filtro
        $stmt = $db->query("SELECT id, name FROM tenants ORDER BY name ASC");
        $tenants = $stmt->fetchAll();
        
        $this->view('projects.index', [
            'projects' => $projects,
            'tenants' => $tenants,
            'selectedTenantId' => $tenantId,
            'selectedStatus' => $status,
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
            $this->redirect('/projects?success=created');
        } catch (\InvalidArgumentException $e) {
            $this->redirect('/projects?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao criar projeto: " . $e->getMessage());
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
     * Arquivar um projeto
     */
    public function archive(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/projects?error=missing_id');
            return;
        }

        try {
            ProjectService::archiveProject($id);
            $this->redirect('/projects?success=archived');
        } catch (\RuntimeException $e) {
            $this->redirect('/projects?error=' . urlencode($e->getMessage()));
        } catch (\Exception $e) {
            error_log("Erro ao arquivar projeto: " . $e->getMessage());
            $this->redirect('/projects?error=database_error');
        }
    }
}

