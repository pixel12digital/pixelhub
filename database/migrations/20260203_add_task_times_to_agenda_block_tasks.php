<?php

/**
 * Migration: Adiciona hora_inicio e hora_fim por tarefa em agenda_block_tasks
 * Permite registrar horário específico de cada tarefa dentro da janela do bloco
 */
class AddTaskTimesToAgendaBlockTasks
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_block_tasks LIKE 'hora_inicio'");
        if ($stmt->rowCount() > 0) {
            return;
        }
        $db->exec("
            ALTER TABLE agenda_block_tasks
            ADD COLUMN hora_inicio TIME NULL AFTER task_id,
            ADD COLUMN hora_fim TIME NULL AFTER hora_inicio
        ");
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_block_tasks LIKE 'hora_inicio'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        $db->exec("
            ALTER TABLE agenda_block_tasks
            DROP COLUMN hora_inicio,
            DROP COLUMN hora_fim
        ");
    }
}
