<?php
/**
 * Script para executar migrations com autoload
 * Uso: php run_migration.php database/migrations/NOME_DA_MIGRATION.php
 */

if ($argc < 2) {
    echo "Uso: php run_migration.php database/migrations/NOME_DA_MIGRATION.php\n";
    exit(1);
}

$migrationFile = $argv[1];

if (!file_exists($migrationFile)) {
    echo "Erro: Arquivo de migration não encontrado: {$migrationFile}\n";
    exit(1);
}

// Carrega autoload do Composer
require_once __DIR__ . '/vendor/autoload.php';

// Carrega configuração do banco
require_once __DIR__ . '/config/database.php';

// Executa a migration
require $migrationFile;
