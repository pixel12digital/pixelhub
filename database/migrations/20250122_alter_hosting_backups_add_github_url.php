<?php

/**
 * Migration: Adiciona campo github_repo_url à tabela hosting_backups
 * Para registrar repositórios GitHub relacionados aos backups
 */
class AlterHostingBackupsAddGithubUrl
{
    public function up(PDO $db): void
    {
        // Verifica se a coluna já existe antes de adicionar
        $columns = $db->query("SHOW COLUMNS FROM hosting_backups")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('github_repo_url', $columns)) {
            $db->exec("ALTER TABLE hosting_backups ADD COLUMN github_repo_url VARCHAR(500) NULL AFTER external_url");
        }
    }

    public function down(PDO $db): void
    {
        // Remove a coluna se existir
        $columns = $db->query("SHOW COLUMNS FROM hosting_backups")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('github_repo_url', $columns)) {
            $db->exec("ALTER TABLE hosting_backups DROP COLUMN github_repo_url");
        }
    }
}

