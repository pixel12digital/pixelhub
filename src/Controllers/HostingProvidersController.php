<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar provedores de hospedagem
 * 
 * Permite criar, editar e ativar/desativar provedores de hospedagem
 * sem precisar editar código.
 */
class HostingProvidersController extends Controller
{
    /**
     * Lista todos os provedores
     * 
     * GET /settings/hosting-providers
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $stmt = $db->query("
            SELECT *
            FROM hosting_providers
            ORDER BY sort_order ASC, name ASC
        ");
        $providers = $stmt->fetchAll();

        $this->view('settings.hosting_providers.index', [
            'providers' => $providers,
        ]);
    }

    /**
     * Exibe formulário de criação
     * 
     * GET /settings/hosting-providers/create
     */
    public function create(): void
    {
        Auth::requireInternal();

        $this->view('settings.hosting_providers.form', [
            'provider' => null,
        ]);
    }

    /**
     * Salva novo provedor
     * 
     * POST /settings/hosting-providers/store
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

        // Validações
        if (empty($name)) {
            $this->redirect('/settings/hosting-providers/create?error=missing_name');
            return;
        }

        if (empty($slug)) {
            $this->redirect('/settings/hosting-providers/create?error=missing_slug');
            return;
        }

        // Valida formato do slug (apenas letras minúsculas, números e underscore)
        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            $this->redirect('/settings/hosting-providers/create?error=invalid_slug');
            return;
        }

        // Verifica se slug já existe
        $stmt = $db->prepare("SELECT id FROM hosting_providers WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $this->redirect('/settings/hosting-providers/create?error=slug_exists');
            return;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO hosting_providers (name, slug, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([$name, $slug, $isActive, $sortOrder]);

            $this->redirect('/settings/hosting-providers?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar provedor: " . $e->getMessage());
            $this->redirect('/settings/hosting-providers/create?error=database_error');
        }
    }

    /**
     * Exibe formulário de edição
     * 
     * GET /settings/hosting-providers/edit?id={id}
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/hosting-providers');
            return;
        }

        $stmt = $db->prepare("SELECT * FROM hosting_providers WHERE id = ?");
        $stmt->execute([$id]);
        $provider = $stmt->fetch();

        if (!$provider) {
            $this->redirect('/settings/hosting-providers?error=not_found');
            return;
        }

        $this->view('settings.hosting_providers.form', [
            'provider' => $provider,
        ]);
    }

    /**
     * Atualiza provedor existente
     * 
     * POST /settings/hosting-providers/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

        if ($id <= 0) {
            $this->redirect('/settings/hosting-providers');
            return;
        }

        // Validações
        if (empty($name)) {
            $this->redirect('/settings/hosting-providers/edit?id=' . $id . '&error=missing_name');
            return;
        }

        if (empty($slug)) {
            $this->redirect('/settings/hosting-providers/edit?id=' . $id . '&error=missing_slug');
            return;
        }

        // Valida formato do slug
        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            $this->redirect('/settings/hosting-providers/edit?id=' . $id . '&error=invalid_slug');
            return;
        }

        // Verifica se slug já existe em outro registro
        $stmt = $db->prepare("SELECT id FROM hosting_providers WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $this->redirect('/settings/hosting-providers/edit?id=' . $id . '&error=slug_exists');
            return;
        }

        try {
            $stmt = $db->prepare("
                UPDATE hosting_providers
                SET name = ?, slug = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$name, $slug, $isActive, $sortOrder, $id]);

            $this->redirect('/settings/hosting-providers?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar provedor: " . $e->getMessage());
            $this->redirect('/settings/hosting-providers/edit?id=' . $id . '&error=database_error');
        }
    }

    /**
     * Alterna status ativo/inativo
     * 
     * POST /settings/hosting-providers/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/hosting-providers');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("UPDATE hosting_providers SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('/settings/hosting-providers?success=toggled');
        } catch (\Exception $e) {
            error_log("Erro ao alternar status do provedor: " . $e->getMessage());
            $this->redirect('/settings/hosting-providers?error=database_error');
        }
    }
}

