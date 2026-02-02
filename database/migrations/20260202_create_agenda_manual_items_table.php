<?php

/**
 * Migration: Cria tabela agenda_manual_items
 * Itens manuais da agenda (CRM, reuniões, agendamentos pontuais)
 */
class CreateAgendaManualItemsTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_manual_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                item_date DATE NOT NULL,
                time_start TIME NULL,
                time_end TIME NULL,
                item_type VARCHAR(50) NULL DEFAULT 'outro',
                notes TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_item_date (item_date),
                INDEX idx_item_type (item_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_manual_items");
    }
}
