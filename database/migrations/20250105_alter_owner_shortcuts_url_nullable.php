<?php

/**
 * Migration: Altera coluna url em owner_shortcuts para permitir NULL
 */
class AlterOwnerShortcutsUrlNullable
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna existe e se já permite NULL
        $columns = $db->query("SHOW COLUMNS FROM owner_shortcuts")->fetchAll(PDO::FETCH_ASSOC);
        $urlColumn = null;
        foreach ($columns as $column) {
            if ($column['Field'] === 'url') {
                $urlColumn = $column;
                break;
            }
        }
        
        if ($urlColumn && $urlColumn['Null'] === 'NO') {
            // Altera a coluna para permitir NULL
            $db->exec("ALTER TABLE owner_shortcuts MODIFY COLUMN url VARCHAR(255) NULL");
        }
    }

    public function down(PDO $db): void
    {
        // Reverte: torna a coluna NOT NULL novamente
        // Define um valor padrão vazio para registros que tenham NULL
        $db->exec("UPDATE owner_shortcuts SET url = '' WHERE url IS NULL");
        $db->exec("ALTER TABLE owner_shortcuts MODIFY COLUMN url VARCHAR(255) NOT NULL");
    }
}






