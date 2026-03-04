<?php

/**
 * Migration: Adicionar campos de chatbot à tabela conversations
 * 
 * Adiciona campos para rastrear estado do chatbot e origem da conversa
 * 
 * Data: 2026-03-04
 */
class AlterConversationsAddChatbotFields
{
    public function up(PDO $db): void
    {
        echo "Adicionando campos de chatbot à tabela conversations...\n";
        
        // Verifica se a coluna já existe antes de adicionar
        $checkStmt = $db->query("SHOW COLUMNS FROM conversations LIKE 'bot_stage'");
        if ($checkStmt->rowCount() === 0) {
            $db->exec("
            ALTER TABLE conversations
            ADD COLUMN bot_stage VARCHAR(100) NULL COMMENT 'Estágio atual no fluxo do chatbot' AFTER status,
            ADD COLUMN bot_last_flow_id INT UNSIGNED NULL COMMENT 'Último fluxo executado' AFTER bot_stage,
            ADD COLUMN bot_context JSON NULL COMMENT 'Contexto/variáveis do chatbot' AFTER bot_last_flow_id,
            ADD COLUMN is_bot_active BOOLEAN DEFAULT 0 COMMENT 'Se chatbot está ativo nesta conversa' AFTER bot_context,
            ADD COLUMN campaign_id INT UNSIGNED NULL COMMENT 'Campanha que originou a conversa' AFTER is_bot_active,
            ADD KEY idx_bot_stage (bot_stage),
            ADD KEY idx_bot_active (is_bot_active),
            ADD KEY idx_campaign (campaign_id)
            ");
            echo "✓ Campos de chatbot adicionados com sucesso\n";
        } else {
            echo "⊙ Campos de chatbot já existem, pulando...\n";
        }
    }
    
    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE conversations
            DROP COLUMN bot_stage,
            DROP COLUMN bot_last_flow_id,
            DROP COLUMN bot_context,
            DROP COLUMN is_bot_active,
            DROP COLUMN campaign_id
        ");
    }
}
