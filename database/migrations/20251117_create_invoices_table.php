<?php

/**
 * Migration: Cria tabela invoices
 */
class CreateInvoicesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS invoices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                subscription_id INT UNSIGNED NULL,
                asaas_invoice_id VARCHAR(100) NULL,
                amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                due_date DATE NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                invoice_url VARCHAR(500) NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_subscription_id (subscription_id),
                INDEX idx_asaas_invoice_id (asaas_invoice_id),
                INDEX idx_status (status),
                INDEX idx_due_date (due_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS invoices");
    }
}

