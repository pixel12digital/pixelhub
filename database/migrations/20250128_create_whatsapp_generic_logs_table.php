<?php

/**
 * Migration: Cria tabela de logs de envios genéricos de WhatsApp
 * 
 * Registra envios de mensagens genéricas (não relacionadas a cobrança)
 * para histórico e auditoria.
 */
class CreateWhatsappGenericLogsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS whatsapp_generic_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                template_id INT UNSIGNED NULL,
                phone VARCHAR(30) NOT NULL,
                message TEXT NOT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tenant (tenant_id),
                INDEX idx_template (template_id),
                INDEX idx_sent_at (sent_at),
                CONSTRAINT fk_whatsapp_generic_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_whatsapp_generic_logs_template FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS whatsapp_generic_logs");
    }
}

