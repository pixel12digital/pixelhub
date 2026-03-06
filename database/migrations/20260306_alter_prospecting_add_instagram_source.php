<?php

/**
 * Migration: Adiciona suporte a Instagram (Apify) na Prospecção Ativa
 *
 * prospecting_recipes: adiciona 'instagram' ao ENUM source
 * prospecting_results: adiciona colunas Instagram (username, seguidores, bio, telefone, etc.)
 */
class AlterProspectingAddInstagramSource
{
    public function up(PDO $db): void
    {
        // 1. Adiciona 'instagram' ao ENUM source de prospecting_recipes
        $db->exec("
            ALTER TABLE prospecting_recipes
                MODIFY COLUMN source ENUM('google_maps', 'cnpjws', 'minhareceita', 'instagram') NOT NULL DEFAULT 'google_maps'
                    COMMENT 'Fonte da prospecção'
        ");

        // 2. Adiciona colunas Instagram em prospecting_results
        $db->exec("
            ALTER TABLE prospecting_results
                ADD COLUMN instagram_username VARCHAR(100) NULL
                    COMMENT 'Username do perfil Instagram (@handle)' AFTER source,
                ADD COLUMN instagram_followers INT UNSIGNED NULL
                    COMMENT 'Número de seguidores' AFTER instagram_username,
                ADD COLUMN instagram_is_business TINYINT(1) NULL
                    COMMENT '1 = conta business/criador' AFTER instagram_followers,
                ADD COLUMN instagram_category VARCHAR(150) NULL
                    COMMENT 'Categoria da conta business' AFTER instagram_is_business,
                ADD COLUMN instagram_bio TEXT NULL
                    COMMENT 'Biografia do perfil' AFTER instagram_category,
                ADD COLUMN instagram_profile_pic VARCHAR(500) NULL
                    COMMENT 'URL da foto de perfil' AFTER instagram_bio,
                ADD COLUMN phone_instagram VARCHAR(50) NULL
                    COMMENT 'Telefone business do Instagram (enriquecido via Apify)' AFTER instagram_profile_pic,
                ADD COLUMN email_instagram VARCHAR(255) NULL
                    COMMENT 'Email business do Instagram' AFTER phone_instagram,
                ADD COLUMN website_instagram VARCHAR(500) NULL
                    COMMENT 'Website do perfil Instagram' AFTER email_instagram,
                ADD COLUMN instagram_city VARCHAR(150) NULL
                    COMMENT 'Cidade do perfil Instagram' AFTER website_instagram,
                ADD COLUMN apify_phone_enriched_at DATETIME NULL
                    COMMENT 'Quando foi feita a busca de telefone via Apify' AFTER instagram_city,
                ADD INDEX idx_instagram_username (instagram_username)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE prospecting_results
                DROP INDEX idx_instagram_username,
                DROP COLUMN apify_phone_enriched_at,
                DROP COLUMN instagram_city,
                DROP COLUMN website_instagram,
                DROP COLUMN email_instagram,
                DROP COLUMN phone_instagram,
                DROP COLUMN instagram_profile_pic,
                DROP COLUMN instagram_bio,
                DROP COLUMN instagram_category,
                DROP COLUMN instagram_is_business,
                DROP COLUMN instagram_followers,
                DROP COLUMN instagram_username
        ");

        $db->exec("
            ALTER TABLE prospecting_recipes
                MODIFY COLUMN source ENUM('google_maps', 'cnpjws', 'minhareceita') NOT NULL DEFAULT 'google_maps'
                    COMMENT 'Fonte da prospecção'
        ");
    }
}
