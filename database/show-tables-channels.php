<?php

/**
 * Script para listar tabelas relacionadas a canais
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
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

Env::load();

echo "=== Tabelas relacionadas a canais ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "SHOW TABLES LIKE '%channel%'";
    
    $stmt = $db->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($results)) {
        echo "✗ Nenhuma tabela encontrada com 'channel' no nome.\n\n";
        exit(0);
    }
    
    echo "✓ Encontradas " . count($results) . " tabela(s):\n\n";
    
    foreach ($results as $tableName) {
        echo "  - {$tableName}\n";
    }
    
    echo "\n";
    
    // Para cada tabela, mostra estrutura básica
    foreach ($results as $tableName) {
        echo "Estrutura da tabela: {$tableName}\n";
        echo str_repeat("-", 80) . "\n";
        
        $columnsStmt = $db->query("SHOW COLUMNS FROM `{$tableName}`");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        printf("%-30s | %-20s | %-10s | %-10s\n", "Field", "Type", "Null", "Key");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($columns as $col) {
            printf("%-30s | %-20s | %-10s | %-10s\n",
                $col['Field'],
                $col['Type'],
                $col['Null'],
                $col['Key']
            );
        }
        
        echo "\n";
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}










