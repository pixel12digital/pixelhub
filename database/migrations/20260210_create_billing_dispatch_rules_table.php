<?php

/**
 * Migration: Cria tabela de regras de disparo de cobrança automática
 */
class CreateBillingDispatchRulesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_dispatch_rules (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                stage VARCHAR(50) NOT NULL,
                days_offset INT NOT NULL COMMENT 'Negativo=antes do vencimento, 0=dia, Positivo=após',
                channels JSON NOT NULL DEFAULT '[\"whatsapp\"]',
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                repeat_if_open TINYINT(1) NOT NULL DEFAULT 0,
                repeat_interval_days INT UNSIGNED NULL,
                max_repeats INT UNSIGNED NOT NULL DEFAULT 3,
                template_key VARCHAR(50) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_dispatch_rules_enabled (is_enabled),
                INDEX idx_dispatch_rules_stage (stage)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Insere regras padrão
        $db->exec("
            INSERT INTO billing_dispatch_rules (name, stage, days_offset, channels, is_enabled, repeat_if_open, repeat_interval_days, max_repeats, template_key) VALUES
            ('Lembrete pré-vencimento (3 dias)', 'pre_due', -3, '[\"whatsapp\"]', 1, 0, NULL, 1, 'pre_due'),
            ('Dia do vencimento', 'due_day', 0, '[\"whatsapp\"]', 1, 0, NULL, 1, 'due_day'),
            ('Cobrança +3 dias', 'overdue_3d', 3, '[\"whatsapp\"]', 1, 0, NULL, 1, 'overdue_3d'),
            ('Cobrança +7 dias', 'overdue_7d', 7, '[\"whatsapp\"]', 1, 0, NULL, 1, 'overdue_7d'),
            ('Cobrança +15 dias (recorrente)', 'overdue_15d', 15, '[\"whatsapp\"]', 1, 1, 7, 3, 'overdue_15d')
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_dispatch_rules");
    }
}
