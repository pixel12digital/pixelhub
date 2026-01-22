<?php

/**
 * Migration: Cria tabela chat_messages
 * 
 * Mensagens das conversas do chat.
 */
class CreateChatMessagesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS chat_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                thread_id INT UNSIGNED NOT NULL COMMENT 'FK para chat_threads',
                role VARCHAR(20) NOT NULL COMMENT 'system | assistant | user | tool',
                content TEXT NOT NULL COMMENT 'Conteúdo da mensagem',
                metadata JSON NULL COMMENT 'extracted_fields, step_id, confidence, etc',
                created_at DATETIME NOT NULL,
                
                INDEX idx_thread_id (thread_id),
                INDEX idx_role (role),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (thread_id) REFERENCES chat_threads(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS chat_messages");
    }
}

