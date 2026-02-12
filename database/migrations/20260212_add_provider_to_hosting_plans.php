<?php

/**
 * Migration: Adiciona coluna 'provider' na tabela hosting_plans
 * Provedor do plano de hospedagem (hostmedia, vercel)
 */
class AddProviderToHostingPlans
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe
        $stmt = $db->query("SHOW COLUMNS FROM hosting_plans LIKE 'provider'");
        if ($stmt->fetch()) {
            return; // Coluna já existe
        }

        $db->exec("
            ALTER TABLE hosting_plans
            ADD COLUMN provider VARCHAR(50) NULL DEFAULT NULL AFTER name
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("ALTER TABLE hosting_plans DROP COLUMN IF EXISTS provider");
    }
}
