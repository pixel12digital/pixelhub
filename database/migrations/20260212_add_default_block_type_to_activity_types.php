<?php

/**
 * Migration: Adiciona default_block_type_id à tabela activity_types
 * Permite vincular um tipo de bloco padrão a cada tipo de atividade.
 * Ex.: "Almoço" → bloco "Pausa", "Reunião" → bloco "Comercial"
 */
class AddDefaultBlockTypeToActivityTypes
{
    public function up(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM activity_types LIKE 'default_block_type_id'");
        if ($stmt->rowCount() === 0) {
            $db->exec("
                ALTER TABLE activity_types
                ADD COLUMN default_block_type_id INT UNSIGNED NULL AFTER ativo,
                ADD INDEX idx_default_block_type (default_block_type_id)
            ");
        }
    }

    public function down(PDO $db): void
    {
        $stmt = $db->query("SHOW COLUMNS FROM activity_types LIKE 'default_block_type_id'");
        if ($stmt->rowCount() > 0) {
            $db->exec("
                ALTER TABLE activity_types
                DROP COLUMN default_block_type_id
            ");
        }
    }
}
