<?php

/**
 * Migration: Cria tabela de log de webhooks do Asaas
 */
class CreateAsaasWebhookLogsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS asaas_webhook_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event VARCHAR(100) NULL,
                payload LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_event (event),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS asaas_webhook_logs");
    }
}

