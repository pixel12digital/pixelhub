<?php

/**
 * Migration: Adiciona campo is_deleted na tabela billing_invoices
 * 
 * Este campo marca cobranças que foram excluídas/canceladas no Asaas
 * e não devem aparecer como em aberto no painel.
 */
class AlterBillingInvoicesAddIsDeleted
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_invoices
            ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
            ADD INDEX idx_is_deleted (is_deleted)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_invoices
            DROP INDEX idx_is_deleted,
            DROP COLUMN is_deleted
        ");
    }
}

