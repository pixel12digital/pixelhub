<?php

/**
 * Script para buscar estatísticas de status dos eventos WhatsApp inbound
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

echo "=== Query: Estatísticas por Status ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          status,
          COUNT(*) AS total,
          MIN(created_at) AS oldest,
          MAX(created_at) AS newest
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        GROUP BY status
        ORDER BY total DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "Nenhum resultado encontrado.\n";
        exit(0);
    }
    
    // Exibe os resultados exatamente como saem do banco
    echo "Resultado:\n";
    echo str_repeat("=", 100) . "\n";
    
    // Cabeçalho
    printf("%-15s | %-10s | %-19s | %-19s\n", "status", "total", "oldest", "newest");
    echo str_repeat("-", 100) . "\n";
    
    // Linhas
    foreach ($results as $row) {
        printf("%-15s | %-10s | %-19s | %-19s\n", 
            $row['status'] ?? 'NULL',
            $row['total'] ?? 'NULL',
            $row['oldest'] ?? 'NULL',
            $row['newest'] ?? 'NULL'
        );
    }
    
    echo str_repeat("=", 100) . "\n";
    echo "\nTotal de grupos (status diferentes): " . count($results) . "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

