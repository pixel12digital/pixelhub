<?php

/**
 * Migration: Adapta prospecting_results para suportar Minha Receita
 *
 * Problemas corrigidos:
 * 1. google_place_id era NOT NULL — impedia INSERT de resultados Minha Receita
 * 2. Colunas email, cnae_code, cnae_description não existiam em prospecting_results
 */
class AlterProspectingResultsMinhaReceita
{
    public function up(PDO $db): void
    {
        // 1. Torna google_place_id nullable (Minha Receita não tem place_id do Google)
        $db->exec("
            ALTER TABLE prospecting_results
                MODIFY COLUMN google_place_id VARCHAR(255) NULL
                    COMMENT 'ID único do Google Maps (null para fontes não-Google)'
        ");

        // 2. Adiciona colunas necessárias para Minha Receita
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN email VARCHAR(255) NULL
                    COMMENT 'E-mail da empresa (Minha Receita)' AFTER phone,
                ADD COLUMN cnae_code VARCHAR(10) NULL
                    COMMENT 'Código CNAE principal' AFTER cnpj,
                ADD COLUMN cnae_description VARCHAR(255) NULL
                    COMMENT 'Descrição do CNAE principal' AFTER cnae_code
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP COLUMN cnae_description,
                DROP COLUMN cnae_code,
                DROP COLUMN email
        ");

        $db->exec("
            ALTER TABLE prospecting_results
                MODIFY COLUMN google_place_id VARCHAR(255) NOT NULL
                    COMMENT 'ID único do Google Maps (deduplicação global)'
        ");
    }
}
