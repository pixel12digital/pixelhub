<?php

/**
 * Migration: Adiciona campo billing_type na tabela services
 * 
 * Este campo indica se o serviço é uma cobrança única ou recorrente
 * Valores: 'one_time' (única) ou 'recurring' (recorrente)
 */
class AlterServicesAddBillingType
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM services LIKE 'billing_type'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE services
                ADD COLUMN billing_type VARCHAR(20) NULL DEFAULT 'one_time' AFTER price,
                ADD INDEX idx_billing_type (billing_type)
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE services
            DROP INDEX idx_billing_type,
            DROP COLUMN billing_type
        ");
    }
}

