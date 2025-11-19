<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar categorias de tipos de serviço (billing_service_types)
 * 
 * Permite criar, editar e ativar/desativar categorias de contratos recorrentes
 * sem precisar editar código.
 */
class BillingServiceTypesController extends Controller
{
    /**
     * Lista todas as categorias de serviço
     * 
     * GET /billing/service-types
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $stmt = $db->query("
            SELECT *
            FROM billing_service_types
            ORDER BY sort_order ASC, name ASC
        ");
        $serviceTypes = $stmt->fetchAll();

        $this->view('billing.service_types.index', [
            'serviceTypes' => $serviceTypes,
        ]);
    }

    /**
     * Exibe formulário de criação
     * 
     * GET /billing/service-types/create
     */
    public function create(): void
    {
        Auth::requireInternal();

        $this->view('billing.service_types.form', [
            'serviceType' => null,
        ]);
    }

    /**
     * Salva nova categoria
     * 
     * POST /billing/service-types/store
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

        // Validações
        if (empty($slug)) {
            $this->redirect('/billing/service-types/create?error=missing_slug');
            return;
        }

        if (empty($name)) {
            $this->redirect('/billing/service-types/create?error=missing_name');
            return;
        }

        // Valida formato do slug (apenas letras, números e underscore)
        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            $this->redirect('/billing/service-types/create?error=invalid_slug');
            return;
        }

        // Verifica se slug já existe
        $stmt = $db->prepare("SELECT id FROM billing_service_types WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $this->redirect('/billing/service-types/create?error=slug_exists');
            return;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO billing_service_types (slug, name, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([$slug, $name, $isActive, $sortOrder]);

            $this->redirect('/billing/service-types?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar categoria de serviço: " . $e->getMessage());
            $this->redirect('/billing/service-types/create?error=database_error');
        }
    }

    /**
     * Exibe formulário de edição
     * 
     * GET /billing/service-types/edit?id={id}
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/billing/service-types');
            return;
        }

        $stmt = $db->prepare("SELECT * FROM billing_service_types WHERE id = ?");
        $stmt->execute([$id]);
        $serviceType = $stmt->fetch();

        if (!$serviceType) {
            $this->redirect('/billing/service-types?error=not_found');
            return;
        }

        $this->view('billing.service_types.form', [
            'serviceType' => $serviceType,
        ]);
    }

    /**
     * Atualiza categoria existente
     * 
     * POST /billing/service-types/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;

        if ($id <= 0) {
            $this->redirect('/billing/service-types');
            return;
        }

        // Validações
        if (empty($slug)) {
            $this->redirect('/billing/service-types/edit?id=' . $id . '&error=missing_slug');
            return;
        }

        if (empty($name)) {
            $this->redirect('/billing/service-types/edit?id=' . $id . '&error=missing_name');
            return;
        }

        // Valida formato do slug
        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            $this->redirect('/billing/service-types/edit?id=' . $id . '&error=invalid_slug');
            return;
        }

        // Verifica se slug já existe em outro registro
        $stmt = $db->prepare("SELECT id FROM billing_service_types WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $this->redirect('/billing/service-types/edit?id=' . $id . '&error=slug_exists');
            return;
        }

        try {
            $stmt = $db->prepare("
                UPDATE billing_service_types
                SET slug = ?, name = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([$slug, $name, $isActive, $sortOrder, $id]);

            $this->redirect('/billing/service-types?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar categoria de serviço: " . $e->getMessage());
            $this->redirect('/billing/service-types/edit?id=' . $id . '&error=database_error');
        }
    }

    /**
     * Alterna status ativo/inativo
     * 
     * POST /billing/service-types/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/billing/service-types');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("UPDATE billing_service_types SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('/billing/service-types?success=toggled');
        } catch (\Exception $e) {
            error_log("Erro ao alternar status da categoria: " . $e->getMessage());
            $this->redirect('/billing/service-types?error=database_error');
        }
    }
}

