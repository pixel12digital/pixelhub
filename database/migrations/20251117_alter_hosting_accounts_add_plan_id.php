<?php

/**
 * Migration: Adiciona hosting_plan_id em hosting_accounts
 */
class AlterHostingAccountsAddPlanId
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('hosting_plan_id', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN hosting_plan_id INT UNSIGNED NULL AFTER plan_name");
            $db->exec("ALTER TABLE hosting_accounts ADD INDEX idx_hosting_plan_id (hosting_plan_id)");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE hosting_accounts DROP COLUMN IF EXISTS hosting_plan_id");
    }
}

