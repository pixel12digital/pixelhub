<?php

/**
 * Migration: Adiciona campos para separar Pessoa Física e Jurídica
 */
class AlterTenantsAddPersonType
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('person_type', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN person_type VARCHAR(2) NOT NULL DEFAULT 'pf' AFTER id");
        }
        
        if (!in_array('cpf_cnpj', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN cpf_cnpj VARCHAR(20) NULL AFTER person_type");
        }
        
        if (!in_array('razao_social', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN razao_social VARCHAR(255) NULL AFTER cpf_cnpj");
        }
        
        if (!in_array('nome_fantasia', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN nome_fantasia VARCHAR(255) NULL AFTER razao_social");
        }
        
        if (!in_array('responsavel_nome', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN responsavel_nome VARCHAR(255) NULL AFTER nome_fantasia");
        }
        
        if (!in_array('responsavel_cpf', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN responsavel_cpf VARCHAR(20) NULL AFTER responsavel_nome");
        }
        
        // Renomeia document para manter compatibilidade (se existir e não for usado)
        // Ou podemos manter document como está e usar cpf_cnpj para Asaas
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS person_type");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS cpf_cnpj");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS razao_social");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS nome_fantasia");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS responsavel_nome");
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS responsavel_cpf");
    }
}

