<?php

/**
 * Migration: Adiciona campo service_type em billing_contracts
 * 
 * Este campo permite categorizar contratos por tipo de serviço
 * (hospedagem, SaaS ImobSites, SaaS CFC, etc.)
 */
class AlterBillingContractsAddServiceType
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM billing_contracts LIKE 'service_type'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE billing_contracts
                ADD COLUMN service_type VARCHAR(100) NULL AFTER status,
                ADD INDEX idx_service_type (service_type)
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE billing_contracts
            DROP INDEX idx_service_type,
            DROP COLUMN service_type
        ");
    }
}

