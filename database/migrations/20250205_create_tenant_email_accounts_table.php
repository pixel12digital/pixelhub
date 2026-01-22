<?php

/**
 * Migration: Cria tabela tenant_email_accounts
 * 
 * Esta tabela armazena contas de email profissionais dos clientes.
 * Cada conta pode estar vinculada a um hosting_account_id específico ou apenas ao tenant.
 */
class CreateTenantEmailAccountsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenant_email_accounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                hosting_account_id INT UNSIGNED NULL,
                email VARCHAR(255) NOT NULL,
                description VARCHAR(255) NULL,
                provider VARCHAR(100) NULL,
                access_url VARCHAR(500) NULL,
                username VARCHAR(255) NULL,
                password_encrypted TEXT NULL,
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_hosting_account_id (hosting_account_id),
                INDEX idx_email (email),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (hosting_account_id) REFERENCES hosting_accounts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenant_email_accounts");
    }
}



