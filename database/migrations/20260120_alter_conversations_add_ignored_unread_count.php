<?php

/**
 * Migration: Adiciona campo ignored_unread_count na tabela conversations
 * 
 * Contador de mensagens não lidas em conversas ignoradas.
 * Quando uma conversa está com status='ignored' e recebe nova mensagem,
 * este contador é incrementado para permitir sinalização visual futura.
 */
class AlterConversationsAddIgnoredUnreadCount
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE conversations
            ADD COLUMN ignored_unread_count INT UNSIGNED NOT NULL DEFAULT 0 
                COMMENT 'Contador de mensagens não lidas em conversas ignoradas' 
                AFTER unread_count
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE conversations
            DROP COLUMN ignored_unread_count
        ");
    }
}

