<?php

/**
 * Migration: Adiciona índice composto para otimizar listagem de conversas
 * 
 * Otimiza queries que filtram por channel_type + status e ordenam por last_message_at.
 * Ex.: listagem de conversas ativas, ignoradas, arquivadas do WhatsApp.
 * 
 * Seguro: apenas adiciona índice, não altera dados nem estrutura de colunas.
 */
class AddCompositeIndexConversations
{
    public function up(PDO $db): void
    {
        // Verifica se o índice já existe antes de criar
        $stmt = $db->query("SHOW INDEX FROM conversations WHERE Key_name = 'idx_conv_type_status_lastmsg'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                CREATE INDEX idx_conv_type_status_lastmsg 
                ON conversations (channel_type, status, last_message_at DESC)
            ");
            error_log("[Migration] Índice idx_conv_type_status_lastmsg criado com sucesso");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP INDEX IF EXISTS idx_conv_type_status_lastmsg ON conversations");
    }
}
