<?php

/**
 * Migration: Adiciona índice em communication_events.conversation_id
 *
 * OTIMIZAÇÃO DE PERFORMANCE: A coluna conversation_id existe mas não tem índice.
 * Sem índice, toda abertura de conversa faz full table scan em communication_events.
 * Com índice, a busca por conversation_id passa de O(n) para O(log n).
 *
 * Seguro: apenas adiciona índice, não altera dados nem estrutura de colunas.
 */
class AddIndexCommunicationEventsConversationId
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW INDEX FROM communication_events WHERE Key_name = 'idx_conversation_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                CREATE INDEX idx_conversation_id
                ON communication_events (conversation_id)
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP INDEX IF EXISTS idx_conversation_id ON communication_events");
    }
}
