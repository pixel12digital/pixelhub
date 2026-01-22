<?php

/**
 * Script para executar query no banco remoto do Pixel Hub
 * Busca informações de um evento específico na tabela communication_events
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

echo "=== Query: communication_events ===\n\n";

$eventId = 'bc1b6e36-1587-45d9-ad6b-b74908e7d85c';

echo "Buscando evento: {$eventId}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          event_type,
          source_system,
          tenant_id,
          status,
          created_at
        FROM communication_events
        WHERE event_id = :event_id
        LIMIT 5
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['event_id' => $eventId]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum evento encontrado com event_id: {$eventId}\n\n";
        echo "Verificando se o evento existe com variações...\n";
        
        // Tenta buscar com LIKE para ver se há eventos similares
        $likeQuery = "
            SELECT event_id, event_type, source_system, created_at
            FROM communication_events
            WHERE event_id LIKE :pattern
            LIMIT 10
        ";
        $likeStmt = $db->prepare($likeQuery);
        $likeStmt->execute(['pattern' => '%' . substr($eventId, 0, 8) . '%']);
        $similar = $likeStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($similar)) {
            echo "\nEventos similares encontrados:\n";
            foreach ($similar as $row) {
                echo "  - {$row['event_id']} ({$row['event_type']}) - {$row['created_at']}\n";
            }
        }
        exit(0);
    }
    
    echo "✓ Evento encontrado!\n\n";
    
    foreach ($results as $index => $row) {
        echo "Resultado " . ($index + 1) . ":\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Event ID:      {$row['event_id']}\n";
        echo "Event Type:   " . ($row['event_type'] ?? 'NULL') . "\n";
        echo "Source System: " . ($row['source_system'] ?? 'NULL') . "\n";
        echo "Tenant ID:    " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "Status:       " . ($row['status'] ?? 'NULL') . "\n";
        echo "Created At:   " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    }
    
    echo "Total de registros encontrados: " . count($results) . "\n\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

