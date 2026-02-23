<?php

/**
 * Migration: Adiciona campo cnaes (JSON array) para suportar múltiplos CNAEs em prospecting_recipes
 */
class AlterProspectingRecipesAddCnaesArray
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_recipes
                ADD COLUMN cnaes JSON NULL
                    COMMENT 'Array de CNAEs [{code, desc}] para busca com múltiplos CNAEs' AFTER cnae_description
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_recipes
                DROP COLUMN cnaes
        ");
    }
}
