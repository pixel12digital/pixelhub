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

// Inclui a classe da migration
require_once $migrationFile;

// Detecta o nome da classe a partir do nome do arquivo
$filename  = pathinfo($migrationFile, PATHINFO_FILENAME);
// Remove prefixo de data (ex: 20260312_) e converte snake_case em PascalCase
$parts     = explode('_', preg_replace('/^\d+_/', '', $filename));
$className = implode('', array_map('ucfirst', $parts));

if (!class_exists($className)) {
    echo "Erro: Classe '{$className}' não encontrada no arquivo {$migrationFile}\n";
    exit(1);
}

try {
    $db        = \PixelHub\Core\DB::getConnection();
    $migration = new $className();
    $migration->up($db);
    echo "Migration '{$className}' executada com sucesso.\n";
} catch (\Exception $e) {
    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
    exit(1);
}
