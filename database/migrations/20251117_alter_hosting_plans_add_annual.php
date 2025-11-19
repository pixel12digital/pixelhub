<?php

/**
 * Migration: Adiciona campos para planos anuais em hosting_plans
 */
class AlterHostingPlansAddAnnual
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem
        $columns = $db->query("SHOW COLUMNS FROM hosting_plans")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('annual_enabled', $columns)) {
            $db->exec("ALTER TABLE hosting_plans ADD COLUMN annual_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER billing_cycle");
        }
        
        if (!in_array('annual_monthly_amount', $columns)) {
            $db->exec("ALTER TABLE hosting_plans ADD COLUMN annual_monthly_amount DECIMAL(10,2) NULL AFTER annual_enabled");
        }
        
        if (!in_array('annual_total_amount', $columns)) {
            $db->exec("ALTER TABLE hosting_plans ADD COLUMN annual_total_amount DECIMAL(10,2) NULL AFTER annual_monthly_amount");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE hosting_plans DROP COLUMN IF EXISTS annual_enabled");
        $db->exec("ALTER TABLE hosting_plans DROP COLUMN IF EXISTS annual_monthly_amount");
        $db->exec("ALTER TABLE hosting_plans DROP COLUMN IF EXISTS annual_total_amount");
    }
}

