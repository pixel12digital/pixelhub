<?php

/**
 * Migration: Cria tabela de serviços (catálogo de serviços pontuais)
 * 
 * Esta tabela armazena o catálogo de serviços oferecidos pela agência
 * (ex: Criação de Site, Logo, Cartão de Visita, etc.)
 * 
 * DIFERENÇA de billing_service_types:
 * - billing_service_types: Para categorizar CONTRATOS RECORRENTES (hospedagem, SaaS)
 * - services: Para SERVIÇOS PONTUAIS (projetos específicos)
 */
class CreateServicesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS services (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                category VARCHAR(50) NULL,
                price DECIMAL(10,2) NULL,
                estimated_duration INT NULL COMMENT 'Duração estimada em dias',
                tasks_template JSON NULL COMMENT 'Template de tarefas pré-definidas',
                briefing_template JSON NULL COMMENT 'Template de briefing/ formulário guiado',
                default_timeline JSON NULL COMMENT 'Prazos padrão por etapa',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_name (name),
                INDEX idx_category (category),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS services");
    }
}

