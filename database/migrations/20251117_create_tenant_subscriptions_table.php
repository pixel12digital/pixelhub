<?php

/**
 * Migration: Cria tabela tenant_subscriptions
 */
class CreateTenantSubscriptionsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS tenant_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id INT UNSIGNED NOT NULL,
                plan_id INT UNSIGNED NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                started_at DATETIME NULL,
                ends_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_tenant_id (tenant_id),
                INDEX idx_plan_id (plan_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS tenant_subscriptions");
    }
}

