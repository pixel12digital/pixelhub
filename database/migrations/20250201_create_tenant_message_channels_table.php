<?php

/**
 * Migration: Cria tabela tenant_message_channels
 * 
 * Mapeia tenants para canais de comunicação (WhatsApp, etc.)
 */
class CreateTenantMessageChannelsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenant_message_channels (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                provider VARCHAR(50) NOT NULL DEFAULT 'wpp_gateway' COMMENT 'Provedor: wpp_gateway, etc.',
                channel_id VARCHAR(100) NOT NULL COMMENT 'ID do channel no provedor',
                is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                webhook_configured BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Se webhook foi configurado',
                metadata JSON NULL COMMENT 'Metadados do channel (status, qr, etc.)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY unique_tenant_provider (tenant_id, provider),
                INDEX idx_channel_id (channel_id),
                INDEX idx_provider (provider),
                INDEX idx_is_enabled (is_enabled),
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenant_message_channels");
    }
}

