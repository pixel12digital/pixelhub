<?php

/**
 * Script para mostrar a estrutura completa da tabela tenant_message_channels
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

echo "=== Estrutura da Tabela tenant_message_channels ===\n\n";

try {
    $db = DB::getConnection();
    
    // SHOW CREATE TABLE
    echo "1. SHOW CREATE TABLE tenant_message_channels:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryCreate = "SHOW CREATE TABLE tenant_message_channels";
    $stmtCreate = $db->prepare($queryCreate);
    $stmtCreate->execute();
    $result = $stmtCreate->fetch(PDO::FETCH_ASSOC);
    
    if (isset($result['Create Table'])) {
        echo $result['Create Table'] . "\n";
    } else {
        echo "✗ Não foi possível obter a estrutura da tabela\n";
    }
    
    // SHOW INDEX (complementar)
    echo "\n\n2. SHOW INDEX FROM tenant_message_channels:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryIndex = "SHOW INDEX FROM tenant_message_channels";
    $stmtIndex = $db->prepare($queryIndex);
    $stmtIndex->execute();
    $indexes = $stmtIndex->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($indexes)) {
        // Cabeçalho
        printf("%-30s %-10s %-20s %-10s %-20s %-10s\n",
            "Table", "Non_unique", "Key_name", "Seq_in_index", "Column_name", "Cardinality");
        echo str_repeat("-", 100) . "\n";
        
        foreach ($indexes as $index) {
            printf("%-30s %-10s %-20s %-10s %-20s %-10s\n",
                substr($index['Table'] ?? 'NULL', 0, 29),
                $index['Non_unique'] ?? 'NULL',
                substr($index['Key_name'] ?? 'NULL', 0, 19),
                $index['Seq_in_index'] ?? 'NULL',
                substr($index['Column_name'] ?? 'NULL', 0, 19),
                $index['Cardinality'] ?? 'NULL'
            );
        }
        
        // Identifica índices únicos
        echo "\n" . str_repeat("-", 100) . "\n";
        echo "ÍNDICES ÚNICOS IDENTIFICADOS:\n";
        $uniqueIndexes = [];
        foreach ($indexes as $index) {
            if (($index['Non_unique'] ?? 1) == 0) {
                $keyName = $index['Key_name'] ?? '';
                if (!isset($uniqueIndexes[$keyName])) {
                    $uniqueIndexes[$keyName] = [];
                }
                $uniqueIndexes[$keyName][] = $index['Column_name'] ?? '';
            }
        }
        
        if (empty($uniqueIndexes)) {
            echo "  Nenhum índice único encontrado\n";
        } else {
            foreach ($uniqueIndexes as $keyName => $columns) {
                echo "  - {$keyName}: " . implode(', ', $columns) . "\n";
            }
        }
    } else {
        echo "✗ Nenhum índice encontrado\n";
    }
    
    echo "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

