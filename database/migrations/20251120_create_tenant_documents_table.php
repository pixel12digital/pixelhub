<?php

/**
 * Migration: Cria tabela tenant_documents
 */
class CreateTenantDocumentsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenant_documents (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                category VARCHAR(50) NULL,
                file_name VARCHAR(255) NULL,
                original_name VARCHAR(255) NULL,
                mime_type VARCHAR(100) NULL,
                file_size BIGINT UNSIGNED NULL,
                stored_path VARCHAR(500) NULL,
                link_url VARCHAR(500) NULL,
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_category (category),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenant_documents");
    }
}

