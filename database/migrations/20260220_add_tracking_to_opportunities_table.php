<?php

/**
 * Migration: Adiciona campos de tracking à tabela opportunities
 * 
 * Implementa sistema de rastreamento de origem de leads:
 * - tracking_code: código extraído da mensagem (ex: "SITE123", "IG456")
 * - tracking_source: fonte do código (site, instagram, whatsapp, etc)
 * - auto_detected: se o código foi extraído automaticamente
 */
class AddTrackingToOpportunitiesTable
{
    public function up(PDO $db): void
    {
        // Adiciona campos de tracking à tabela opportunities
        $db->exec("
            ALTER TABLE opportunities 
            ADD COLUMN tracking_code VARCHAR(100) NULL COMMENT 'Código de rastreamento extraído da mensagem (ex: SITE123)',
            ADD COLUMN tracking_source VARCHAR(50) NULL COMMENT 'Fonte do código: site, instagram, whatsapp, indicacao, outro',
            ADD COLUMN tracking_auto_detected BOOLEAN NULL DEFAULT FALSE COMMENT 'Se o código foi detectado automaticamente',
            ADD COLUMN tracking_metadata JSON NULL COMMENT 'Metadados do tracking (data/hora detecção, mensagem original, etc)',
            ADD INDEX idx_tracking_code (tracking_code),
            ADD INDEX idx_tracking_source (tracking_source)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE opportunities 
            DROP INDEX idx_tracking_code,
            DROP INDEX idx_tracking_source,
            DROP COLUMN tracking_code,
            DROP COLUMN tracking_source,
            DROP COLUMN tracking_auto_detected,
            DROP COLUMN tracking_metadata
        ");
    }
}
