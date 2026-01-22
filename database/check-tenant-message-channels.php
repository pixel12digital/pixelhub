<?php

/**
 * Script para verificar a estrutura e dados da tabela tenant_message_channels
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

echo "=== Verificação: Tabela tenant_message_channels ===\n\n";

try {
    $db = DB::getConnection();
    
    // 1. Mostra a estrutura da tabela
    echo "1. Estrutura da tabela tenant_message_channels:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryStructure = "SHOW COLUMNS FROM tenant_message_channels";
    $stmtStructure = $db->prepare($queryStructure);
    $stmtStructure->execute();
    $structure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($structure)) {
        echo "✗ Tabela não encontrada ou sem estrutura\n";
        exit(1);
    }
    
    // Cabeçalho da tabela
    printf("%-30s %-20s %-10s %-10s %-15s %-15s\n", 
        "Field", "Type", "Null", "Key", "Default", "Extra");
    echo str_repeat("-", 100) . "\n";
    
    foreach ($structure as $col) {
        printf("%-30s %-20s %-10s %-10s %-15s %-15s\n",
            $col['Field'],
            substr($col['Type'], 0, 19),
            $col['Null'],
            $col['Key'],
            $col['Default'] ?? 'NULL',
            $col['Extra'] ?? ''
        );
    }
    
    // 2. Busca alguns registros de exemplo
    echo "\n\n2. Registros de exemplo (primeiros 5):\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryData = "SELECT * FROM tenant_message_channels LIMIT 5";
    $stmtData = $db->prepare($queryData);
    $stmtData->execute();
    $results = $stmtData->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum registro encontrado na tabela\n";
    } else {
        echo "✓ Encontrados " . count($results) . " registro(s)\n\n";
        
        // Exibe cada registro
        foreach ($results as $index => $row) {
            echo "REGISTRO " . ($index + 1) . ":\n";
            echo str_repeat("-", 100) . "\n";
            foreach ($row as $key => $value) {
                $displayValue = $value;
                if (is_string($value) && strlen($value) > 200) {
                    $displayValue = substr($value, 0, 200) . '...';
                }
                echo sprintf("%-30s: %s\n", $key, $displayValue ?? 'NULL');
            }
            echo "\n";
        }
    }
    
    // 3. Verifica se existe registro para ImobSites
    echo "\n3. Verificando se existe registro para 'ImobSites':\n";
    echo str_repeat("=", 100) . "\n";
    
    // Tenta diferentes campos que podem conter o nome do canal
    $columns = array_column($structure, 'Field');
    $foundImobSites = false;
    
    foreach ($columns as $col) {
        if (stripos($col, 'name') !== false || 
            stripos($col, 'channel') !== false || 
            stripos($col, 'session') !== false ||
            stripos($col, 'identifier') !== false) {
            
            try {
                $queryCheck = "SELECT * FROM tenant_message_channels WHERE {$col} LIKE '%ImobSites%' OR {$col} = 'ImobSites' LIMIT 5";
                $stmtCheck = $db->prepare($queryCheck);
                $stmtCheck->execute();
                $checkResults = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($checkResults)) {
                    $foundImobSites = true;
                    echo "✓ Encontrado(s) " . count($checkResults) . " registro(s) com 'ImobSites' no campo '{$col}':\n\n";
                    foreach ($checkResults as $checkRow) {
                        echo "REGISTRO:\n";
                        echo str_repeat("-", 80) . "\n";
                        foreach ($checkRow as $key => $value) {
                            $displayValue = $value;
                            if (is_string($value) && strlen($value) > 200) {
                                $displayValue = substr($value, 0, 200) . '...';
                            }
                            echo sprintf("%-30s: %s\n", $key, $displayValue ?? 'NULL');
                        }
                        echo "\n";
                    }
                    break;
                }
            } catch (\Exception $e) {
                // Ignora erros de tipo de coluna
            }
        }
    }
    
    if (!$foundImobSites) {
        echo "✗ Nenhum registro encontrado com 'ImobSites' em nenhum campo\n";
    }
    
    // 4. Contagem total de registros
    echo "\n4. Estatísticas:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryCount = "SELECT COUNT(*) as total FROM tenant_message_channels";
    $stmtCount = $db->prepare($queryCount);
    $stmtCount->execute();
    $countResult = $stmtCount->fetch(PDO::FETCH_ASSOC);
    
    echo "Total de registros na tabela: " . ($countResult['total'] ?? 0) . "\n";
    
    // Lista valores únicos de campos importantes
    foreach ($columns as $col) {
        if (stripos($col, 'name') !== false || 
            stripos($col, 'channel') !== false || 
            stripos($col, 'tenant') !== false) {
            
            try {
                $queryDistinct = "SELECT DISTINCT {$col} FROM tenant_message_channels WHERE {$col} IS NOT NULL LIMIT 10";
                $stmtDistinct = $db->prepare($queryDistinct);
                $stmtDistinct->execute();
                $distinctResults = $stmtDistinct->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($distinctResults)) {
                    echo "\nValores únicos no campo '{$col}':\n";
                    foreach ($distinctResults as $val) {
                        echo "  - " . substr($val, 0, 50) . "\n";
                    }
                }
            } catch (\Exception $e) {
                // Ignora erros
            }
        }
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

