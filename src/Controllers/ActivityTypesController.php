<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;

/**
 * Controller para gerenciar Tipos de Atividade (Reunião, Follow-up, Suporte rápido, etc.)
 * Usados no 2º select quando "Atividade avulsa" é selecionada na Agenda.
 */
class ActivityTypesController extends Controller
{
    /**
     * Lista todos os tipos
     */
    public function index(): void
    {
        Auth::requireInternal();

        try {
            $db = DB::getConnection();
            $stmt = $db->query("
                SELECT t.*,
                    (SELECT COUNT(*) FROM agenda_blocks WHERE activity_type_id = t.id) as blocks_count
                FROM activity_types t
                ORDER BY t.name ASC
            ");
            $types = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $types = [];
            if (strpos($e->getMessage(), "doesn't exist") === false) {
                throw $e;
            }
        }

        $this->view('activity_types.index', [
            'types' => $types,
        ]);
    }

    /**
     * Formulário de criação
     */
    public function create(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();
        $blockTypes = $db->query("SELECT id, nome, cor_hex FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('activity_types.form', [
            'type' => null,
            'blockTypes' => $blockTypes,
        ]);
    }

    /**
     * Salva novo tipo
     */
    public function store(): void
    {
        Auth::requireInternal();

        $name = trim($_POST['name'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $defaultBlockTypeId = isset($_POST['default_block_type_id']) && $_POST['default_block_type_id'] !== '' ? (int)$_POST['default_block_type_id'] : null;

        if (empty($name)) {
            $this->redirect('/settings/activity-types/create?error=' . urlencode('Nome é obrigatório.'));
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                INSERT INTO activity_types (name, ativo, default_block_type_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $ativo, $defaultBlockTypeId]);
            $this->redirect('/settings/activity-types?success=created');
        } catch (\PDOException $e) {
            error_log("Erro ao criar tipo de atividade: " . $e->getMessage());
            $this->redirect('/settings/activity-types/create?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Formulário de edição
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/activity-types?error=missing_id');
            return;
        }

        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM activity_types WHERE id = ?");
        $stmt->execute([$id]);
        $type = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$type) {
            $this->redirect('/settings/activity-types?error=not_found');
            return;
        }

        $blockTypes = $db->query("SELECT id, nome, cor_hex FROM agenda_block_types WHERE ativo = 1 ORDER BY nome ASC")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('activity_types.form', [
            'type' => $type,
            'blockTypes' => $blockTypes,
        ]);
    }

    /**
     * Atualiza tipo
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/activity-types?error=missing_id');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $defaultBlockTypeId = isset($_POST['default_block_type_id']) && $_POST['default_block_type_id'] !== '' ? (int)$_POST['default_block_type_id'] : null;

        if (empty($name)) {
            $this->redirect('/settings/activity-types/edit?id=' . $id . '&error=' . urlencode('Nome é obrigatório.'));
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                UPDATE activity_types
                SET name = ?, ativo = ?, default_block_type_id = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $ativo, $defaultBlockTypeId, $id]);
            $this->redirect('/settings/activity-types?success=updated');
        } catch (\PDOException $e) {
            error_log("Erro ao atualizar tipo de atividade: " . $e->getMessage());
            $this->redirect('/settings/activity-types/edit?id=' . $id . '&error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Exclui tipo (soft delete: ativo = 0)
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/activity-types?error=missing_id');
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("UPDATE activity_types SET ativo = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $this->redirect('/settings/activity-types?success=deleted');
        } catch (\Exception $e) {
            error_log("Erro ao desativar tipo: " . $e->getMessage());
            $this->redirect('/settings/activity-types?error=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Reativa tipo (ativo = 1)
     */
    public function restore(): void
    {
        Auth::requireInternal();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/settings/activity-types?error=missing_id');
            return;
        }

        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("UPDATE activity_types SET ativo = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $this->redirect('/settings/activity-types?success=restored');
        } catch (\Exception $e) {
            error_log("Erro ao reativar tipo: " . $e->getMessage());
            $this->redirect('/settings/activity-types?error=' . urlencode($e->getMessage()));
        }
    }
}
