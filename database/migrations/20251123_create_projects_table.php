<?php

/**
 * Migration: Altera tabela projects para adicionar campos de Projetos & Tarefas
 * Nota: A tabela projects já existe com campos diferentes (slug, external_project_id, base_url)
 * Esta migration adiciona os campos necessários para o módulo de Projetos & Tarefas
 */
class CreateProjectsTable
{
    public function up(PDO $db): void
    {
        // Verifica se as colunas já existem antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('description', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN description TEXT NULL AFTER name");
        }
        
        // Altera status para aceitar valores 'ativo' e 'arquivado' (mantém compatibilidade com 'active')
        // Não alteramos o tipo, apenas adicionamos os novos valores possíveis
        
        if (!in_array('priority', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'media' AFTER status");
        }
        
        if (!in_array('due_date', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN due_date DATE NULL AFTER priority");
        }
        
        if (!in_array('created_by', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN created_by INT UNSIGNED NULL AFTER due_date");
        }
        
        if (!in_array('updated_by', $columns)) {
            $db->exec("ALTER TABLE projects ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by");
        }
        
        // Ajusta created_at e updated_at se necessário
        $db->exec("
            ALTER TABLE projects 
            MODIFY COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
        ");
        
        // Adiciona índices se não existirem
        $indexes = $db->query("SHOW INDEXES FROM projects")->fetchAll(PDO::FETCH_ASSOC);
        $indexNames = array_column($indexes, 'Key_name');
        if (!in_array('idx_status', $indexNames)) {
            $db->exec("ALTER TABLE projects ADD INDEX idx_status (status)");
        }
    }

    public function down(PDO $db): void
    {
        // Remove apenas as colunas adicionadas por esta migration
        $columns = $db->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('description', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN description");
        }
        if (in_array('priority', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN priority");
        }
        if (in_array('due_date', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN due_date");
        }
        if (in_array('created_by', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN created_by");
        }
        if (in_array('updated_by', $columns)) {
            $db->exec("ALTER TABLE projects DROP COLUMN updated_by");
        }
    }
}

