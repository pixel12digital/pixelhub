<?php

/**
 * Migration: Adiciona tenant_id em tracking_codes
 * Permite vincular um código de rastreamento a uma conta específica (opcional)
 */
class AlterTrackingCodesAddTenant
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE tracking_codes
            ADD COLUMN tenant_id INT UNSIGNED NULL DEFAULT NULL
                COMMENT 'FK para tenants (opcional — null = código global)'
                AFTER created_by,
            ADD INDEX idx_tenant (tenant_id)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tracking_codes DROP INDEX idx_tenant, DROP COLUMN tenant_id");
    }
}
