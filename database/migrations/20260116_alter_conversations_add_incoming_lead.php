<?php

/**
 * Migration: Adiciona campo is_incoming_lead na tabela conversations
 * 
 * Marca conversas de números desconhecidos como "Incoming Leads"
 * (estilo Kommo CRM - leads entrantes que precisam ser revisados)
 */
class AlterConversationsAddIncomingLead
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE conversations
            ADD COLUMN is_incoming_lead TINYINT(1) NOT NULL DEFAULT 0 
                COMMENT '1 = lead entrante (número não cadastrado), precisa revisão' 
                AFTER tenant_id,
            ADD INDEX idx_incoming_lead (is_incoming_lead, tenant_id)
        ");
        
        // Atualiza conversas existentes: se tenant_id é NULL, marca como incoming_lead
        $db->exec("
            UPDATE conversations 
            SET is_incoming_lead = 1 
            WHERE tenant_id IS NULL 
              AND channel_type = 'whatsapp'
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE conversations
            DROP INDEX idx_incoming_lead,
            DROP COLUMN is_incoming_lead
        ");
    }
}

