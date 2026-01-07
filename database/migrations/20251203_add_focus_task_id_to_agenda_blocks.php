<?php

/**
 * Migration: Adiciona coluna focus_task_id em agenda_blocks
 * Permite definir uma tarefa foco por bloco
 */
class AddFocusTaskIdToAgendaBlocks
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'focus_task_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE agenda_blocks
                ADD COLUMN focus_task_id INT UNSIGNED NULL AFTER tipo_id,
                ADD INDEX idx_focus_task_id (focus_task_id),
                ADD FOREIGN KEY (focus_task_id) REFERENCES tasks(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(PDO $db): void
    {
        // Verifica se a coluna existe antes de remover
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'focus_task_id'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE agenda_blocks
                DROP FOREIGN KEY agenda_blocks_ibfk_3,
                DROP INDEX idx_focus_task_id,
                DROP COLUMN focus_task_id
            ");
        }
    }
}











