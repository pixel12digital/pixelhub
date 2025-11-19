<?php

/**
 * Migration: Adiciona campos de cobrança em tenants
 */
class AlterTenantsAddBillingFields
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('asaas_customer_id', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN asaas_customer_id VARCHAR(100) NULL AFTER phone");
        }
        
        if (!in_array('billing_status', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN billing_status VARCHAR(30) NOT NULL DEFAULT 'sem_cobranca' AFTER asaas_customer_id");
        }
        
        if (!in_array('billing_last_check_at', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN billing_last_check_at DATETIME NULL AFTER billing_status");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS asaas_customer_id");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS billing_status");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS billing_last_check_at");
    }
}

