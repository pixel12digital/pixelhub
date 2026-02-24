<?php

/**
 * Migration: Cria tabela opportunity_lost_reasons
 * 
 * Motivos padronizados de perda de oportunidades para análise e relatórios.
 */
class CreateOpportunityLostReasonsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS opportunity_lost_reasons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(100) NOT NULL COMMENT 'Nome do motivo (ex: Sem retorno do lead)',
                slug VARCHAR(50) NOT NULL COMMENT 'Identificador único (ex: no_response)',
                category VARCHAR(50) NULL COMMENT 'Categoria do motivo (ex: contact, price, competition)',
                description TEXT NULL COMMENT 'Descrição detalhada do motivo',
                is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Se o motivo está ativo para seleção',
                display_order INT NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                UNIQUE KEY idx_slug (slug),
                INDEX idx_active (is_active),
                INDEX idx_category (category),
                INDEX idx_order (display_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS opportunity_lost_reasons");
    }
}
