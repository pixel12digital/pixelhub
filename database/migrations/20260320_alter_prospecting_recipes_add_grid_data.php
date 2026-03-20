<?php

/**
 * Migration: Adiciona coluna search_grid_data em prospecting_recipes
 * 
 * Armazena estado da grade geográfica para busca paginada no Google Maps.
 * Permite que "Buscar Novamente" continue de onde parou, explorando novas
 * sub-regiões da cidade em vez de repetir a mesma consulta.
 */
class AlterProspectingRecipesAddGridData
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_recipes
            ADD COLUMN search_grid_data MEDIUMTEXT NULL
                COMMENT 'JSON com grade geográfica e estado de exploração para Google Maps'
            AFTER notes
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_recipes
            DROP COLUMN IF EXISTS search_grid_data
        ");
    }
}
