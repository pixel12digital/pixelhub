<?php

use PixelHub\Core\DB;

/**
 * Migration: Criar tabela scheduled_messages para follow-ups automáticos
 * 
 * Permite agendar mensagens para serem enviadas em horário específico
 * Útil para follow-ups dentro da janela de 24h do Meta (sem cobrança extra)
 * 
 * Data: 2026-03-04
 */

try {
    $db = DB::getConnection();
    
    echo "Iniciando migration: Criar tabela scheduled_messages...\n";
    
    // Verifica se a tabela já existe
    $stmt = $db->query("SHOW TABLES LIKE 'scheduled_messages'");
    if ($stmt->rowCount() > 0) {
        echo "⚠ Tabela 'scheduled_messages' já existe, pulando...\n";
    } else {
        $db->exec("
            CREATE TABLE scheduled_messages (
                id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id INT(10) UNSIGNED NOT NULL,
                lead_id INT(10) UNSIGNED NULL,
                phone VARCHAR(30) NOT NULL,
                message_type ENUM('text', 'template', 'media') NOT NULL DEFAULT 'text',
                message_content TEXT NOT NULL,
                template_name VARCHAR(100) NULL,
                template_params JSON NULL,
                scheduled_at DATETIME NOT NULL COMMENT 'Quando a mensagem deve ser enviada',
                status ENUM('pending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
                sent_at DATETIME NULL,
                error_message TEXT NULL,
                trigger_event VARCHAR(50) NULL COMMENT 'Evento que gerou o agendamento (ex: no_response_22h)',
                metadata JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_scheduled_at (scheduled_at, status),
                INDEX idx_conversation_id (conversation_id),
                INDEX idx_lead_id (lead_id),
                INDEX idx_status (status),
                INDEX idx_trigger_event (trigger_event),
                
                FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
                FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='Mensagens agendadas para envio automático (follow-ups, lembretes, etc.)'
        ");
        
        echo "✓ Tabela 'scheduled_messages' criada\n";
    }
    
    echo "\n✅ Migration concluída com sucesso!\n";
    
} catch (Exception $e) {
    echo "\n❌ Erro na migration: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
