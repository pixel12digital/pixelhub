<?php

/**
 * Migration: Adiciona campos de encerramento à tabela tickets
 * 
 * Campos adicionados:
 * - closed_at (DATETIME NULL): Data/hora de encerramento do ticket
 * - closed_by_user_id (INT UNSIGNED NULL): ID do usuário que encerrou o ticket
 * - closing_feedback (TEXT NULL): Feedback/observações de encerramento para o cliente
 */
class AlterTicketsAddClosingFields
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE tickets
            ADD COLUMN closed_at DATETIME NULL AFTER data_resolucao,
            ADD COLUMN closed_by_user_id INT UNSIGNED NULL AFTER closed_at,
            ADD COLUMN closing_feedback TEXT NULL AFTER closed_by_user_id,
            ADD INDEX idx_closed_at (closed_at),
            ADD INDEX idx_closed_by_user_id (closed_by_user_id),
            ADD CONSTRAINT fk_tickets_closed_by_user 
            FOREIGN KEY (closed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        ");
    }

    public function down(PDO $db): void
    {
        // Remove FK primeiro
        $db->exec("ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_closed_by_user");
        
        // Remove índices
        $db->exec("ALTER TABLE tickets DROP INDEX idx_closed_at");
        $db->exec("ALTER TABLE tickets DROP INDEX idx_closed_by_user_id");
        
        // Remove colunas
        $db->exec("
            ALTER TABLE tickets
            DROP COLUMN closing_feedback,
            DROP COLUMN closed_by_user_id,
            DROP COLUMN closed_at
        ");
    }
}



