<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar tipos de serviço de planos recorrentes (plan_service_types)
 * 
 * CRUD simples: listar, criar, editar, excluir, ativar/desativar.
 * Tela única com listagem + form inline.
 */
class PlanServiceTypeController extends Controller
{
    /**
     * Lista todos os tipos de serviço (com form inline para criar/editar)
     * 
     * GET /settings/plan-service-types
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $stmt = $db->query("
            SELECT * FROM plan_service_types
            ORDER BY sort_order ASC, name ASC
        ");
        $serviceTypes = $stmt->fetchAll();

        // Se estiver editando, busca o registro
        $editing = null;
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        if ($editId > 0) {
            $stmt = $db->prepare("SELECT * FROM plan_service_types WHERE id = ?");
            $stmt->execute([$editId]);
            $editing = $stmt->fetch();
        }

        $this->view('settings.plan_service_types.index', [
            'serviceTypes' => $serviceTypes,
            'editing' => $editing,
        ]);
    }

    /**
     * Salva novo tipo de serviço
     * 
     * POST /settings/plan-service-types/store
     */
    public function store(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if (empty($name)) {
            $this->redirect('/settings/plan-service-types?error=missing_name');
            return;
        }

        // Gera slug automaticamente se não informado
        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            $this->redirect('/settings/plan-service-types?error=invalid_slug');
            return;
        }

        // Verifica duplicidade de slug
        $stmt = $db->prepare("SELECT id FROM plan_service_types WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            $this->redirect('/settings/plan-service-types?error=slug_exists');
            return;
        }

        // Pega próximo sort_order
        $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order), -1) FROM plan_service_types")->fetchColumn();

        try {
            $stmt = $db->prepare("
                INSERT INTO plan_service_types (name, slug, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, 1, ?, NOW(), NOW())
            ");
            $stmt->execute([$name, $slug, $maxOrder + 1]);

            $this->redirect('/settings/plan-service-types?success=created');
        } catch (\Exception $e) {
            error_log("Erro ao criar tipo de serviço: " . $e->getMessage());
            $this->redirect('/settings/plan-service-types?error=database_error');
        }
    }

    /**
     * Atualiza tipo de serviço existente
     * 
     * POST /settings/plan-service-types/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');

        if ($id <= 0) {
            $this->redirect('/settings/plan-service-types');
            return;
        }

        if (empty($name)) {
            $this->redirect('/settings/plan-service-types?edit=' . $id . '&error=missing_name');
            return;
        }

        if (empty($slug)) {
            $slug = $this->generateSlug($name);
        }

        if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
            $this->redirect('/settings/plan-service-types?edit=' . $id . '&error=invalid_slug');
            return;
        }

        // Verifica duplicidade de slug em outro registro
        $stmt = $db->prepare("SELECT id FROM plan_service_types WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            $this->redirect('/settings/plan-service-types?edit=' . $id . '&error=slug_exists');
            return;
        }

        try {
            $stmt = $db->prepare("
                UPDATE plan_service_types
                SET name = ?, slug = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $id]);

            $this->redirect('/settings/plan-service-types?success=updated');
        } catch (\Exception $e) {
            error_log("Erro ao atualizar tipo de serviço: " . $e->getMessage());
            $this->redirect('/settings/plan-service-types?edit=' . $id . '&error=database_error');
        }
    }

    /**
     * Alterna status ativo/inativo
     * 
     * POST /settings/plan-service-types/toggle-status
     */
    public function toggleStatus(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/plan-service-types');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("UPDATE plan_service_types SET is_active = NOT is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('/settings/plan-service-types?success=toggled');
        } catch (\Exception $e) {
            error_log("Erro ao alternar status: " . $e->getMessage());
            $this->redirect('/settings/plan-service-types?error=database_error');
        }
    }

    /**
     * Exclui tipo de serviço (se não estiver em uso)
     * 
     * POST /settings/plan-service-types/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->redirect('/settings/plan-service-types');
            return;
        }

        $db = DB::getConnection();

        // Verifica se está em uso por algum plano
        $stmt = $db->prepare("SELECT COUNT(*) FROM hosting_plans WHERE service_type = (SELECT slug FROM plan_service_types WHERE id = ?)");
        $stmt->execute([$id]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            $this->redirect('/settings/plan-service-types?error=in_use');
            return;
        }

        try {
            $stmt = $db->prepare("DELETE FROM plan_service_types WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('/settings/plan-service-types?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao excluir tipo de serviço: " . $e->getMessage());
            $this->redirect('/settings/plan-service-types?error=database_error');
        }
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name, 'UTF-8');
        $slug = str_replace(
            ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ü','ç','ñ'],
            ['a','a','a','a','e','e','i','o','o','o','u','u','c','n'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim($slug, '_');
        return $slug;
    }
}
