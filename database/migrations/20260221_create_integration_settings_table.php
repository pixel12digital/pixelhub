<?php

/**
 * Migration: Cria tabela integration_settings
 * 
 * Armazena chaves de API e configurações de integrações externas no banco,
 * evitando dependência exclusiva do arquivo .env.
 * Chaves sensíveis são armazenadas criptografadas.
 */
class CreateIntegrationSettingsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS integration_settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                integration_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Identificador da integração (ex: google_maps_api_key)',
                integration_value TEXT NULL COMMENT 'Valor (pode ser criptografado)',
                is_encrypted TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = valor está criptografado',
                label VARCHAR(255) NULL COMMENT 'Nome amigável para exibição',
                notes TEXT NULL COMMENT 'Observações sobre a configuração',
                updated_by INT UNSIGNED NULL COMMENT 'FK para users (quem atualizou)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_integration_key (integration_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS integration_settings");
    }
}
