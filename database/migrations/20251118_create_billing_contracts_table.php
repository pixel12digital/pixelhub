<?php

/**
 * Migration: Cria tabela de contratos de cobrança
 */
class CreateBillingContractsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_contracts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                hosting_account_id INT UNSIGNED NULL,
                hosting_plan_id INT UNSIGNED NULL,
                plan_snapshot_name VARCHAR(255) NOT NULL,
                billing_mode VARCHAR(20) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                annual_total_amount DECIMAL(10,2) NULL,
                asaas_subscription_id VARCHAR(100) NULL,
                asaas_external_reference VARCHAR(255) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ativo',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_asaas_subscription_id (asaas_subscription_id),
                INDEX idx_hosting_account_id (hosting_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_contracts");
    }
}

