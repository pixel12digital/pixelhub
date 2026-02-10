<?php

/**
 * Migration: Cria tabela de fila de envio de cobranças
 * 
 * Padrão idêntico a media_process_queue:
 * - enqueue → worker consome → markDone/markFailed
 * - scheduled_at controla quando o envio deve ocorrer (distribuição na janela da manhã)
 */
class CreateBillingDispatchQueueTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_dispatch_queue (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                invoice_ids JSON NOT NULL COMMENT 'Array de IDs de faturas elegíveis',
                dispatch_rule_id INT UNSIGNED NULL COMMENT 'Regra que originou o enfileiramento',
                channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
                message TEXT NULL COMMENT 'Mensagem pré-montada (null = montar na hora do envio)',
                status ENUM('queued','processing','sent','failed') NOT NULL DEFAULT 'queued',
                scheduled_at DATETIME NOT NULL COMMENT 'Horário agendado para envio (distribuído na janela)',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
                last_attempt_at DATETIME NULL,
                error_message TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_bdq_status_scheduled (status, scheduled_at),
                INDEX idx_bdq_tenant (tenant_id),
                INDEX idx_bdq_created (created_at),
                CONSTRAINT fk_bdq_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_dispatch_queue");
    }
}
