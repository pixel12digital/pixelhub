<?php

/**
 * Migration: Atualiza canal pixel12digital para usar provider 'whapi'
 * 
 * Remove todas as referências ao WPPConnect Gateway da tabela tenant_message_channels.
 * O canal pixel12digital agora usa Whapi.Cloud como provider.
 */

class UpdateChannelProviderToWhapi
{
    public function up(\PDO $db): void
    {
        // 1. Garante que 'whapi' é aceito na coluna provider (VARCHAR ou ENUM)
        // Verifica se a coluna provider é ENUM
        try {
            $col = $db->query("
                SELECT COLUMN_TYPE 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'tenant_message_channels' 
                AND COLUMN_NAME = 'provider'
            ")->fetchColumn();
            
            if ($col && strpos($col, 'enum') !== false && strpos($col, 'whapi') === false) {
                // É ENUM, precisa adicionar 'whapi'
                $newType = str_replace("')", ",'whapi')", $col);
                $db->exec("ALTER TABLE tenant_message_channels MODIFY COLUMN provider {$newType}");
                echo "  ✅ 'whapi' adicionado ao ENUM provider em tenant_message_channels\n";
            } else {
                echo "  ℹ️ Coluna provider é VARCHAR ou já inclui 'whapi'\n";
            }
        } catch (\Exception $e) {
            echo "  ⚠️ Aviso ao verificar coluna provider: " . $e->getMessage() . "\n";
        }

        // 2. Atualiza todos os canais com provider='wpp_gateway' para 'whapi'
        try {
            $affected = $db->exec("
                UPDATE tenant_message_channels 
                SET provider = 'whapi', provider_type = 'whapi'
                WHERE provider = 'wpp_gateway'
            ");
            echo "  ✅ {$affected} canal(is) atualizado(s): wpp_gateway → whapi\n";
        } catch (\Exception $e) {
            echo "  ⚠️ Erro ao atualizar canais: " . $e->getMessage() . "\n";
        }

        // 3. Verifica o resultado
        $channels = $db->query("
            SELECT id, tenant_id, channel_id, provider, provider_type, is_enabled 
            FROM tenant_message_channels 
            ORDER BY id ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);
        
        echo "\n  📋 Estado atual de tenant_message_channels:\n";
        foreach ($channels as $ch) {
            echo "    - ID {$ch['id']}: channel_id={$ch['channel_id']}, provider={$ch['provider']}, provider_type={$ch['provider_type']}, enabled={$ch['is_enabled']}\n";
        }
    }

    public function down(\PDO $db): void
    {
        $db->exec("
            UPDATE tenant_message_channels 
            SET provider = 'wpp_gateway', provider_type = 'wppconnect'
            WHERE provider = 'whapi'
        ");
        echo "  Revertido: whapi → wpp_gateway\n";
    }
}
