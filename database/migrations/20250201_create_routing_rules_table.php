<?php

/**
 * Migration: Cria tabela routing_rules
 * 
 * Regras de roteamento para eventos de comunicação.
 */
class CreateRoutingRulesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS routing_rules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(100) NOT NULL COMMENT 'Tipo de evento (pode usar wildcard: billing.invoice.*)',
                source_system VARCHAR(50) NULL COMMENT 'Sistema de origem (NULL = qualquer)',
                channel VARCHAR(30) NOT NULL COMMENT 'Canal de destino: whatsapp|chat|email|none',
                template VARCHAR(100) NULL COMMENT 'Template a usar (opcional)',
                priority INT NOT NULL DEFAULT 100 COMMENT 'Prioridade (menor = maior prioridade)',
                is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
                metadata JSON NULL COMMENT 'Configurações adicionais da regra',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_event_type (event_type),
                INDEX idx_source_system (source_system),
                INDEX idx_channel (channel),
                INDEX idx_is_enabled (is_enabled),
                INDEX idx_priority (priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS routing_rules");
    }
}

