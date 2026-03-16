<?php

/**
 * Migration: Torna hora_inicio e hora_fim opcionais em agenda_blocks
 * Permite planejamento baseado em lista de tarefas (sem horário fixo)
 */
class MakeAgendaTimesOptional
{
    public function up(PDO $db): void
    {
        $db->exec("ALTER TABLE agenda_blocks MODIFY hora_inicio TIME NULL");
        $db->exec("ALTER TABLE agenda_blocks MODIFY hora_fim TIME NULL");
    }

    public function down(PDO $db): void
    {
        $db->exec("UPDATE agenda_blocks SET hora_inicio = '00:00:00' WHERE hora_inicio IS NULL");
        $db->exec("UPDATE agenda_blocks SET hora_fim = '00:00:00' WHERE hora_fim IS NULL");
        $db->exec("ALTER TABLE agenda_blocks MODIFY hora_inicio TIME NOT NULL");
        $db->exec("ALTER TABLE agenda_blocks MODIFY hora_fim TIME NOT NULL");
    }
}
