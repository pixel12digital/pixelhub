<?php

/**
 * Migration: Cria tabela de faturas/cobranças
 */
class CreateBillingInvoicesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_invoices (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                billing_contract_id INT UNSIGNED NULL,
                asaas_payment_id VARCHAR(100) NOT NULL,
                asaas_customer_id VARCHAR(100) NULL,
                due_date DATE NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) NOT NULL,
                paid_at DATETIME NULL,
                invoice_url VARCHAR(512) NULL,
                billing_type VARCHAR(20) NULL,
                description VARCHAR(255) NULL,
                external_reference VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_asaas_payment_id (asaas_payment_id),
                INDEX idx_billing_contract_id (billing_contract_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_invoices");
    }
}

