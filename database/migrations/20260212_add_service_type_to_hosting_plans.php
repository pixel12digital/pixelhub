<?php

/**
 * Migration: Adicionar coluna service_type na tabela hosting_plans
 * Para categorizar planos por tipo de serviço (hospedagem, ecommerce, etc.)
 */
class AddServiceTypeToHostingPlans
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM hosting_plans LIKE 'service_type'");
        if ($stmt->fetch()) {
            return;
        }

        $db->exec("
            ALTER TABLE hosting_plans
            ADD COLUMN service_type VARCHAR(50) NULL DEFAULT NULL AFTER provider
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE hosting_plans DROP COLUMN IF EXISTS service_type");
    }
}
