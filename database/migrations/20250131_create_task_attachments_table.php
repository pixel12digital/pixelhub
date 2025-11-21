<?php

/**
 * Migration: Cria tabela task_attachments (Anexos de Tarefas)
 */
class CreateTaskAttachmentsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS task_attachments (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NULL,
                task_id INT UNSIGNED NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size BIGINT UNSIGNED NULL,
                mime_type VARCHAR(100) NULL,
                uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                uploaded_by INT UNSIGNED NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_task_id (task_id),
                INDEX idx_uploaded_at (uploaded_at),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS task_attachments");
    }
}

