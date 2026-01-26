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

echo "=== Verificação: Status do Evento ===\n\n";

$eventId = 'ed80f300-8a7e-4dcd-99a4-b28d89186ecd';

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          event_type,
          tenant_id,
          status,
          processed_at,
          retry_count,
          next_retry_at,
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
        echo "✗ Evento não encontrado com event_id: {$eventId}\n\n";
        exit(0);
    }
    
    echo "✓ Evento encontrado!\n\n";
    echo str_repeat("=", 100) . "\n";
    
    // Exibe os resultados
    echo "event_id:        " . ($result['event_id'] ?? 'NULL') . "\n";
    echo "event_type:      " . ($result['event_type'] ?? 'NULL') . "\n";
    echo "tenant_id:       " . ($result['tenant_id'] ?? 'NULL') . "\n";
    echo "status:          " . ($result['status'] ?? 'NULL') . "\n";
    echo "processed_at:    " . ($result['processed_at'] ?? 'NULL') . "\n";
    echo "retry_count:     " . ($result['retry_count'] ?? 'NULL') . "\n";
    echo "next_retry_at:   " . ($result['next_retry_at'] ?? 'NULL') . "\n";
    echo "error_message:   " . ($result['error_message'] ?? 'NULL') . "\n";
    echo "created_at:      " . ($result['created_at'] ?? 'NULL') . "\n";
    echo "updated_at:      " . ($result['updated_at'] ?? 'NULL') . "\n";
    
    echo str_repeat("=", 100) . "\n\n";
    
    // Análise
    echo "Análise:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($result['status'] === 'processed') {
        echo "✅ Status: PROCESSED (correto!)\n";
    } elseif ($result['status'] === 'failed') {
        echo "⚠️  Status: FAILED\n";
        echo "   Motivo: " . ($result['error_message'] ?? 'N/A') . "\n";
    } elseif ($result['status'] === 'queued') {
        echo "❌ Status: QUEUED (ainda não processado)\n";
    } else {
        echo "ℹ️  Status: " . ($result['status'] ?? 'NULL') . "\n";
    }
    
    if ($result['processed_at']) {
        echo "✅ processed_at: PREENCHIDO (" . $result['processed_at'] . ")\n";
    } else {
        echo "❌ processed_at: NULL (não foi processado ainda)\n";
    }
    
    if ($result['created_at'] && $result['updated_at']) {
        $created = strtotime($result['created_at']);
        $updated = strtotime($result['updated_at']);
        $diff = $updated - $created;
        
        if ($diff > 1) {
            echo "✅ updated_at diferente de created_at (diferença: {$diff}s) - evento foi atualizado\n";
        } else {
            echo "⚠️  updated_at igual ou muito próximo de created_at - evento pode não ter sido atualizado\n";
        }
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}










