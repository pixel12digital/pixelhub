<?php

/**
 * Migration: Cria tabela system_alerts para monitoramento de serviços
 */
class CreateSystemAlertsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS system_alerts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                alert_type VARCHAR(50) NOT NULL COMMENT 'gateway_offline, session_disconnected, webhook_failure, etc.',
                severity ENUM('critical', 'warning', 'info') NOT NULL DEFAULT 'critical',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                context_json TEXT NULL COMMENT 'JSON com detalhes técnicos (causa, logs, etc.)',
                session_id VARCHAR(100) NULL COMMENT 'ID da sessão WhatsApp afetada (se aplicável)',
                is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo (exibindo), 0=resolvido',
                acknowledged_at DATETIME NULL COMMENT 'Quando o usuário clicou OK/Ciente',
                acknowledged_by INT UNSIGNED NULL COMMENT 'ID do usuário que deu acknowledge',
                resolved_at DATETIME NULL COMMENT 'Quando o problema foi resolvido automaticamente',
                first_detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                check_count INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Quantas vezes o health check detectou este problema',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active (is_active),
                INDEX idx_type_active (alert_type, is_active),
                INDEX idx_session (session_id),
                INDEX idx_severity (severity, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS system_alert_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                alert_id INT UNSIGNED NULL,
                event_type VARCHAR(50) NOT NULL COMMENT 'detected, resolved, acknowledged, check_failed, check_ok',
                details TEXT NULL COMMENT 'Detalhes do evento (JSON)',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_alert_id (alert_id),
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (alert_id) REFERENCES system_alerts(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS system_alert_log");
        $db->exec("DROP TABLE IF EXISTS system_alerts");
    }
}
