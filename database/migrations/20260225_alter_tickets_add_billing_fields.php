<?php

/**
 * Migration: Adiciona campos de faturamento na tabela tickets
 * 
 * Permite que tickets sejam marcados como faturáveis e vinculados a cobranças no Asaas.
 * 
 * Campos adicionados:
 * - is_billable: Marca se o ticket é faturável
 * - service_id: Vincula a um serviço do catálogo (opcional)
 * - billed_value: Valor a ser cobrado
 * - billing_status: Status da cobrança (pending, billed, paid, canceled)
 * - billing_invoice_id: FK para billing_invoices (quando cobrança é gerada)
 * - billing_due_date: Data de vencimento da cobrança
 * - billed_at: Data em que a cobrança foi gerada no Asaas
 * - billing_notes: Observações sobre o faturamento
 */
class AlterTicketsAddBillingFields
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('is_billable', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN is_billable TINYINT(1) NOT NULL DEFAULT 0 AFTER data_resolucao");
        }
        
        if (!in_array('service_id', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN service_id INT UNSIGNED NULL AFTER is_billable");
        }
        
        if (!in_array('billed_value', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN billed_value DECIMAL(10,2) NULL AFTER service_id");
        }
        
        if (!in_array('billing_status', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN billing_status ENUM('pending','billed','paid','canceled') NULL DEFAULT NULL AFTER billed_value");
        }
        
        if (!in_array('billing_invoice_id', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN billing_invoice_id INT UNSIGNED NULL AFTER billing_status");
        }
        
        if (!in_array('billing_due_date', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN billing_due_date DATE NULL AFTER billing_invoice_id");
        }
        
        if (!in_array('billed_at', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN billed_at DATETIME NULL AFTER billing_due_date");
        }
        
        if (!in_array('billing_notes', $columns)) {
            $db->exec("ALTER TABLE tickets ADD COLUMN billing_notes TEXT NULL AFTER billed_at");
        }
        
        // Adiciona índices
        $indexes = $db->query("SHOW INDEXES FROM tickets")->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');
        
        if (!in_array('idx_is_billable', $indexNames)) {
            $db->exec("ALTER TABLE tickets ADD INDEX idx_is_billable (is_billable)");
        }
        
        if (!in_array('idx_billing_status', $indexNames)) {
            $db->exec("ALTER TABLE tickets ADD INDEX idx_billing_status (billing_status)");
        }
        
        if (!in_array('idx_service_id', $indexNames)) {
            $db->exec("ALTER TABLE tickets ADD INDEX idx_service_id (service_id)");
        }
        
        if (!in_array('idx_billing_invoice_id', $indexNames)) {
            $db->exec("ALTER TABLE tickets ADD INDEX idx_billing_invoice_id (billing_invoice_id)");
        }
        
        // Adiciona foreign keys se não existirem
        $foreignKeys = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'tickets' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('fk_tickets_service', $foreignKeys)) {
            $db->exec("ALTER TABLE tickets ADD CONSTRAINT fk_tickets_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL");
        }
        
        if (!in_array('fk_tickets_billing_invoice', $foreignKeys)) {
            $db->exec("ALTER TABLE tickets ADD CONSTRAINT fk_tickets_billing_invoice FOREIGN KEY (billing_invoice_id) REFERENCES billing_invoices(id) ON DELETE SET NULL");
        }
    }

    public function down(PDO $db): void
    {
        // Remove foreign keys
        $foreignKeys = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'tickets' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('fk_tickets_service', $foreignKeys)) {
            $db->exec("ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_service");
        }
        
        if (in_array('fk_tickets_billing_invoice', $foreignKeys)) {
            $db->exec("ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_billing_invoice");
        }
        
        // Remove índices
        $indexes = $db->query("SHOW INDEXES FROM tickets")->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');
        
        if (in_array('idx_is_billable', $indexNames)) {
            $db->exec("ALTER TABLE tickets DROP INDEX idx_is_billable");
        }
        
        if (in_array('idx_billing_status', $indexNames)) {
            $db->exec("ALTER TABLE tickets DROP INDEX idx_billing_status");
        }
        
        if (in_array('idx_service_id', $indexNames)) {
            $db->exec("ALTER TABLE tickets DROP INDEX idx_service_id");
        }
        
        if (in_array('idx_billing_invoice_id', $indexNames)) {
            $db->exec("ALTER TABLE tickets DROP INDEX idx_billing_invoice_id");
        }
        
        // Remove colunas
        $columns = $db->query("SHOW COLUMNS FROM tickets")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('is_billable', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN is_billable");
        }
        
        if (in_array('service_id', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN service_id");
        }
        
        if (in_array('billed_value', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN billed_value");
        }
        
        if (in_array('billing_status', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN billing_status");
        }
        
        if (in_array('billing_invoice_id', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN billing_invoice_id");
        }
        
        if (in_array('billing_due_date', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN billing_due_date");
        }
        
        if (in_array('billed_at', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN billed_at");
        }
        
        if (in_array('billing_notes', $columns)) {
            $db->exec("ALTER TABLE tickets DROP COLUMN billing_notes");
        }
    }
}

