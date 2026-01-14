<?php

/**
 * Migration: Adiciona campos de endereço em tenants
 */
class AlterTenantsAddAddressFields
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('address_cep', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_cep VARCHAR(10) NULL AFTER phone");
        }
        
        if (!in_array('address_street', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_street VARCHAR(255) NULL AFTER address_cep");
        }
        
        if (!in_array('address_number', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_number VARCHAR(20) NULL AFTER address_street");
        }
        
        if (!in_array('address_complement', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_complement VARCHAR(100) NULL AFTER address_number");
        }
        
        if (!in_array('address_neighborhood', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_neighborhood VARCHAR(100) NULL AFTER address_complement");
        }
        
        if (!in_array('address_city', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_city VARCHAR(100) NULL AFTER address_neighborhood");
        }
        
        if (!in_array('address_state', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN address_state VARCHAR(2) NULL AFTER address_city");
        }
        
        if (!in_array('phone_fixed', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN phone_fixed VARCHAR(20) NULL AFTER phone");
        }
    }

    public function down(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('address_cep', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_cep");
        }
        
        if (in_array('address_street', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_street");
        }
        
        if (in_array('address_number', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_number");
        }
        
        if (in_array('address_complement', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_complement");
        }
        
        if (in_array('address_neighborhood', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_neighborhood");
        }
        
        if (in_array('address_city', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_city");
        }
        
        if (in_array('address_state', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN address_state");
        }
        
        if (in_array('phone_fixed', $columns)) {
            $db->exec("ALTER TABLE tenants DROP COLUMN phone_fixed");
        }
    }
}





