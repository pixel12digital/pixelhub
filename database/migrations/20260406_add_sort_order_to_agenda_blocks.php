<?php
/**
 * Migration: adiciona coluna sort_order em agenda_blocks
 * Permite reordenação manual dos blocos por arrastar e soltar.
 */

class AddSortOrderToAgendaBlocks
{
    public function up(\PDO $db): void
    {
        $db->exec("
            ALTER TABLE agenda_blocks
            ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER ticket_id
        ");

        $db->exec("
            UPDATE agenda_blocks b
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (PARTITION BY data ORDER BY
                           (CASE WHEN hora_inicio IS NULL THEN 1 ELSE 0 END) ASC,
                           hora_inicio ASC,
                           created_at ASC
                       ) AS rn
                FROM agenda_blocks
            ) ranked ON b.id = ranked.id
            SET b.sort_order = ranked.rn
        ");

        echo "  sort_order adicionado e inicializado em agenda_blocks\n";
    }
}
