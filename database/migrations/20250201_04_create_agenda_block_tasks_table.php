<?php

/**
 * Migration: Cria tabela agenda_block_tasks
 * Relaciona blocos de agenda com tarefas
 */
class CreateAgendaBlockTasksTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_block_tasks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bloco_id INT UNSIGNED NOT NULL,
                task_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bloco_id (bloco_id),
                INDEX idx_task_id (task_id),
                UNIQUE KEY unique_block_task (bloco_id, task_id),
                FOREIGN KEY (bloco_id) REFERENCES agenda_blocks(id) ON DELETE CASCADE,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_block_tasks");
    }
}

