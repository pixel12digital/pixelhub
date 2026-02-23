<?php

/**
 * Migration: Separa campos por fonte de dados (Minha Receita vs Google Maps)
 * 
 * Objetivo: Preservar informações de ambas as fontes em vez de sobrescrever
 * 
 * Campos duplicados:
 * - phone_minhareceita / phone_google
 * - website_minhareceita / website_google
 * - address_minhareceita / address_google
 * 
 * Campos exclusivos Minha Receita: email, cnae, qsa, etc (já existem)
 * Campos exclusivos Google Maps: rating, user_ratings_total, google_place_id (já existem)
 */
class AlterProspectingResultsSeparateSources
{
    public function up(PDO $db): void
    {
        // 1. Renomeia campos existentes para indicar fonte Minha Receita
        $db->exec("
            ALTER TABLE prospecting_results
                CHANGE COLUMN phone phone_minhareceita VARCHAR(50) NULL
                    COMMENT 'Telefone da Minha Receita',
                CHANGE COLUMN website website_minhareceita VARCHAR(500) NULL
                    COMMENT 'Website da Minha Receita',
                CHANGE COLUMN address address_minhareceita VARCHAR(500) NULL
                    COMMENT 'Endereço da Minha Receita'
        ");

        // 2. Adiciona campos para dados do Google Maps
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN phone_google VARCHAR(50) NULL
                    COMMENT 'Telefone do Google Maps' AFTER phone_minhareceita,
                ADD COLUMN website_google VARCHAR(500) NULL
                    COMMENT 'Website do Google Maps' AFTER website_minhareceita,
                ADD COLUMN address_google VARCHAR(500) NULL
                    COMMENT 'Endereço do Google Maps' AFTER address_minhareceita
        ");

        // 3. Adiciona índices para busca
        $db->exec("
            ALTER TABLE prospecting_results
                ADD INDEX idx_phone_minhareceita (phone_minhareceita),
                ADD INDEX idx_phone_google (phone_google)
        ");
    }

    public function down(PDO $db): void
    {
        // Remove índices
        $db->exec("
            ALTER TABLE prospecting_results
                DROP INDEX idx_phone_minhareceita,
                DROP INDEX idx_phone_google
        ");

        // Remove campos do Google
        $db->exec("
            ALTER TABLE prospecting_results
                DROP COLUMN phone_google,
                DROP COLUMN website_google,
                DROP COLUMN address_google
        ");

        // Reverte nomes originais
        $db->exec("
            ALTER TABLE prospecting_results
                CHANGE COLUMN phone_minhareceita phone VARCHAR(50) NULL
                    COMMENT 'Telefone retornado pelo Google',
                CHANGE COLUMN website_minhareceita website VARCHAR(500) NULL,
                CHANGE COLUMN address_minhareceita address VARCHAR(500) NULL
                    COMMENT 'Endereço completo formatado'
        ");
    }
}
