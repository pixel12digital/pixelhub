<?php

/**
 * Migration: Adiciona campos de vencimento de domínio em hosting_accounts
 */
class AlterHostingAccountsAddDomainExpiration
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('domain_expiration_date', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN domain_expiration_date DATE NULL AFTER hostinger_expiration_date");
        }
        
        if (!in_array('domain_notified_30', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN domain_notified_30 TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_expiration_date");
        }
        
        if (!in_array('domain_notified_15', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN domain_notified_15 TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_notified_30");
        }
        
        if (!in_array('domain_notified_7', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN domain_notified_7 TINYINT(1) NOT NULL DEFAULT 0 AFTER domain_notified_15");
        }
    }

    public function down(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('domain_notified_7', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN domain_notified_7");
        }
        
        if (in_array('domain_notified_15', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN domain_notified_15");
        }
        
        if (in_array('domain_notified_30', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN domain_notified_30");
        }
        
        if (in_array('domain_expiration_date', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN domain_expiration_date");
        }
    }
}

