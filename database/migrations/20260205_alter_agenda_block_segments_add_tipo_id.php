<?php

/**
 * Migration: Adiciona tipo_id em agenda_block_segments
 * Permite registrar trabalho de outro tipo (ex.: Comercial) durante bloco de Produção
 */
class AlterAgendaBlockSegmentsAddTipoId
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_block_segments LIKE 'tipo_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE agenda_block_segments
                ADD COLUMN tipo_id INT UNSIGNED NULL AFTER block_id,
                ADD INDEX idx_tipo_id (tipo_id),
                ADD CONSTRAINT fk_segments_tipo FOREIGN KEY (tipo_id) REFERENCES agenda_block_types(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_block_segments LIKE 'tipo_id'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE agenda_block_segments
                DROP FOREIGN KEY fk_segments_tipo,
                DROP INDEX idx_tipo_id,
                DROP COLUMN tipo_id
            ");
        }
    }
}
