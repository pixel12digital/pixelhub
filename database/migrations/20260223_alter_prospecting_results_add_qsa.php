<?php

/**
 * Migration: Adiciona campo QSA (Quadro de Sócios e Administradores) em prospecting_results
 */
class AlterProspectingResultsAddQsa
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN qsa JSON NULL
                    COMMENT 'Quadro de Sócios e Administradores (JSON)' AFTER cnaes_secundarios
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP COLUMN qsa
        ");
    }
}
