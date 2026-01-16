<?php

/**
 * Migration: Cria tabela communication_media
 * 
 * Armazena informações sobre mídias recebidas via WhatsApp.
 */
class CreateCommunicationMediaTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS communication_media (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(36) NOT NULL UNIQUE COMMENT 'UUID do evento (FK para communication_events)',
                media_id VARCHAR(255) NOT NULL COMMENT 'ID da mídia no WhatsApp Gateway',
                media_type VARCHAR(50) NOT NULL COMMENT 'Tipo de mídia (audio, image, video, document, sticker)',
                mime_type VARCHAR(100) NULL COMMENT 'MIME type do arquivo',
                stored_path VARCHAR(500) NULL COMMENT 'Caminho relativo do arquivo armazenado',
                file_name VARCHAR(255) NULL COMMENT 'Nome do arquivo',
                file_size INT UNSIGNED NULL COMMENT 'Tamanho do arquivo em bytes',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_event_id (event_id),
                INDEX idx_media_id (media_id),
                INDEX idx_media_type (media_type),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (event_id) REFERENCES communication_events(event_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS communication_media");
    }
}

