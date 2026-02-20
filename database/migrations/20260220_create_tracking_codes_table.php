<?php

/**
 * Migration: Cria tabela de configurações de códigos de rastreio
 * 
 * Permite cadastrar manualmente os códigos e suas fontes para validação
 */
class CreateTrackingCodesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tracking_codes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL COMMENT 'Código de rastreamento (ex: SITE123)',
                source VARCHAR(50) NOT NULL COMMENT 'Fonte: site, instagram, facebook, whatsapp, google, email, indicacao, outro',
                description TEXT NULL COMMENT 'Descrição opcional do código',
                is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Se o código está ativo para detecção',
                created_by INT UNSIGNED NULL COMMENT 'Quem cadastrou',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY uk_code (code),
                INDEX idx_source (source),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tracking_codes");
    }
}
