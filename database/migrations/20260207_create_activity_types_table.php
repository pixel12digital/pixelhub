<?php

/**
 * Migration: Cria tabela activity_types (Tipos de Atividade para Atividade avulsa)
 * Ex.: Reunião, Follow-up, Suporte rápido, Prospecção, Alinhamento interno
 */
class CreateActivityTypesTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS activity_types (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_ativo (ativo),
                INDEX idx_sort (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Adiciona activity_type_id em agenda_blocks
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'activity_type_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE agenda_blocks
                ADD COLUMN activity_type_id INT UNSIGNED NULL AFTER projeto_foco_id,
                ADD INDEX idx_activity_type_id (activity_type_id),
                ADD CONSTRAINT agenda_blocks_activity_type_fk
                    FOREIGN KEY (activity_type_id) REFERENCES activity_types(id) ON DELETE SET NULL
            ");
        }
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM agenda_blocks LIKE 'activity_type_id'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE agenda_blocks
                DROP FOREIGN KEY agenda_blocks_activity_type_fk,
                DROP COLUMN activity_type_id
            ");
        }
        $db->exec("DROP TABLE IF EXISTS activity_types");
    }
}
