<?php

/**
 * Migration: Adiciona campo billing_period_type em hosting_accounts
 * 
 * Permite diferenciar se a conta está em plano mensal ou anual.
 * - mensal: usa hosting_plans.amount (valor mensal)
 * - anual: usa hosting_plans.annual_total_amount (valor total anual)
 */
class AlterHostingAccountsAddBillingPeriodType
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('billing_period_type', $columns)) {
            $db->exec("
                ALTER TABLE hosting_accounts 
                ADD COLUMN billing_period_type ENUM('mensal', 'anual') NOT NULL DEFAULT 'mensal' 
                COMMENT 'Tipo de período de cobrança: mensal ou anual'
                AFTER billing_cycle
            ");
            
            $db->exec("ALTER TABLE hosting_accounts ADD INDEX idx_billing_period_type (billing_period_type)");
            
            error_log("[Migration] Campo billing_period_type adicionado à tabela hosting_accounts");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE hosting_accounts DROP COLUMN IF EXISTS billing_period_type");
        error_log("[Migration] Campo billing_period_type removido da tabela hosting_accounts (rollback)");
    }
}
