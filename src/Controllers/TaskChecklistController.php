<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Services\TaskChecklistService;

/**
 * Controller para gerenciar checklist de tarefas (rotas AJAX)
 */
class TaskChecklistController extends Controller
{
    /**
     * Adiciona um item ao checklist
     */
    public function add(): void
    {
        Auth::requireInternal();

        $taskId = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        $label = trim($_POST['label'] ?? '');

        if ($taskId <= 0) {
            $this->json(['error' => 'ID da tarefa inválido'], 400);
            return;
        }

        if (empty($label)) {
            $this->json(['error' => 'Label é obrigatório'], 400);
            return;
        }

        try {
            $id = TaskChecklistService::addItem($taskId, $label);
            $this->json(['success' => true, 'id' => $id]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao adicionar item ao checklist: " . $e->getMessage());
            $this->json(['error' => 'Erro ao adicionar item'], 500);
        }
    }

    /**
     * Marca/desmarca um item do checklist
     */
    public function toggle(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $done = isset($_POST['is_done']) ? (bool) $_POST['is_done'] : false;

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        try {
            TaskChecklistService::toggleItem($id, $done);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar item do checklist: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar item'], 500);
        }
    }

    /**
     * Atualiza o label de um item
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $label = trim($_POST['label'] ?? '');

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        if (empty($label)) {
            $this->json(['error' => 'Label é obrigatório'], 400);
            return;
        }

        try {
            TaskChecklistService::updateLabel($id, $label);
            $this->json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao atualizar label do checklist: " . $e->getMessage());
            $this->json(['error' => 'Erro ao atualizar label'], 500);
        }
    }

    /**
     * Remove um item do checklist
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        if ($id <= 0) {
            $this->json(['error' => 'ID inválido'], 400);
            return;
        }

        try {
            TaskChecklistService::deleteItem($id);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            error_log("Erro ao excluir item do checklist: " . $e->getMessage());
            $this->json(['error' => 'Erro ao excluir item'], 500);
        }
    }

    /**
     * Reordena os itens do checklist
     */
    public function reorder(): void
    {
        Auth::requireInternal();

        $taskId = isset($_POST['task_id']) ? (int) $_POST['task_id'] : 0;
        $orderedIds = $_POST['ordered_ids'] ?? [];

        if ($taskId <= 0) {
            $this->json(['error' => 'ID da tarefa inválido'], 400);
            return;
        }

        if (!is_array($orderedIds) || empty($orderedIds)) {
            $this->json(['error' => 'Lista de IDs ordenados é obrigatória'], 400);
            return;
        }

        try {
            TaskChecklistService::reorderItems($taskId, $orderedIds);
            $this->json(['success' => true]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            error_log("Erro ao reordenar itens do checklist: " . $e->getMessage());
            $this->json(['error' => 'Erro ao reordenar itens'], 500);
        }
    }
}

