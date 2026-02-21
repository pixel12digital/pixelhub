<?php

/**
 * Migration: Cria tabela tenant_products
 * 
 * Catálogo de produtos/serviços por conta (tenant).
 * tenant_id NULL = produtos da própria agência (Pixel12 Digital).
 */
class CreateTenantProductsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenant_products (
                id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                tenant_id   INT UNSIGNED NULL
                    COMMENT 'FK para tenants. NULL = agência própria',
                name        VARCHAR(120) NOT NULL,
                description TEXT NULL,
                status      ENUM('active','archived') NOT NULL DEFAULT 'active',
                sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_tenant (tenant_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenant_products");
    }
}
