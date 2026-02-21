<?php

/**
 * Migration: Adiciona tenant_id em prospecting_recipes
 * 
 * Permite vincular cada receita de busca a um tenant (cliente da agência),
 * isolando os leads e resultados por conta.
 * tenant_id NULL = campanha da própria agência (Pixel12 Digital).
 */
class AlterProspectingAddTenant
{
    public function up(PDO $db): void
    {
        // Adiciona tenant_id em prospecting_recipes
        $db->exec("
            ALTER TABLE prospecting_recipes
            ADD COLUMN tenant_id INT UNSIGNED NULL
                COMMENT 'FK para tenants — cliente dono da campanha. NULL = agência própria'
                AFTER id,
            ADD INDEX idx_tenant (tenant_id)
        ");

        // Adiciona tenant_id em prospecting_results para facilitar queries diretas
        $db->exec("
            ALTER TABLE prospecting_results
            ADD COLUMN tenant_id INT UNSIGNED NULL
                COMMENT 'Denormalizado de prospecting_recipes.tenant_id para queries rápidas'
                AFTER recipe_id,
            ADD INDEX idx_tenant (tenant_id)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE prospecting_recipes DROP INDEX idx_tenant, DROP COLUMN tenant_id");
        $db->exec("ALTER TABLE prospecting_results DROP INDEX idx_tenant, DROP COLUMN tenant_id");
    }
}
