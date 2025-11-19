<?php

/**
 * Migration: Adiciona campos faltantes em hosting_accounts
 */
class AlterHostingAccountsAddFields
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('plan_name', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN plan_name VARCHAR(100) NULL AFTER domain");
        }
        
        if (!in_array('amount', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN amount DECIMAL(10, 2) NULL DEFAULT 0.00 AFTER current_provider");
        }
        
        if (!in_array('billing_cycle', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN billing_cycle VARCHAR(50) NULL DEFAULT 'mensal' AFTER amount");
        }
        
        if (!in_array('last_backup_at', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN last_backup_at DATETIME NULL AFTER backup_status");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE hosting_accounts DROP COLUMN IF EXISTS plan_name");
        $db->exec("ALTER TABLE hosting_accounts DROP COLUMN IF EXISTS amount");
        $db->exec("ALTER TABLE hosting_accounts DROP COLUMN IF EXISTS billing_cycle");
        $db->exec("ALTER TABLE hosting_accounts DROP COLUMN IF EXISTS last_backup_at");
    }
}

