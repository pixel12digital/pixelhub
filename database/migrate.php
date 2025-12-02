<?php

/**
 * Script CLI para executar migrations
 * 
 * Uso: php database/migrate.php
 */

// Carrega autoload do Composer se existir, senão carrega manualmente
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Autoload manual simples
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
Env::load();

// Inicia sessão se necessário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "=== Sistema de Migrations - Pixel Hub ===\n\n";

try {
    $db = DB::getConnection();
    
    // Cria tabela de controle de migrations se não existir
    $db->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL UNIQUE,
            run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✓ Tabela de controle de migrations verificada\n\n";
    
    // Busca migrations já executadas
    $stmt = $db->query("SELECT migration_name FROM migrations");
    $executed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Busca todos os arquivos de migration
    $migrationsDir = __DIR__ . '/migrations';
    $files = glob($migrationsDir . '/*.php');
    
    if (empty($files)) {
        echo "Nenhuma migration encontrada em {$migrationsDir}\n";
        exit(0);
    }
    
    // Ordena por nome (que contém data)
    sort($files);
    
    $executedCount = 0;
    
    foreach ($files as $file) {
        $migrationName = basename($file, '.php');
        
        // Pula se já foi executada
        if (in_array($migrationName, $executed)) {
            echo "⊘ {$migrationName} - já executada\n";
            continue;
        }
        
        echo "→ Executando {$migrationName}...\n";
        
        // Carrega a classe da migration
        require_once $file;
        
        // Remove a data do início do nome (formato: YYYYMMDD_nome_da_migration)
        // Também remove prefixo numérico opcional (ex: YYYYMMDD_01_nome_da_migration)
        $nameWithoutDate = preg_replace('/^\d{8}_\d{2}_/', '', $migrationName); // Tenta com prefixo numérico primeiro
        if ($nameWithoutDate === $migrationName) {
            $nameWithoutDate = preg_replace('/^\d{8}_/', '', $migrationName); // Se não funcionou, tenta sem prefixo numérico
        }
        // Converte para PascalCase
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $nameWithoutDate)));
        
        if (!class_exists($className)) {
            echo "  ✗ Classe {$className} não encontrada no arquivo {$migrationName}\n";
            continue;
        }
        
        $migration = new $className();
        
        if (!method_exists($migration, 'up')) {
            echo "  ✗ Método up() não encontrado\n";
            continue;
        }
        
        // Executa a migration
        try {
            $migration->up($db);
            
            // Registra a migration
            $stmt = $db->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->execute([$migrationName]);
            
            echo "  ✓ {$migrationName} executada com sucesso\n";
            $executedCount++;
        } catch (\Exception $e) {
            echo "  ✗ Erro ao executar {$migrationName}: " . $e->getMessage() . "\n";
            error_log("Erro na migration {$migrationName}: " . $e->getMessage());
            throw $e;
        }
    }
    
    echo "\n=== Resumo ===\n";
    echo "Migrations executadas nesta execução: {$executedCount}\n";
    echo "Total de migrations no banco: " . count($executed) + $executedCount . "\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro fatal: " . $e->getMessage() . "\n";
    error_log("Erro fatal no migrate.php: " . $e->getMessage());
    exit(1);
}

echo "\n✓ Processo concluído!\n";

