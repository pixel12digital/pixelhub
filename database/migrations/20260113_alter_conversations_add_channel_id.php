<?php

/**
 * Migration: Adiciona campo channel_id na tabela conversations
 * 
 * Este campo armazena o session.id do gateway WhatsApp, permitindo
 * que a regra "último inbound event" vire apenas fallback.
 */
class AlterConversationsAddChannelId
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM conversations LIKE 'channel_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE conversations
                ADD COLUMN channel_id VARCHAR(100) NULL COMMENT 'ID do channel no gateway (session.id para WhatsApp)' AFTER channel_account_id,
                ADD INDEX idx_channel_id (channel_id)
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE conversations
            DROP INDEX idx_channel_id,
            DROP COLUMN channel_id
        ");
    }
}

