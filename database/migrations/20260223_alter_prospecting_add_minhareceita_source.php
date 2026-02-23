<?php

/**
 * Migration: Adiciona 'minhareceita' ao ENUM source de prospecting_recipes
 */
class AlterProspectingAddMinhareceitaSource
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_recipes
                MODIFY COLUMN source ENUM('google_maps', 'cnpjws', 'minhareceita') NOT NULL DEFAULT 'google_maps'
                    COMMENT 'Fonte da prospecção'
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_recipes
                MODIFY COLUMN source ENUM('google_maps', 'cnpjws') NOT NULL DEFAULT 'google_maps'
                    COMMENT 'Fonte da prospecção'
        ");
    }
}
