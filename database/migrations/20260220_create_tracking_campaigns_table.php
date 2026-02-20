<?php

/**
 * Migration: Cria tabela de campanhas de rastreamento
 * 
 * Relaciona códigos com campanhas específicas (Ads, orgânico, etc)
 */
class CreateTrackingCampaignsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tracking_campaigns (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tracking_code_id INT UNSIGNED NOT NULL COMMENT 'FK para tracking_codes',
                name VARCHAR(255) NOT NULL COMMENT 'Nome da campanha',
                channel ENUM('organic', 'ads', 'social', 'email', 'referral', 'direct', 'other') NOT NULL DEFAULT 'organic' COMMENT 'Canal da campanha',
                platform VARCHAR(50) NULL COMMENT 'Plataforma: Google Ads, Facebook Ads, Instagram, etc',
                destination_url TEXT NULL COMMENT 'URL de destino da campanha',
                description TEXT NULL COMMENT 'Descrição detalhada',
                is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Se a campanha está ativa',
                created_by INT UNSIGNED NULL COMMENT 'Quem cadastrou',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_tracking_code_id (tracking_code_id),
                INDEX idx_channel (channel),
                INDEX idx_platform (platform),
                INDEX idx_active (is_active),
                FOREIGN KEY (tracking_code_id) REFERENCES tracking_codes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tracking_campaigns");
    }
}
