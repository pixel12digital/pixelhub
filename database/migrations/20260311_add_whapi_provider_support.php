<?php

/**
 * Migration: Adiciona suporte ao provider Whapi.Cloud
 * 
 * 1. Altera ENUM provider_type em tenant_message_channels para incluir 'whapi'
 * 2. Altera ENUM provider_type em whatsapp_provider_configs para incluir 'whapi'
 * 3. Adiciona campos whapi_api_token e whapi_channel_id em whatsapp_provider_configs
 * 4. Atualiza sessão pixel12digital para usar whapi
 */

class AddWhapiProviderSupport
{
    public function up(\PDO $db): void
    {
        // 1. Altera ENUM em tenant_message_channels
        try {
            $db->exec("
                ALTER TABLE tenant_message_channels 
                MODIFY COLUMN provider_type ENUM('wppconnect', 'meta_official', 'whapi') 
                NOT NULL DEFAULT 'wppconnect'
            ");
            echo "  ✅ tenant_message_channels.provider_type atualizado com 'whapi'\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), "Unknown column") !== false || strpos($e->getMessage(), "doesn't exist") !== false) {
                echo "  ⚠️ Coluna provider_type não existe em tenant_message_channels, pulando\n";
            } else {
                echo "  ⚠️ Erro ao alterar tenant_message_channels: " . $e->getMessage() . "\n";
            }
        }

        // 2. Altera ENUM em whatsapp_provider_configs
        try {
            $db->exec("
                ALTER TABLE whatsapp_provider_configs 
                MODIFY COLUMN provider_type ENUM('wppconnect', 'meta_official', 'whapi') 
                NOT NULL DEFAULT 'wppconnect'
            ");
            echo "  ✅ whatsapp_provider_configs.provider_type atualizado com 'whapi'\n";
        } catch (\PDOException $e) {
            echo "  ⚠️ Erro ao alterar whatsapp_provider_configs: " . $e->getMessage() . "\n";
        }

        // 3. Adiciona campos Whapi em whatsapp_provider_configs
        try {
            $db->exec("
                ALTER TABLE whatsapp_provider_configs 
                ADD COLUMN whapi_api_token TEXT NULL AFTER meta_webhook_verify_token,
                ADD COLUMN whapi_channel_id VARCHAR(100) NULL AFTER whapi_api_token
            ");
            echo "  ✅ Campos whapi_api_token e whapi_channel_id adicionados\n";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  ℹ️ Campos whapi já existem, pulando\n";
            } else {
                echo "  ⚠️ Erro ao adicionar campos whapi: " . $e->getMessage() . "\n";
            }
        }

        // 4. Insere configuração global Whapi (token será configurado via interface)
        try {
            // Verifica se já existe config whapi
            $stmt = $db->query("SELECT id FROM whatsapp_provider_configs WHERE provider_type = 'whapi' AND is_global = 1 LIMIT 1");
            $existing = $stmt->fetch();
            
            if (!$existing) {
                $db->exec("
                    INSERT INTO whatsapp_provider_configs 
                    (provider_type, is_global, is_active, created_at, updated_at)
                    VALUES ('whapi', 1, 0, NOW(), NOW())
                ");
                echo "  ✅ Config global Whapi criada (inativa - configure o API token)\n";
            } else {
                echo "  ℹ️ Config global Whapi já existe (id={$existing['id']})\n";
            }
        } catch (\PDOException $e) {
            echo "  ⚠️ Erro ao inserir config Whapi: " . $e->getMessage() . "\n";
        }

        echo "\n  📋 PRÓXIMOS PASSOS:\n";
        echo "  1. Configure o API Token do Whapi.Cloud na tabela whatsapp_provider_configs\n";
        echo "  2. No painel Whapi.Cloud, configure o webhook: https://hub.pixel12digital.com.br/api/whatsapp/whapi/webhook\n";
        echo "  3. Ative Auto Download para Image, Audio, Voice, Video, Document\n";
        echo "  4. Ative a config (is_active = 1) quando estiver pronto\n";
    }

    public function down(\PDO $db): void
    {
        // Remove config Whapi
        $db->exec("DELETE FROM whatsapp_provider_configs WHERE provider_type = 'whapi'");
        
        // Remove campos
        try {
            $db->exec("ALTER TABLE whatsapp_provider_configs DROP COLUMN whapi_api_token, DROP COLUMN whapi_channel_id");
        } catch (\PDOException $e) { /* ignora */ }

        // Reverte ENUMs
        try {
            $db->exec("ALTER TABLE tenant_message_channels MODIFY COLUMN provider_type ENUM('wppconnect', 'meta_official') NOT NULL DEFAULT 'wppconnect'");
        } catch (\PDOException $e) { /* ignora */ }

        try {
            $db->exec("ALTER TABLE whatsapp_provider_configs MODIFY COLUMN provider_type ENUM('wppconnect', 'meta_official') NOT NULL DEFAULT 'wppconnect'");
        } catch (\PDOException $e) { /* ignora */ }
    }
}
