<?php

/**
 * Migration: Permite múltiplas sessões da mesma tarefa no mesmo bloco.
 * Remove UNIQUE(bloco_id, task_id) para suportar "pausar e retomar" a mesma tarefa.
 */
class AlterAgendaBlockTasksAllowMultipleSessions
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW INDEX FROM agenda_block_tasks WHERE Key_name = 'unique_block_task'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        $db->exec("ALTER TABLE agenda_block_tasks DROP INDEX unique_block_task");
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW INDEX FROM agenda_block_tasks WHERE Key_name = 'unique_block_task'");
        if ($stmt->rowCount() > 0) {
            return;
        }
        $db->exec("ALTER TABLE agenda_block_tasks ADD UNIQUE KEY unique_block_task (bloco_id, task_id)");
    }
}
