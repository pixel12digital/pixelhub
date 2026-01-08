<?php

/**
 * Migration: Adiciona campo project_id na tabela billing_invoices
 * 
 * Este campo permite vincular faturas a projetos específicos
 */
class AlterBillingInvoicesAddProjectId
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM billing_invoices LIKE 'project_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE billing_invoices
                ADD COLUMN project_id INT UNSIGNED NULL AFTER tenant_id,
                ADD INDEX idx_project_id (project_id),
                ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_invoices
            DROP FOREIGN KEY billing_invoices_ibfk_1,
            DROP INDEX idx_project_id,
            DROP COLUMN project_id
        ");
    }
}

