<?php

/**
 * Migration: Adiciona campos para enriquecimento com dados do Google Maps
 */
class AlterProspectingResultsAddGoogleEnrichment
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN google_enriched_at DATETIME NULL COMMENT 'Data do enriquecimento com Google Maps' AFTER qsa,
                ADD COLUMN enrichment_confidence TINYINT NULL COMMENT 'Score de confiança do matching (0-100)' AFTER google_enriched_at
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP COLUMN google_enriched_at,
                DROP COLUMN enrichment_confidence
        ");
    }
}
