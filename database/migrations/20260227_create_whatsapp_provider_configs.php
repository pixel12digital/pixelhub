<?php

/**
 * Migration: Cria tabela whatsapp_provider_configs
 * 
 * Armazena configurações específicas de cada provider WhatsApp por tenant
 * Suporta múltiplos providers (WPPConnect, Meta Official API)
 */
class CreateWhatsappProviderConfigs
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS whatsapp_provider_configs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL COMMENT 'FK para tenants',
                provider_type ENUM('wppconnect', 'meta_official') NOT NULL COMMENT 'Tipo de provider',
                
                -- Configurações Meta Official API
                meta_phone_number_id VARCHAR(100) NULL COMMENT 'Phone Number ID do Meta',
                meta_access_token TEXT NULL COMMENT 'Access Token do Meta (criptografado)',
                meta_business_account_id VARCHAR(100) NULL COMMENT 'WhatsApp Business Account ID',
                meta_webhook_verify_token VARCHAR(255) NULL COMMENT 'Token para verificação de webhook',
                
                -- Configurações WPPConnect (para compatibilidade futura)
                wppconnect_session_id VARCHAR(100) NULL COMMENT 'Session ID no gateway WPPConnect',
                
                -- Metadados gerais
                config_metadata JSON NULL COMMENT 'Configurações adicionais específicas do provider',
                is_active BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Se esta configuração está ativa',
                
                -- Auditoria
                created_by INT UNSIGNED NULL COMMENT 'FK para users (quem criou)',
                updated_by INT UNSIGNED NULL COMMENT 'FK para users (quem atualizou)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                -- Índices e constraints
                UNIQUE KEY unique_tenant_provider (tenant_id, provider_type),
                INDEX idx_provider_type (provider_type),
                INDEX idx_is_active (is_active),
                INDEX idx_meta_phone_number_id (meta_phone_number_id),
                
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Configurações de providers WhatsApp por tenant (Meta Official API, WPPConnect, etc.)'
        ");

        echo "✓ Tabela whatsapp_provider_configs criada com sucesso\n";
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS whatsapp_provider_configs");
    }
}
