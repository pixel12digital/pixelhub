<?php

/**
 * Migration: Adiciona campo has_no_hosting_expiration em hosting_accounts
 */
class AlterHostingAccountsAddHasNoExpiration
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('has_no_hosting_expiration', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN has_no_hosting_expiration TINYINT(1) NOT NULL DEFAULT 0 AFTER hostinger_expiration_date");
        }
    }

    public function down(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('has_no_hosting_expiration', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN has_no_hosting_expiration");
        }
    }
}
















