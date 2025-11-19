<?php

/**
 * Migration: Adiciona campos type, is_customer_visible e template na tabela projects
 */
class AlterProjectsAddTypeAndVisibility
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('type', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN type VARCHAR(30) NOT NULL DEFAULT 'interno' AFTER status");
        }
        
        if (!in_array('is_customer_visible', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN is_customer_visible TINYINT(1) NOT NULL DEFAULT 0 AFTER type");
        }
        
        if (!in_array('template', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN template VARCHAR(50) NULL AFTER is_customer_visible");
        }
        
        // Atualiza projetos existentes para garantir valores padrão
        $db->exec("
            UPDATE projects 
            SET type = 'interno', is_customer_visible = 0 
            WHERE type IS NULL OR type = ''
        ");
    }

    public function down(PDO $db): void
    {
        // Remove apenas as colunas adicionadas por esta migration
        $columns = $db->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('template', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN template");
        }
        if (in_array('is_customer_visible', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN is_customer_visible");
        }
        if (in_array('type', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN type");
        }
    }
}

