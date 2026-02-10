<?php

/**
 * Migration: Cria tabela de log de envios de cobrança
 * 
 * Fonte única de verdade para dedupe:
 * - tenant_id + invoice_id + channel + template_key + sent_at
 * - Cooldown configurável por canal/template
 * - Controle de envios forçados
 */
class CreateBillingDispatchLogTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_dispatch_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                invoice_id INT UNSIGNED NOT NULL,
                channel VARCHAR(20) NOT NULL,
                template_key VARCHAR(50) NOT NULL,
                sent_at DATETIME NOT NULL,
                trigger_source ENUM('automatic', 'manual') NOT NULL,
                triggered_by_user_id INT UNSIGNED NULL,
                is_forced TINYINT(1) NOT NULL DEFAULT 0,
                force_reason VARCHAR(255) NULL,
                message_id VARCHAR(100) NULL COMMENT 'ID da mensagem no gateway',
                status ENUM('sent', 'delivered', 'read', 'failed') NOT NULL DEFAULT 'sent',
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bdl_tenant_invoice (tenant_id, invoice_id),
                INDEX idx_bdl_channel_template (channel, template_key),
                INDEX idx_bdl_sent_at (sent_at),
                INDEX idx_bdl_cooldown (tenant_id, invoice_id, channel, template_key, sent_at),
                CONSTRAINT fk_bdl_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_bdl_invoice FOREIGN KEY (invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
                CONSTRAINT fk_bdl_user FOREIGN KEY (triggered_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_dispatch_log");
    }
}
