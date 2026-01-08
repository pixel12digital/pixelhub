<?php

/**
 * Migration: Cria tabela chat_threads
 * 
 * Threads de conversa vinculadas a pedidos de serviço.
 * O chat sempre nasce com order_id - nunca existe solto.
 */
class CreateChatThreadsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS chat_threads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                customer_id INT UNSIGNED NULL COMMENT 'ID do tenant/cliente',
                order_id INT UNSIGNED NOT NULL COMMENT 'FK para service_orders - OBRIGATÓRIO',
                status VARCHAR(50) NOT NULL DEFAULT 'open' COMMENT 'open | waiting_user | waiting_ai | escalated | closed',
                current_step VARCHAR(50) NULL COMMENT 'step_0_welcome | step_1_identity | step_2_contacts | etc.',
                metadata JSON NULL COMMENT 'Dados adicionais do estado do chat',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                
                INDEX idx_customer_id (customer_id),
                INDEX idx_order_id (order_id),
                INDEX idx_status (status),
                INDEX idx_current_step (current_step),
                FOREIGN KEY (order_id) REFERENCES service_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (customer_id) REFERENCES tenants(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS chat_threads");
    }
}

