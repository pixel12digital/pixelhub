<?php

/**
 * Migration: Cria tabela de templates genéricos de WhatsApp
 * 
 * Templates para uso em campanhas, avisos, relacionamento comercial,
 * separados do sistema de cobrança.
 */
class CreateWhatsappTemplatesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS whatsapp_templates (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(50) NULL,
                category VARCHAR(50) NOT NULL DEFAULT 'geral',
                description TEXT NULL,
                content TEXT NOT NULL,
                variables JSON NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_code (code),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS whatsapp_templates");
    }
}

