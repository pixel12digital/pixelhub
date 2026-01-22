<?php

/**
 * Migration: Cria tabela tenant_users
 */
class CreateTenantUsersTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenant_users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'admin_cliente',
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY unique_tenant_user (tenant_id, user_id),
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenant_users");
    }
}

