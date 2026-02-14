<?php

/**
 * Migration: Cria tabela scheduled_messages para envio automático de mensagens agendadas
 * Permite agendar mensagens de follow-up que serão enviadas automaticamente
 */
class CreateScheduledMessagesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS scheduled_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agenda_item_id INT UNSIGNED NULL COMMENT 'FK para agenda_manual_items',
                opportunity_id INT UNSIGNED NULL COMMENT 'FK para opportunities',
                lead_id INT UNSIGNED NULL COMMENT 'FK para leads',
                tenant_id INT UNSIGNED NULL COMMENT 'FK para tenants',
                conversation_id INT UNSIGNED NULL COMMENT 'FK para conversations',
                
                message_text TEXT NOT NULL COMMENT 'Mensagem a ser enviada',
                scheduled_at DATETIME NOT NULL COMMENT 'Data/hora agendada para envio',
                
                status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
                sent_at DATETIME NULL COMMENT 'Data/hora real do envio',
                failed_reason TEXT NULL COMMENT 'Motivo da falha',
                
                response_detected TINYINT(1) DEFAULT 0 COMMENT 'Lead respondeu?',
                response_detected_at DATETIME NULL COMMENT 'Quando detectou resposta',
                reminder_sent TINYINT(1) DEFAULT 0 COMMENT 'Lembrete de sem resposta enviado?',
                reminder_sent_at DATETIME NULL COMMENT 'Quando enviou lembrete',
                
                created_by INT UNSIGNED NULL COMMENT 'Usuário que criou',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_scheduled_at (scheduled_at),
                INDEX idx_status (status),
                INDEX idx_agenda_item_id (agenda_item_id),
                INDEX idx_opportunity_id (opportunity_id),
                INDEX idx_conversation_id (conversation_id),
                INDEX idx_response_detected (response_detected),
                INDEX idx_reminder_sent (reminder_sent)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS scheduled_messages");
    }
}
