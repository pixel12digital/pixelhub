<?php

/**
 * Migration: Adiciona flags de arquivamento em tenants
 * 
 * Permite arquivar clientes para que não apareçam na lista de CRM,
 * mas continuem acessíveis para fins financeiros (Central de Cobrança).
 */
class AlterTenantsAddArchiveFlags
{
    public function up(PDO $db): void
    {
        // Verifica colunas existentes
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        // Adiciona is_archived se não existir
        if (!in_array('is_archived', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
        }
        
        // Adiciona is_financial_only se não existir
        if (!in_array('is_financial_only', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN is_financial_only TINYINT(1) NOT NULL DEFAULT 0 AFTER is_archived");
        }
        
        // Verifica índices existentes
        $indexes = $db->query("SHOW INDEXES FROM tenants")->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');
        
        // Adiciona índice para is_archived se não existir
        if (!in_array('idx_is_archived', $indexNames)) {
            try {
                $db->exec("ALTER TABLE tenants ADD INDEX idx_is_archived (is_archived)");
            } catch (\Exception $e) {
                error_log("Não foi possível criar índice idx_is_archived: " . $e->getMessage());
            }
        }
        
        // Adiciona índice para is_financial_only se não existir
        if (!in_array('idx_is_financial_only', $indexNames)) {
            try {
                $db->exec("ALTER TABLE tenants ADD INDEX idx_is_financial_only (is_financial_only)");
            } catch (\Exception $e) {
                error_log("Não foi possível criar índice idx_is_financial_only: " . $e->getMessage());
            }
        }
    }

    public function down(PDO $db): void
    {
        // Remove índices
        try {
            $db->exec("ALTER TABLE tenants DROP INDEX IF EXISTS idx_is_financial_only");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        try {
            $db->exec("ALTER TABLE tenants DROP INDEX IF EXISTS idx_is_archived");
        } catch (\Exception $e) {
            // Ignora se não existir
        }
        
        // Remove colunas
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('is_financial_only', $columns)) {
            try {
                $db->exec("ALTER TABLE tenants DROP COLUMN is_financial_only");
            } catch (\Exception $e) {
                error_log("Erro ao remover coluna is_financial_only: " . $e->getMessage());
            }
        }
        
        if (in_array('is_archived', $columns)) {
            try {
                $db->exec("ALTER TABLE tenants DROP COLUMN is_archived");
            } catch (\Exception $e) {
                error_log("Erro ao remover coluna is_archived: " . $e->getMessage());
            }
        }
    }
}

