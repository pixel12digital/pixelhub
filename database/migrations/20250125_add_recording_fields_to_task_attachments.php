<?php

/**
 * Migration: Adiciona campos recording_type e duration à tabela task_attachments
 * Para suportar gravações de tela diferenciadas de anexos comuns
 */
class AddRecordingFieldsToTaskAttachments
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM task_attachments")->fetchAll(PDO::FETCH_COLUMN);
        
        // Adiciona recording_type após mime_type
        if (!in_array('recording_type', $columns)) {
            $db->exec("ALTER TABLE task_attachments ADD COLUMN recording_type VARCHAR(50) NULL AFTER mime_type");
        }
        
        // Adiciona duration após file_size
        if (!in_array('duration', $columns)) {
            $db->exec("ALTER TABLE task_attachments ADD COLUMN duration INT UNSIGNED NULL AFTER file_size");
        }
        
        // Verifica se o índice já existe antes de criar
        $indexes = $db->query("SHOW INDEXES FROM task_attachments WHERE Key_name = 'idx_recording_type'")->fetchAll();
        
        if (empty($indexes)) {
            $db->exec("CREATE INDEX idx_recording_type ON task_attachments(recording_type)");
        }
    }

    public function down(PDO $db): void
    {
        // Remove o índice se existir
        $indexes = $db->query("SHOW INDEXES FROM task_attachments WHERE Key_name = 'idx_recording_type'")->fetchAll();
        
        if (!empty($indexes)) {
            $db->exec("DROP INDEX idx_recording_type ON task_attachments");
        }
        
        // Remove as colunas se existirem
        $columns = $db->query("SHOW COLUMNS FROM task_attachments")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('duration', $columns)) {
            $db->exec("ALTER TABLE task_attachments DROP COLUMN duration");
        }
        
        if (in_array('recording_type', $columns)) {
            $db->exec("ALTER TABLE task_attachments DROP COLUMN recording_type");
        }
    }
}


