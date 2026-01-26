<?php

/**
 * Script para verificar status de um evento específico
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

$eventId = 'f66fa9c0-dcef-46a8-9319-72897a000f86';

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          event_type,
          tenant_id,
          status,
          processed_at,
          error_message,
          created_at,
          updated_at
        FROM communication_events
        WHERE event_id = ?
        LIMIT 1
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$eventId]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (empty($result)) {
        echo "✗ Evento não encontrado\n";
        exit(0);
    }
    
    // Exibe resultado formatado
    echo "event_id:        " . ($result['event_id'] ?? 'NULL') . "\n";
    echo "event_type:      " . ($result['event_type'] ?? 'NULL') . "\n";
    echo "tenant_id:       " . ($result['tenant_id'] ?? 'NULL') . "\n";
    echo "status:          " . ($result['status'] ?? 'NULL') . "\n";
    echo "processed_at:    " . ($result['processed_at'] ?? 'NULL') . "\n";
    echo "error_message:   " . ($result['error_message'] ?? 'NULL') . "\n";
    echo "created_at:      " . ($result['created_at'] ?? 'NULL') . "\n";
    echo "updated_at:      " . ($result['updated_at'] ?? 'NULL') . "\n";
    
} catch (\PDOException $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}










