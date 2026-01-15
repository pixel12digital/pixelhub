<?php

/**
 * Script para listar tenants e identificar o tenant_id do ImobSites
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

echo "=== Verificação: Tenants - Identificar tenant_id do ImobSites ===\n\n";

try {
    $db = DB::getConnection();
    
    // Lista todos os tenants
    echo "1. Listando todos os tenants:\n";
    echo str_repeat("=", 100) . "\n";
    
    $query = "SELECT id, name FROM tenants ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum tenant encontrado\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " tenant(s)\n\n";
    
    // Cabeçalho da tabela
    printf("%-10s %-50s\n", "ID", "Name");
    echo str_repeat("-", 100) . "\n";
    
    $foundImobSites = false;
    $imobSitesId = null;
    
    foreach ($results as $row) {
        $id = $row['id'] ?? 'NULL';
        $name = $row['name'] ?? 'NULL';
        
        // Verifica se contém ImobSites
        if (stripos($name, 'ImobSites') !== false || 
            stripos($name, 'Imob') !== false ||
            stripos($name, 'imob') !== false) {
            $foundImobSites = true;
            $imobSitesId = $id;
            printf("%-10s %-50s ⭐ (POSSÍVEL MATCH)\n", $id, substr($name, 0, 49));
        } else {
            printf("%-10s %-50s\n", $id, substr($name, 0, 49));
        }
    }
    
    echo "\n";
    
    // Busca específica por ImobSites
    echo "\n2. Busca específica por 'ImobSites':\n";
    echo str_repeat("=", 100) . "\n";
    
    $querySearch = "SELECT id, name FROM tenants WHERE name LIKE '%ImobSites%' OR name LIKE '%Imob%' ORDER BY id";
    $stmtSearch = $db->prepare($querySearch);
    $stmtSearch->execute();
    $searchResults = $stmtSearch->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($searchResults)) {
        echo "✓ Encontrado(s) " . count($searchResults) . " tenant(s) com 'Imob' no nome:\n\n";
        foreach ($searchResults as $row) {
            echo "ID:   " . ($row['id'] ?? 'NULL') . "\n";
            echo "Name: " . ($row['name'] ?? 'NULL') . "\n";
            echo "\n";
        }
    } else {
        echo "✗ Nenhum tenant encontrado com 'Imob' no nome\n";
    }
    
    // Verifica se há outros campos na tabela que possam ajudar
    echo "\n3. Verificando estrutura completa da tabela tenants:\n";
    echo str_repeat("=", 100) . "\n";
    
    $queryStructure = "DESCRIBE tenants";
    $stmtStructure = $db->prepare($queryStructure);
    $stmtStructure->execute();
    $structure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);
    
    $allColumns = array_column($structure, 'Field');
    echo "Colunas disponíveis: " . implode(', ', $allColumns) . "\n\n";
    
    // Se houver outros campos relevantes, busca com mais informações
    if (in_array('slug', $allColumns) || in_array('domain', $allColumns) || in_array('identifier', $allColumns)) {
        $extraColumns = [];
        if (in_array('slug', $allColumns)) $extraColumns[] = 'slug';
        if (in_array('domain', $allColumns)) $extraColumns[] = 'domain';
        if (in_array('identifier', $allColumns)) $extraColumns[] = 'identifier';
        
        $extraColsStr = implode(', ', $extraColumns);
        $queryExtra = "SELECT id, name, {$extraColsStr} FROM tenants ORDER BY id";
        $stmtExtra = $db->prepare($queryExtra);
        $stmtExtra->execute();
        $extraResults = $stmtExtra->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Lista completa com campos adicionais:\n";
        echo str_repeat("-", 100) . "\n";
        foreach ($extraResults as $row) {
            echo "ID: " . ($row['id'] ?? 'NULL') . " | Name: " . ($row['name'] ?? 'NULL');
            foreach ($extraColumns as $col) {
                echo " | {$col}: " . ($row[$col] ?? 'NULL');
            }
            echo "\n";
        }
    }
    
    // Resumo final
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "RESUMO:\n";
    echo str_repeat("-", 100) . "\n";
    
    if ($foundImobSites) {
        echo "✓ Tenant com 'Imob' no nome encontrado:\n";
        echo "  ID: {$imobSitesId}\n";
        echo "\n";
        echo "💡 Este é provavelmente o tenant_id que deve ser usado no registro de tenant_message_channels\n";
    } else {
        echo "⚠ Nenhum tenant encontrado com 'ImobSites' ou 'Imob' no nome\n";
        echo "  Você pode precisar:\n";
        echo "  1. Criar um novo tenant para ImobSites\n";
        echo "  2. Ou verificar se o nome está diferente na tabela\n";
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

