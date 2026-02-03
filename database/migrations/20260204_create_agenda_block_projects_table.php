<?php

/**
 * Migration: Cria tabela agenda_block_projects
 * Permite pré-vincular múltiplos projetos ao bloco (sem remover projeto_foco_id)
 */
class CreateAgendaBlockProjectsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_block_projects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                block_id INT UNSIGNED NOT NULL,
                project_id INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_block_project (block_id, project_id),
                INDEX idx_block_id (block_id),
                INDEX idx_project_id (project_id),
                FOREIGN KEY (block_id) REFERENCES agenda_blocks(id) ON DELETE CASCADE,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_block_projects");
    }
}
