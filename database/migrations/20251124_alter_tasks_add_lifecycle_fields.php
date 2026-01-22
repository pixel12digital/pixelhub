<?php

/**
 * Migration: Adiciona campos de ciclo de vida às tarefas
 * 
 * Campos adicionados:
 * - start_date (DATE, NULL): Data de início da tarefa
 * - completed_at (DATETIME, NULL): Data/hora de conclusão
 * - completed_by (INT, NULL): ID do usuário que concluiu
 * - completion_note (TEXT, NULL): Resumo/feedback da conclusão
 * - task_type (VARCHAR(50), NULL): Tipo de tarefa ('internal' ou 'client_ticket')
 */
class AlterTasksAddLifecycleFields
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE tasks
            ADD COLUMN start_date DATE NULL AFTER due_date,
            ADD COLUMN completed_at DATETIME NULL AFTER start_date,
            ADD COLUMN completed_by INT UNSIGNED NULL AFTER completed_at,
            ADD COLUMN completion_note TEXT NULL AFTER completed_by,
            ADD COLUMN task_type VARCHAR(50) NULL AFTER completion_note,
            ADD INDEX idx_task_type (task_type),
            ADD INDEX idx_completed_at (completed_at)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE tasks
            DROP COLUMN task_type,
            DROP COLUMN completion_note,
            DROP COLUMN completed_by,
            DROP COLUMN completed_at,
            DROP COLUMN start_date
        ");
    }
}

