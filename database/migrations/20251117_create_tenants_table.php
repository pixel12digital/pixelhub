<?php

/**
 * Migration: Cria tabela tenants
 */
class CreateTenantsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenants (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                document VARCHAR(20) NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(20) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                asaas_customer_id VARCHAR(100) NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_status (status),
                INDEX idx_asaas_customer_id (asaas_customer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenants");
    }
}

