<?php

/**
 * Migration: Adiciona campos de horário real em agenda_blocks
 * Permite registrar horário real de início e fim do bloco
 */
class AddRealTimeFieldsToAgendaBlocks
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'hora_inicio_real'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE agenda_blocks
                ADD COLUMN hora_inicio_real TIME NULL AFTER hora_fim,
                ADD COLUMN hora_fim_real TIME NULL AFTER hora_inicio_real
            ");
        }
    }

    public function down(PDO $db): void
    {
        // Verifica se as colunas existem antes de remover
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'hora_inicio_real'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE agenda_blocks
                DROP COLUMN hora_fim_real,
                DROP COLUMN hora_inicio_real
            ");
        }
    }
}











