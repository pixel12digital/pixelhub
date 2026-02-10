<?php

/**
 * Migration: Cria tabela de configuração de cooldown
 * 
 * Configuração de cooldown por canal/template:
 * - channel: whatsapp|email
 * - template_key: overdue_7d|overdue_15d|etc
 * - cooldown_hours: horas para evitar duplicação
 * - enabled: ativa/desativa cooldown
 */
class CreateBillingCooldownConfigTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS billing_cooldown_config (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                channel VARCHAR(20) NOT NULL,
                template_key VARCHAR(50) NOT NULL,
                cooldown_hours INT UNSIGNED NOT NULL DEFAULT 24,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_bcc_channel_template (channel, template_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Configurações padrão
        $db->exec("
            INSERT INTO billing_cooldown_config (channel, template_key, cooldown_hours) VALUES
            ('whatsapp', 'overdue_3d', 12),
            ('whatsapp', 'overdue_7d', 24),
            ('whatsapp', 'overdue_15d', 48),
            ('whatsapp', 'overdue_30d', 72),
            ('email', 'overdue_7d', 24),
            ('email', 'overdue_15d', 48),
            ('email', 'overdue_30d', 72),
            ('whatsapp', 'due_soon_3d', 24),
            ('email', 'due_soon_3d', 24)
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS billing_cooldown_config");
    }
}
