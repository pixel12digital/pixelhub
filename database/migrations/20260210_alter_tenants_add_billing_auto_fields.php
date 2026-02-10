<?php

/**
 * Migration: Adiciona campos de automação de cobrança em tenants
 * 
 * - billing_auto_send: flag para ativar envio automático
 * - billing_auto_channel: canal preferido (whatsapp, email, both)
 * - is_billing_test: flag para marcar tenant como teste (segurança)
 */
class AlterTenantsAddBillingAutoFields
{
    public function up(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('billing_auto_send', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN billing_auto_send TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_last_check_at");
        }
        
        if (!in_array('billing_auto_channel', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN billing_auto_channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp' AFTER billing_auto_send");
        }
        
        if (!in_array('is_billing_test', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN is_billing_test TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_auto_channel");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS billing_auto_send");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS billing_auto_channel");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS is_billing_test");
    }
}
