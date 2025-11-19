<?php

/**
 * Migration: Cria tabela projects
 */
class CreateProjectsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS projects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                external_project_id VARCHAR(100) NULL,
                base_url VARCHAR(255) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_slug (slug),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS projects");
    }
}

