<?php

/**
 * Migration: Adiciona coluna company na tabela tenants
 * 
 * Necessário para ContactService::create() funcionar corretamente ao criar leads
 * via modal "Nova Oportunidade" (storeLeadAjax).
 */
class AlterTenantsAddCompany
{
    public function up(PDO $db): void
    {
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('company', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN company VARCHAR(255) NULL DEFAULT NULL COMMENT 'Nome da empresa do lead/contato' AFTER name");
        }
    }

    public function down(PDO $db): void
    {
        try {
            $db->exec("ALTER TABLE tenants DROP COLUMN company");
        } catch (Exception $e) {
            // Ignora se coluna não existe
        }
    }
}
