<?php

/**
 * Migration: Cria tabela ticket_notes (Notas/Ocorrências dos Tickets)
 * 
 * Permite registrar pequenas notas/ocorrências nos tickets com data e hora automática.
 * Exemplo: "Entrei em contato com suporte da hospedagem" registrado em 15/01/2025 14:30
 */
class CreateTicketNotesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS ticket_notes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT UNSIGNED NOT NULL,
                note TEXT NOT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ticket_id (ticket_id),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS ticket_notes");
    }
}

