<?php

/**
 * Migration: Cria tabela para persistir payload bruto de webhooks WhatsApp
 *
 * Permite rastrear o que chegou e reprocessar manualmente se a ingestão falhar.
 * Referência: docs/PLANO_ESTRATEGICO_CONFIABILIDADE_INBOX.md
 */
class CreateWebhookRawLogsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS webhook_raw_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                event_type VARCHAR(100) NULL,
                payload_hash VARCHAR(16) NULL,
                payload_json LONGTEXT NOT NULL,
                processed TINYINT(1) NOT NULL DEFAULT 0,
                event_id VARCHAR(36) NULL,
                error_message VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_event_type (event_type),
                INDEX idx_received_at (received_at),
                INDEX idx_payload_hash (payload_hash),
                INDEX idx_processed (processed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS webhook_raw_logs");
    }
}
