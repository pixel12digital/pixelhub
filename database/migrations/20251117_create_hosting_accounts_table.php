<?php

/**
 * Migration: Cria tabela hosting_accounts
 */
class CreateHostingAccountsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS hosting_accounts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                domain VARCHAR(255) NOT NULL,
                provider VARCHAR(100) NOT NULL DEFAULT 'hostinger',
                current_provider VARCHAR(50) NOT NULL DEFAULT 'hostinger',
                hostinger_expiration_date DATE NULL,
                decision VARCHAR(50) NOT NULL DEFAULT 'pendente',
                backup_status VARCHAR(50) NOT NULL DEFAULT 'nenhum',
                migration_status VARCHAR(50) NOT NULL DEFAULT 'nao_iniciada',
                notes TEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_domain (domain),
                INDEX idx_current_provider (current_provider),
                INDEX idx_decision (decision),
                INDEX idx_backup_status (backup_status),
                INDEX idx_migration_status (migration_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS hosting_accounts");
    }
}

