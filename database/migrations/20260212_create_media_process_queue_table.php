<?php

/**
 * Migration: Cria tabela de fila para processamento de mídia WhatsApp
 *
 * Eventos inbound com mídia são enfileirados; worker processa assincronamente.
 * Evita timeout no webhook e permite retry com backoff.
 * Referência: docs/PLANO_ESTRATEGICO_CONFIABILIDADE_INBOX.md
 */
class CreateMediaProcessQueueTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS media_process_queue (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(36) NOT NULL,
                status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
                last_attempt_at DATETIME NULL,
                error_message VARCHAR(500) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_event_id (event_id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS media_process_queue");
    }
}
