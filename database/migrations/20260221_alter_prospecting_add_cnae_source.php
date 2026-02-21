<?php

/**
 * Migration: Adiciona suporte a CNAE (CNPJ.ws) na Prospecção Ativa
 *
 * prospecting_recipes: adiciona source, cnae_code, cnae_description
 * prospecting_results: adiciona source, cnpj
 */
class AlterProspectingAddCnaeSource
{
    public function up(PDO $db): void
    {
        // prospecting_recipes: coluna source (default google_maps para retrocompatibilidade)
        $db->exec("
            ALTER TABLE prospecting_recipes
                ADD COLUMN source ENUM('google_maps', 'cnpjws') NOT NULL DEFAULT 'google_maps'
                    COMMENT 'Fonte da prospecção' AFTER name,
                ADD COLUMN cnae_code VARCHAR(10) NULL
                    COMMENT 'Código CNAE (ex: 6822-6/00)' AFTER google_place_type,
                ADD COLUMN cnae_description VARCHAR(255) NULL
                    COMMENT 'Descrição do CNAE para exibição' AFTER cnae_code,
                ADD INDEX idx_source (source)
        ");

        // prospecting_results: coluna source e cnpj (nullable para resultados Google existentes)
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN source VARCHAR(30) NULL
                    COMMENT 'Fonte: google_maps ou cnpjws' AFTER google_types,
                ADD COLUMN cnpj VARCHAR(20) NULL
                    COMMENT 'CNPJ da empresa (CNPJ.ws)' AFTER source,
                ADD INDEX idx_cnpj (cnpj)
        ");

        // Retrocompatibilidade: marca resultados existentes como google_maps
        $db->exec("UPDATE prospecting_results SET source = 'google_maps' WHERE source IS NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE prospecting_results DROP INDEX idx_cnpj, DROP COLUMN cnpj, DROP COLUMN source");
        $db->exec("ALTER TABLE prospecting_recipes DROP INDEX idx_source, DROP COLUMN cnae_description, DROP COLUMN cnae_code, DROP COLUMN source");
    }
}
