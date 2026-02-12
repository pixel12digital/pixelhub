<?php

/**
 * Migration: Criar tabela plan_service_types
 * Tipos de serviço cadastráveis para planos recorrentes (Hospedagem, E-commerce, etc.)
 */
class CreatePlanServiceTypesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS plan_service_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(100) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY uk_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed: inserir Hospedagem como tipo inicial
        $stmt = $db->prepare("SELECT id FROM plan_service_types WHERE slug = ?");
        $stmt->execute(['hospedagem']);
        if (!$stmt->fetch()) {
            $db->exec("
                INSERT INTO plan_service_types (name, slug, is_active, sort_order, created_at, updated_at)
                VALUES ('Hospedagem', 'hospedagem', 1, 0, NOW(), NOW())
            ");
        }

        // Seed: E-commerce
        $stmt->execute(['ecommerce']);
        if (!$stmt->fetch()) {
            $db->exec("
                INSERT INTO plan_service_types (name, slug, is_active, sort_order, created_at, updated_at)
                VALUES ('E-commerce', 'ecommerce', 1, 1, NOW(), NOW())
            ");
        }
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS plan_service_types");
    }
}
