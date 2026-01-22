<?php

/**
 * Migration: Cria tabela screen_recordings (Biblioteca geral de gravações de tela)
 */
class CreateScreenRecordingsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS screen_recordings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id INT UNSIGNED NULL,
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime_type VARCHAR(100) NULL,
                size_bytes BIGINT UNSIGNED NULL,
                duration_seconds INT UNSIGNED NULL,
                has_audio TINYINT(1) DEFAULT 0,
                public_token VARCHAR(64) UNIQUE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_by INT UNSIGNED NULL,
                INDEX idx_task_id (task_id),
                INDEX idx_public_token (public_token),
                INDEX idx_created_at (created_at),
                INDEX idx_created_by (created_by),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS screen_recordings");
    }
}












