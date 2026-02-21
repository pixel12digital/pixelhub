<?php

/**
 * Migration: Cria tabelas de Prospecção Ativa
 * 
 * prospecting_recipes: Receitas de busca (produto + cidade + palavras-chave)
 * prospecting_results: Empresas encontradas via Google Places API
 */
class CreateProspectingTables
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS prospecting_recipes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL COMMENT 'Nome da receita (ex: Imobiliárias em Curitiba)',
                product_id INT UNSIGNED NULL COMMENT 'FK para opportunity_products (produto/serviço a oferecer)',
                city VARCHAR(150) NOT NULL COMMENT 'Cidade alvo da busca',
                state VARCHAR(2) NULL COMMENT 'UF (ex: PR, SP)',
                keywords JSON NULL COMMENT 'Array de palavras-chave (ex: [\"imobiliária\", \"corretor\"])',
                google_place_type VARCHAR(100) NULL COMMENT 'Tipo de lugar do Google Places (ex: real_estate_agency)',
                radius_meters INT UNSIGNED NULL DEFAULT 5000 COMMENT 'Raio de busca em metros (para Nearby Search)',
                status ENUM(\"active\", \"paused\") NOT NULL DEFAULT \"active\",
                last_run_at DATETIME NULL COMMENT 'Última vez que a busca foi executada',
                total_found INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total de empresas encontradas (acumulado)',
                notes TEXT NULL COMMENT 'Observações sobre a receita',
                created_by INT UNSIGNED NULL COMMENT 'FK para users',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_status (status),
                INDEX idx_product (product_id),
                INDEX idx_city (city),
                INDEX idx_created_by (created_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS prospecting_results (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                recipe_id INT UNSIGNED NOT NULL COMMENT 'FK para prospecting_recipes',
                google_place_id VARCHAR(255) NOT NULL UNIQUE COMMENT 'ID único do Google Maps (deduplicação global)',
                name VARCHAR(255) NOT NULL COMMENT 'Nome da empresa',
                address VARCHAR(500) NULL COMMENT 'Endereço completo formatado',
                city VARCHAR(150) NULL,
                state VARCHAR(2) NULL,
                phone VARCHAR(50) NULL COMMENT 'Telefone retornado pelo Google',
                website VARCHAR(500) NULL,
                rating DECIMAL(2,1) NULL COMMENT 'Avaliação Google (0.0 a 5.0)',
                user_ratings_total INT UNSIGNED NULL COMMENT 'Número de avaliações',
                lat DECIMAL(10,7) NULL,
                lng DECIMAL(10,7) NULL,
                google_types JSON NULL COMMENT 'Tipos do Google Places (ex: [\"real_estate_agency\"])',
                status ENUM('new','contacted','qualified','discarded') NOT NULL DEFAULT 'new' COMMENT 'Status de prospecção',
                lead_id INT UNSIGNED NULL COMMENT 'FK para leads (quando convertido)',
                opportunity_id INT UNSIGNED NULL COMMENT 'FK para opportunities (quando virou oportunidade)',
                notes TEXT NULL COMMENT 'Observações do prospector',
                found_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Quando foi encontrado',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT UNSIGNED NULL COMMENT 'FK para users',
                
                INDEX idx_recipe (recipe_id),
                INDEX idx_status (status),
                INDEX idx_lead (lead_id),
                INDEX idx_opportunity (opportunity_id),
                INDEX idx_found_at (found_at),
                FOREIGN KEY (recipe_id) REFERENCES prospecting_recipes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS prospecting_results");
        $db->exec("DROP TABLE IF EXISTS prospecting_recipes");
    }
}
