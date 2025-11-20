<?php

/**
 * Script para verificar estrutura da tabela tenants
 * Verifica se as colunas is_archived e is_financial_only existem
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

echo "=== Verificação da Estrutura da Tabela tenants ===\n\n";

try {
    $db = DB::getConnection();
    
    // Descreve a tabela tenants
    $stmt = $db->query("DESCRIBE tenants");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colunas encontradas na tabela tenants:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-30s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    
    $hasIsArchived = false;
    $hasIsFinancialOnly = false;
    
    foreach ($columns as $column) {
        printf("%-30s %-20s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key']
        );
        
        if ($column['Field'] === 'is_archived') {
            $hasIsArchived = true;
        }
        if ($column['Field'] === 'is_financial_only') {
            $hasIsFinancialOnly = true;
        }
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // Verifica se as colunas necessárias existem
    echo "Verificação das colunas de arquivamento:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($hasIsArchived) {
        echo "✅ Coluna 'is_archived' existe\n";
    } else {
        echo "❌ Coluna 'is_archived' NÃO existe\n";
    }
    
    if ($hasIsFinancialOnly) {
        echo "✅ Coluna 'is_financial_only' existe\n";
    } else {
        echo "❌ Coluna 'is_financial_only' NÃO existe\n";
    }
    
    echo str_repeat("-", 80) . "\n\n";
    
    // Se não existirem, mostra o SQL necessário
    if (!$hasIsArchived || !$hasIsFinancialOnly) {
        echo "⚠️  ATENÇÃO: Colunas faltando! Execute o SQL abaixo no phpMyAdmin:\n\n";
        echo "SQL para adicionar as colunas faltantes:\n";
        echo str_repeat("=", 80) . "\n";
        
        $sqlParts = [];
        if (!$hasIsArchived) {
            $sqlParts[] = "ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER status";
        }
        if (!$hasIsFinancialOnly) {
            // Se is_archived existe, adiciona depois dela, senão depois de status
            $afterColumn = $hasIsArchived ? 'is_archived' : 'status';
            $sqlParts[] = "ADD COLUMN is_financial_only TINYINT(1) NOT NULL DEFAULT 0 AFTER {$afterColumn}";
        }
        
        if (!empty($sqlParts)) {
            echo "ALTER TABLE tenants\n";
            echo "  " . implode(",\n  ", $sqlParts);
            
            // Adiciona índices
            $indexParts = [];
            if (!$hasIsArchived) {
                $indexParts[] = "ADD INDEX idx_is_archived (is_archived)";
            }
            if (!$hasIsFinancialOnly) {
                $indexParts[] = "ADD INDEX idx_is_financial_only (is_financial_only)";
            }
            if (!empty($indexParts)) {
                echo ",\n  " . implode(",\n  ", $indexParts);
            }
            echo ";\n";
        }
        
        echo str_repeat("=", 80) . "\n";
    } else {
        echo "✅ Todas as colunas necessárias existem!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Erro ao verificar estrutura: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

