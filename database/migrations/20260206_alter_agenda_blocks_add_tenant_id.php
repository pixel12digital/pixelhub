<?php

/**
 * Migration: Adiciona tenant_id em agenda_blocks
 * Permite vincular cliente em atividades avulsas (ex.: reuniões comerciais)
 */
class AlterAgendaBlocksAddTenantId
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'tenant_id'");
        if ($stmt->rowCount() > 0) {
            return;
        }
        $db->exec("
            ALTER TABLE agenda_blocks
            ADD COLUMN tenant_id INT UNSIGNED NULL AFTER projeto_foco_id,
            ADD INDEX idx_tenant_id (tenant_id),
            ADD CONSTRAINT agenda_blocks_tenant_fk
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
        ");
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'tenant_id'");
        if ($stmt->rowCount() === 0) {
            return;
        }
        $db->exec("
            ALTER TABLE agenda_blocks
            DROP FOREIGN KEY agenda_blocks_tenant_fk,
            DROP INDEX idx_tenant_id,
            DROP COLUMN tenant_id
        ");
    }
}
