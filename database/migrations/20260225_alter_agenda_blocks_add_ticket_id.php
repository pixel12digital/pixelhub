<?php

/**
 * Migration: Adiciona ticket_id à tabela agenda_blocks
 * Permite vincular tickets de suporte aos blocos de agenda (especialmente blocos SUPORTE)
 */
class AlterAgendaBlocksAddTicketId
{
    public function up(PDO $db): void
    {
        $db->exec("
            ALTER TABLE agenda_blocks
            ADD COLUMN ticket_id INT UNSIGNED NULL AFTER projeto_foco_id,
            ADD INDEX idx_ticket_id (ticket_id),
            ADD CONSTRAINT fk_agenda_blocks_ticket_id 
                FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("
            ALTER TABLE agenda_blocks
            DROP FOREIGN KEY fk_agenda_blocks_ticket_id,
            DROP INDEX idx_ticket_id,
            DROP COLUMN ticket_id
        ");
    }
}
