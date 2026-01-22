<?php

/**
 * Script para verificar a estrutura da tabela whatsapp_generic_logs
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

echo "=== Verificação: Estrutura da tabela whatsapp_generic_logs ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica a estrutura da tabela
    echo "1. Estrutura da tabela whatsapp_generic_logs:\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryStructure = "DESCRIBE whatsapp_generic_logs";
    $stmtStructure = $db->prepare($queryStructure);
    $stmtStructure->execute();
    $structure = $stmtStructure->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($structure)) {
        echo "✗ Tabela não encontrada ou sem estrutura\n";
        exit(1);
    }
    
    echo "Colunas encontradas:\n";
    foreach ($structure as $col) {
        echo "  - {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'YES' ? ' NULL' : ' NOT NULL') . "\n";
    }
    
    // Lista todas as colunas para usar na query
    $columns = array_column($structure, 'Field');
    $columnsStr = implode(', ', $columns);
    
    echo "\n\n2. Últimos 20 registros (todas as colunas):\n";
    echo str_repeat("-", 80) . "\n";
    
    $queryGeneral = "SELECT {$columnsStr} FROM whatsapp_generic_logs ORDER BY id DESC LIMIT 20";
    $stmtGeneral = $db->prepare($queryGeneral);
    $stmtGeneral->execute();
    $resultsGeneral = $stmtGeneral->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($resultsGeneral)) {
        echo "✗ Nenhum registro encontrado\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($resultsGeneral) . " registro(s)\n\n";
    
    // Identifica qual coluna pode conter dados JSON ou texto
    $dataColumns = [];
    foreach ($columns as $col) {
        if (in_array(strtolower($col), ['data', 'content', 'message', 'body', 'json', 'log', 'info', 'details', 'request', 'response'])) {
            $dataColumns[] = $col;
        }
    }
    
    // Se não encontrou colunas óbvias, tenta todas exceto id e created_at
    if (empty($dataColumns)) {
        $dataColumns = array_filter($columns, function($col) {
            return !in_array(strtolower($col), ['id', 'created_at', 'updated_at']);
        });
    }
    
    echo "Colunas que podem conter dados: " . implode(', ', $dataColumns) . "\n\n";
    
    // Analisa os registros procurando por ImobSites
    $foundImobSites = false;
    $foundNumber = false;
    $foundFields = [];
    $matchingRecords = [];
    
    foreach ($resultsGeneral as $row) {
        $rowText = json_encode($row);
        
        // Verifica se contém ImobSites
        if (stripos($rowText, 'ImobSites') !== false) {
            $foundImobSites = true;
            $matchingRecords[] = $row;
        }
        
        // Verifica se contém o número
        if (stripos($rowText, '554796164699') !== false) {
            $foundNumber = true;
        }
        
        // Verifica campos comuns
        foreach ($row as $key => $value) {
            if (stripos($key, 'session') !== false || stripos($key, 'tenant') !== false || 
                stripos($key, 'channel') !== false || stripos($key, 'source') !== false ||
                stripos($key, 'event') !== false || stripos($key, 'from') !== false) {
                $foundFields[$key] = true;
            }
        }
    }
    
    // Exibe resumo
    echo "\n3. Análise dos registros...\n";
    echo str_repeat("-", 80) . "\n";
    echo "Aparece 'ImobSites' em algum registro? " . ($foundImobSites ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "Aparece 'from' com o número 554796164699? " . ($foundNumber ? "✓ SIM" : "✗ NÃO") . "\n";
    echo "\nCampos encontrados nos registros:\n";
    if (empty($foundFields)) {
        echo "  ✗ Nenhum campo específico encontrado (tenant_id, channel, source, eventType)\n";
    } else {
        foreach ($foundFields as $field => $value) {
            echo "  ✓ {$field}\n";
        }
    }
    
    // Se encontrou ImobSites, tenta buscar mais registros
    if ($foundImobSites) {
        echo "\n\n4. Buscando todos os registros com 'ImobSites'...\n";
        echo str_repeat("=", 80) . "\n";
        
        // Tenta buscar em cada coluna de dados
        $allMatches = [];
        foreach ($dataColumns as $col) {
            try {
                $queryFiltered = "SELECT {$columnsStr} FROM whatsapp_generic_logs WHERE {$col} LIKE '%ImobSites%' ORDER BY id DESC LIMIT 50";
                $stmtFiltered = $db->prepare($queryFiltered);
                $stmtFiltered->execute();
                $filtered = $stmtFiltered->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($filtered)) {
                    $allMatches = array_merge($allMatches, $filtered);
                }
            } catch (\Exception $e) {
                // Ignora erros de tipo de coluna
            }
        }
        
        // Remove duplicatas
        $uniqueMatches = [];
        $seenIds = [];
        foreach ($allMatches as $match) {
            if (!in_array($match['id'], $seenIds)) {
                $uniqueMatches[] = $match;
                $seenIds[] = $match['id'];
            }
        }
        
        if (!empty($uniqueMatches)) {
            echo "✓ Encontrados " . count($uniqueMatches) . " registro(s) com 'ImobSites'\n\n";
            foreach (array_slice($uniqueMatches, 0, 10) as $index => $row) {
                echo "REGISTRO " . ($index + 1) . ":\n";
                echo str_repeat("-", 80) . "\n";
                foreach ($row as $key => $value) {
                    $displayValue = $value;
                    if (is_string($value) && strlen($value) > 200) {
                        $displayValue = substr($value, 0, 200) . '...';
                    }
                    echo "  {$key}: " . ($displayValue ?? 'NULL') . "\n";
                }
                echo "\n";
            }
        }
    }
    
    // Exibe amostra dos registros
    echo "\n\n5. Amostra dos últimos registros (primeiros 3):\n";
    echo str_repeat("=", 80) . "\n";
    
    $sampleSize = min(3, count($resultsGeneral));
    for ($i = 0; $i < $sampleSize; $i++) {
        $row = $resultsGeneral[$i];
        echo "\nREGISTRO " . ($i + 1) . ":\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($row as $key => $value) {
            $displayValue = $value;
            if (is_string($value) && strlen($value) > 300) {
                $displayValue = substr($value, 0, 300) . '...';
            }
            echo "  {$key}: " . ($displayValue ?? 'NULL') . "\n";
        }
    }
    
    // Diagnóstico final
    echo "\n\n" . str_repeat("=", 80) . "\n";
    echo "DIAGNÓSTICO FINAL:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($foundImobSites) {
        echo "✓ O Hub ESTÁ registrando 'ImobSites' nos logs\n";
    } else {
        echo "✗ O Hub NÃO está registrando 'ImobSites' nos logs\n";
    }
    
    if ($foundNumber) {
        echo "✓ O número 554796164699 aparece nos logs\n";
    } else {
        echo "✗ O número 554796164699 NÃO aparece nos logs\n";
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

