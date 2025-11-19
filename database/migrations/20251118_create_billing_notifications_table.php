<?php

class CreateBillingNotificationsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                invoice_id INT UNSIGNED NULL,
                channel VARCHAR(30) NOT NULL DEFAULT 'whatsapp_web',
                template VARCHAR(50) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'prepared',
                message TEXT NULL,
                phone_raw VARCHAR(50) NULL,
                phone_normalized VARCHAR(30) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                sent_at DATETIME NULL,
                last_error TEXT NULL,
                INDEX idx_billing_notifications_tenant (tenant_id),
                INDEX idx_billing_notifications_invoice (invoice_id),
                INDEX idx_billing_notifications_status (status),
                CONSTRAINT fk_billing_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_billing_notifications_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_notifications");
    }
}

