<?php

/**
 * Migration: Cria tabela hosting_backups
 */
class CreateHostingBackupsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS hosting_backups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                hosting_account_id INT UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'all_in_one_wp',
                file_name VARCHAR(255) NOT NULL,
                file_size BIGINT UNSIGNED NULL,
                stored_path VARCHAR(500) NOT NULL,
                notes TEXT NULL,
                created_at DATETIME NULL,
                INDEX idx_hosting_account_id (hosting_account_id),
                INDEX idx_type (type),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS hosting_backups");
    }
}

