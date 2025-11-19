<?php

/**
 * Migration: Cria tabela de tipos de serviço para contratos recorrentes
 * 
 * Permite criar e gerenciar categorias de contratos (ex: Hospedagem, SaaS ImobSites, etc.)
 * sem precisar editar código.
 */
class CreateBillingServiceTypesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_service_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_slug (slug),
                INDEX idx_is_active (is_active),
                INDEX idx_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_service_types");
    }
}

