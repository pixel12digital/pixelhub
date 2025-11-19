<?php

/**
 * Migration: Adiciona campo de observações internas em tenants
 */
class AlterTenantsAddInternalNotes
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $columns = $db->query("SHOW COLUMNS FROM tenants")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('internal_notes', $columns)) {
            $db->exec("ALTER TABLE tenants ADD COLUMN internal_notes TEXT NULL AFTER billing_last_check_at");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE tenants DROP COLUMN IF EXISTS internal_notes");
    }
}

