<?php

/**
 * Migration: Adiciona provider_type em tenant_message_channels
 * 
 * Permite diferenciar entre WPPConnect e Meta Official API
 * DEFAULT 'wppconnect' garante que todos registros existentes continuam funcionando
 */
class AlterTenantMessageChannelsAddProviderType
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $stmt = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'provider_type'");
        if ($stmt->rowCount() > 0) {
            echo "Coluna provider_type já existe, pulando...\n";
            return;
        }

        $db->exec("
            ALTER TABLE tenant_message_channels 
            ADD COLUMN provider_type ENUM('wppconnect', 'meta_official') 
            NOT NULL DEFAULT 'wppconnect' 
            AFTER provider
        ");

        echo "✓ Coluna provider_type adicionada com sucesso (default: wppconnect)\n";
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tenant_message_channels DROP COLUMN provider_type");
    }
}
