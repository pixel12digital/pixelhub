<?php

/**
 * Script para testar a query SQL da biblioteca de gravações
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

echo "=== Teste de Query SQL - Biblioteca de Gravações ===\n\n";

try {
    $db = DB::getConnection();
    
    // Simula os parâmetros do controller
    $search = '';
    $dateFrom = '';
    $dateTo = '';
    $page = 1;
    $perPage = 20;
    $offset = 0;
    
    // Monta WHERE clause
    $whereConditions = ["ta.recording_type = 'screen_recording'"];
    $params = [];
    
    // Filtro de busca (se houver)
    if (!empty($search)) {
        $whereConditions[] = "(
            ta.original_name LIKE ? OR 
            ta.file_name LIKE ? OR 
            t.title LIKE ? OR 
            c.name LIKE ?
        )";
        $searchParam = '%' . $search . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // Filtro de data (from)
    if (!empty($dateFrom)) {
        $whereConditions[] = "DATE(ta.uploaded_at) >= ?";
        $params[] = $dateFrom;
    }
    
    // Filtro de data (to)
    if (!empty($dateTo)) {
        $whereConditions[] = "DATE(ta.uploaded_at) <= ?";
        $params[] = $dateTo;
    }
    
    $whereSql = implode(' AND ', $whereConditions);
    
    // Query de contagem
    echo "=== Teste 1: Query de Contagem ===\n";
    $countSql = "
        SELECT COUNT(DISTINCT ta.id) as total
        FROM task_attachments ta
        LEFT JOIN tasks t ON ta.task_id = t.id
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN tenants c ON p.tenant_id = c.id
        WHERE {$whereSql}
    ";
    
    echo "SQL: " . $countSql . "\n";
    echo "Params: " . print_r($params, true) . "\n";
    
    try {
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        echo "✓ Query de contagem executada com sucesso!\n";
        echo "Total encontrado: {$total}\n\n";
    } catch (\PDOException $e) {
        echo "✗ Erro na query de contagem: " . $e->getMessage() . "\n";
        echo "Código do erro: " . $e->getCode() . "\n\n";
        throw $e;
    }
    
    // Query principal
    echo "=== Teste 2: Query Principal ===\n";
    $sql = "
        SELECT 
            ta.id,
            ta.task_id,
            ta.file_name,
            ta.original_name,
            ta.file_size,
            ta.mime_type,
            ta.duration,
            ta.uploaded_at,
            ta.uploaded_by,
            ta.file_path,
            t.title as task_title,
            t.id as task_id_real,
            t.project_id,
            c.name as client_name,
            c.id as client_id,
            u.name as uploaded_by_name
        FROM task_attachments ta
        LEFT JOIN tasks t ON ta.task_id = t.id
        LEFT JOIN projects p ON t.project_id = p.id
        LEFT JOIN tenants c ON p.tenant_id = c.id
        LEFT JOIN users u ON ta.uploaded_by = u.id
        WHERE {$whereSql}
        ORDER BY ta.uploaded_at DESC, ta.id DESC
        LIMIT ? OFFSET ?
    ";
    
    echo "SQL: " . $sql . "\n";
    $allParams = array_merge($params, [$perPage, $offset]);
    echo "Params: " . print_r($allParams, true) . "\n";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($allParams);
        $recordings = $stmt->fetchAll();
        echo "✓ Query principal executada com sucesso!\n";
        echo "Registros encontrados: " . count($recordings) . "\n\n";
        
        if (count($recordings) > 0) {
            echo "=== Primeiro Registro (exemplo) ===\n";
            $first = $recordings[0];
            foreach ($first as $key => $value) {
                echo "  {$key}: " . ($value ?? 'NULL') . "\n";
            }
        }
        
    } catch (\PDOException $e) {
        echo "✗ Erro na query principal: " . $e->getMessage() . "\n";
        echo "Código do erro: " . $e->getCode() . "\n";
        echo "SQL State: " . $e->getCode() . "\n\n";
        throw $e;
    }
    
    echo "\n✓ Todos os testes passaram!\n";
    
} catch (\Exception $e) {
    echo "\n✗ Erro fatal: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}












