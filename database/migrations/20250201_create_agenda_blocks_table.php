<?php

/**
 * Migration: Cria tabela agenda_blocks
 * Instâncias diárias de blocos baseadas no template
 */
class CreateAgendaBlocksTable
{
    public function up(PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS agenda_blocks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                data DATE NOT NULL,
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                tipo_id INT UNSIGNED NOT NULL,
                status ENUM('planned', 'ongoing', 'completed', 'partial', 'canceled') NOT NULL DEFAULT 'planned',
                motivo_cancelamento VARCHAR(255) NULL,
                resumo TEXT NULL,
                projeto_foco_id INT UNSIGNED NULL,
                duracao_planejada INT NOT NULL COMMENT 'Duração em minutos',
                duracao_real INT NULL COMMENT 'Duração real em minutos',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_data (data),
                INDEX idx_tipo_id (tipo_id),
                INDEX idx_status (status),
                INDEX idx_projeto_foco_id (projeto_foco_id),
                UNIQUE KEY unique_block_datetime (data, hora_inicio, hora_fim),
                FOREIGN KEY (tipo_id) REFERENCES agenda_block_types(id) ON DELETE RESTRICT,
                FOREIGN KEY (projeto_foco_id) REFERENCES projects(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec("DROP TABLE IF EXISTS agenda_blocks");
    }
}











