<?php

/**
 * Migration: Adiciona campos de credenciais de acesso em hosting_accounts
 */
class AlterHostingAccountsAddCredentials
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('hosting_panel_url', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN hosting_panel_url VARCHAR(255) NULL AFTER notes");
        }
        
        if (!in_array('hosting_panel_username', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN hosting_panel_username VARCHAR(255) NULL AFTER hosting_panel_url");
        }
        
        if (!in_array('hosting_panel_password', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN hosting_panel_password VARCHAR(255) NULL AFTER hosting_panel_username");
        }
        
        if (!in_array('site_admin_url', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN site_admin_url VARCHAR(255) NULL AFTER hosting_panel_password");
        }
        
        if (!in_array('site_admin_username', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN site_admin_username VARCHAR(255) NULL AFTER site_admin_url");
        }
        
        if (!in_array('site_admin_password', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts ADD COLUMN site_admin_password VARCHAR(255) NULL AFTER site_admin_username");
        }
    }

    public function down(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM hosting_accounts")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('site_admin_password', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN site_admin_password");
        }
        
        if (in_array('site_admin_username', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN site_admin_username");
        }
        
        if (in_array('site_admin_url', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN site_admin_url");
        }
        
        if (in_array('hosting_panel_password', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN hosting_panel_password");
        }
        
        if (in_array('hosting_panel_username', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN hosting_panel_username");
        }
        
        if (in_array('hosting_panel_url', $columns)) {
            $db->exec("ALTER TABLE hosting_accounts DROP COLUMN hosting_panel_url");
        }
    }
}

