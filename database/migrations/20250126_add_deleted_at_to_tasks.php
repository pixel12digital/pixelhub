<?php

/**
 * Migration: Adiciona coluna deleted_at para soft delete em tarefas
 */
class AddDeletedAtToTasks
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM tasks")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('deleted_at', $columns)) {
            $db->exec("
                ALTER TABLE tasks
                ADD COLUMN deleted_at DATETIME NULL AFTER updated_at
            ");
        }
        
        // Verifica se o índice já existe antes de criar
        $indexes = $db->query("SHOW INDEXES FROM tasks WHERE Key_name = 'idx_deleted_at'")->fetchAll();
        
        if (empty($indexes)) {
            $db->exec("CREATE INDEX idx_deleted_at ON tasks(deleted_at)");
        }
    }

    public function down(PDO $db): void
    {
        // Remove o índice se existir
        $indexes = $db->query("SHOW INDEXES FROM tasks WHERE Key_name = 'idx_deleted_at'")->fetchAll();
        
        if (!empty($indexes)) {
            $db->exec("DROP INDEX idx_deleted_at ON tasks");
        }
        
        // Remove a coluna se existir
        $columns = $db->query("SHOW COLUMNS FROM tasks")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('deleted_at', $columns)) {
            $db->exec("ALTER TABLE tasks DROP COLUMN deleted_at");
        }
    }
}

