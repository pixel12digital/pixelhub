<?php

/**
 * Migration: Cria tabela owner_shortcuts
 */
class CreateOwnerShortcutsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS owner_shortcuts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(50) NOT NULL,
                label VARCHAR(150) NOT NULL,
                url VARCHAR(255) NOT NULL,
                username VARCHAR(150) NULL,
                password_encrypted TEXT NULL,
                notes TEXT NULL,
                is_favorite TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_category (category),
                INDEX idx_is_favorite (is_favorite)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS owner_shortcuts");
    }
}

