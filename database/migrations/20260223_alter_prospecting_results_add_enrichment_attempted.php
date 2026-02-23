<?php

/**
 * Migration: Adiciona campo para rastrear tentativas de enriquecimento Google Maps
 */
class AlterProspectingResultsAddEnrichmentAttempted
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN google_enrichment_attempted TINYINT(1) DEFAULT 0 
                    COMMENT 'Se já tentou enriquecer com Google Maps (0=não tentou, 1=tentou)' 
                    AFTER enrichment_confidence
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP COLUMN google_enrichment_attempted
        ");
    }
}
