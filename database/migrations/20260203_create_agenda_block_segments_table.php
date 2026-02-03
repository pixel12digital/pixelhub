<?php

/**
 * Migration: Cria tabela agenda_block_segments
 * Registra períodos de trabalho por projeto dentro de um bloco (multi-projeto com pausa/retomada)
 */
class CreateAgendaBlockSegmentsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_block_segments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                block_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                project_id INT UNSIGNED NULL,
                task_id INT UNSIGNED NULL,
                status ENUM('running', 'paused', 'done') NOT NULL DEFAULT 'running',
                started_at DATETIME NOT NULL,
                ended_at DATETIME NULL,
                duration_seconds INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_block_id (block_id),
                INDEX idx_block_status (block_id, status),
                INDEX idx_project_id (project_id),
                FOREIGN KEY (block_id) REFERENCES agenda_blocks(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_block_segments");
    }
}
