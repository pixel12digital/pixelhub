<?php

/**
 * Migration: Adiciona campo contract_content em project_contracts
 * 
 * Armazena o conteúdo completo do contrato com cláusulas montadas automaticamente.
 */
class AddContractContentToProjectContracts
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $stmt = $db->query("SHOW COLUMNS FROM project_contracts LIKE 'contract_content'");
        if ($stmt->fetch()) {
            return; // Coluna já existe
        }
        
        $db->exec("
            ALTER TABLE project_contracts
            ADD COLUMN contract_content TEXT NULL COMMENT 'Conteúdo completo do contrato com cláusulas montadas automaticamente' AFTER notes
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE project_contracts DROP COLUMN contract_content");
    }
}

