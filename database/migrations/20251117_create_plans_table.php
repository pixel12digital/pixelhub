<?php

/**
 * Migration: Cria tabela plans
 */
class CreatePlansTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS plans (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                billing_cycle VARCHAR(50) NOT NULL DEFAULT 'mensal',
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_billing_cycle (billing_cycle)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS plans");
    }
}

