<?php

/**
 * Migration: Adiciona campos lead_id e opportunity_id à tabela agenda_manual_items
 * Permite vincular compromissos da agenda a leads e oportunidades
 */
class AlterAgendaManualItemsAddLeadOpportunity
{
    public function up(PDO $db): void
    {
        // Adiciona colunas para vincular agenda items a leads e oportunidades
        $db->exec("
            ALTER TABLE agenda_manual_items
            ADD COLUMN lead_id INT UNSIGNED NULL COMMENT 'FK para leads' AFTER created_by,
            ADD COLUMN opportunity_id INT UNSIGNED NULL COMMENT 'FK para opportunities' AFTER lead_id,
            ADD COLUMN related_type VARCHAR(20) NULL COMMENT 'Tipo de vínculo: lead, opportunity, tenant, null' AFTER opportunity_id,
            ADD INDEX idx_lead_id (lead_id),
            ADD INDEX idx_opportunity_id (opportunity_id),
            ADD INDEX idx_related_type (related_type)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE agenda_manual_items
            DROP INDEX idx_related_type,
            DROP INDEX idx_opportunity_id,
            DROP INDEX idx_lead_id,
            DROP COLUMN related_type,
            DROP COLUMN opportunity_id,
            DROP COLUMN lead_id
        ");
    }
}
