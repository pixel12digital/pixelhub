<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar checklist de tarefas
 */
class TaskChecklistService
{
    /**
     * Lista itens do checklist de uma tarefa
     */
    public static function getItemsByTask(int $taskId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT *
            FROM task_checklists
            WHERE task_id = ?
            ORDER BY `order` ASC, created_at ASC
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    /**
     * Adiciona um item ao checklist
     */
    public static function addItem(int $taskId, string $label): int
    {
        $db = DB::getConnection();
        
        // Validações
        $label = trim($label);
        if (empty($label)) {
            throw new \InvalidArgumentException('Label do item é obrigatório');
        }
        
        if (strlen($label) > 255) {
            throw new \InvalidArgumentException('Label do item deve ter no máximo 255 caracteres');
        }
        
        // Verifica se a tarefa existe
        $task = \PixelHub\Services\TaskService::findTask($taskId);
        if (!$task) {
            throw new \RuntimeException('Tarefa não encontrada');
        }
        
        // Calcula order (maior order + 1)
        $stmt = $db->prepare("
            SELECT COALESCE(MAX(`order`), 0) + 1 as next_order
            FROM task_checklists
            WHERE task_id = ?
        ");
        $stmt->execute([$taskId]);
        $result = $stmt->fetch();
        $order = (int) ($result['next_order'] ?? 1);
        
        // Insere no banco
        $stmt = $db->prepare("
            INSERT INTO task_checklists 
            (task_id, label, is_done, `order`, created_at, updated_at)
            VALUES (?, ?, 0, ?, NOW(), NOW())
        ");
        
        $stmt->execute([$taskId, $label, $order]);
        
        return (int) $db->lastInsertId();
    }

    /**
     * Marca/desmarca um item do checklist
     */
    public static function toggleItem(int $id, bool $done): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o item existe
        $item = self::findItem($id);
        if (!$item) {
            throw new \RuntimeException('Item do checklist não encontrado');
        }
        
        $stmt = $db->prepare("
            UPDATE task_checklists 
            SET is_done = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$done ? 1 : 0, $id]);
        
        return true;
    }

    /**
     * Atualiza o label de um item
     */
    public static function updateLabel(int $id, string $label): bool
    {
        $db = DB::getConnection();
        
        // Validações
        $label = trim($label);
        if (empty($label)) {
            throw new \InvalidArgumentException('Label do item é obrigatório');
        }
        
        if (strlen($label) > 255) {
            throw new \InvalidArgumentException('Label do item deve ter no máximo 255 caracteres');
        }
        
        // Verifica se o item existe
        $item = self::findItem($id);
        if (!$item) {
            throw new \RuntimeException('Item do checklist não encontrado');
        }
        
        $stmt = $db->prepare("
            UPDATE task_checklists 
            SET label = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$label, $id]);
        
        return true;
    }

    /**
     * Remove um item do checklist
     */
    public static function deleteItem(int $id): bool
    {
        $db = DB::getConnection();
        
        // Verifica se o item existe
        $item = self::findItem($id);
        if (!$item) {
            throw new \RuntimeException('Item do checklist não encontrado');
        }
        
        $stmt = $db->prepare("DELETE FROM task_checklists WHERE id = ?");
        $stmt->execute([$id]);
        
        return true;
    }

    /**
     * Reordena os itens do checklist de uma tarefa
     * 
     * @param int $taskId ID da tarefa
     * @param array $orderedIds Array de IDs na ordem desejada
     * @return bool
     * @throws \RuntimeException Se algum item não pertencer à tarefa
     */
    public static function reorderItems(int $taskId, array $orderedIds): bool
    {
        $db = DB::getConnection();
        
        // Valida se a tarefa existe
        $task = \PixelHub\Services\TaskService::findTask($taskId);
        if (!$task) {
            throw new \RuntimeException('Tarefa não encontrada');
        }
        
        // Valida se todos os IDs pertencem à tarefa
        if (empty($orderedIds)) {
            return true; // Nada para reordenar
        }
        
        // Converte para inteiros e remove duplicatas
        $orderedIds = array_map('intval', $orderedIds);
        $orderedIds = array_values(array_unique($orderedIds));
        
        // Verifica se todos os itens pertencem à tarefa
        $placeholders = str_repeat('?,', count($orderedIds) - 1) . '?';
        $stmt = $db->prepare("
            SELECT id, task_id
            FROM task_checklists
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($orderedIds);
        $items = $stmt->fetchAll();
        
        // Verifica se todos os IDs foram encontrados
        if (count($items) !== count($orderedIds)) {
            throw new \RuntimeException('Um ou mais itens não foram encontrados');
        }
        
        // Verifica se todos pertencem à tarefa
        foreach ($items as $item) {
            if ((int) $item['task_id'] !== $taskId) {
                throw new \RuntimeException('Um ou mais itens não pertencem a esta tarefa');
            }
        }
        
        // Atualiza a ordem sequencialmente (1, 2, 3...)
        $db->beginTransaction();
        try {
            foreach ($orderedIds as $index => $itemId) {
                $order = $index + 1;
                $stmt = $db->prepare("
                    UPDATE task_checklists
                    SET `order` = ?, updated_at = NOW()
                    WHERE id = ? AND task_id = ?
                ");
                $stmt->execute([$order, $itemId, $taskId]);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Verifica se uma tarefa possui checklists em aberto (não concluídos)
     * 
     * @param int $taskId ID da tarefa
     * @return bool True se houver checklists em aberto, false caso contrário
     */
    public static function hasOpenChecklists(int $taskId): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM task_checklists
            WHERE task_id = ? AND is_done = 0
        ");
        $stmt->execute([$taskId]);
        $result = $stmt->fetch();
        
        return isset($result['total']) && (int)$result['total'] > 0;
    }

    /**
     * Busca um item por ID
     */
    private static function findItem(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT * FROM task_checklists WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}

