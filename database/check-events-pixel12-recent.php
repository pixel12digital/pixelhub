<?php

/**
 * Script para buscar eventos recentes do canal Pixel12 Digital
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

echo "=== Eventos recentes do Pixel12 Digital (após 13:30) ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          status,
          created_at,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id')) AS session_id,
          JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS meta_channel_id,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_root,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS msg_text,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS raw_event
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id')) = 'Pixel12 Digital'
          )
          AND created_at >= '2026-01-15 13:30:00'
        ORDER BY created_at DESC
        LIMIT 30
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum evento encontrado\n\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " evento(s)\n\n";
    
    // Exibe cada evento
    foreach ($results as $index => $row) {
        echo "EVENTO " . ($index + 1) . ":\n";
        echo str_repeat("-", 100) . "\n";
        echo "event_id:        " . ($row['event_id'] ?? 'NULL') . "\n";
        echo "status:          " . ($row['status'] ?? 'NULL') . "\n";
        echo "created_at:      " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "session_id:      " . ($row['session_id'] ?? 'NULL') . "\n";
        echo "meta_channel_id: " . ($row['meta_channel_id'] ?? 'NULL') . "\n";
        echo "msg_from:        " . ($row['msg_from'] ?? 'NULL') . "\n";
        echo "from_root:       " . ($row['from_root'] ?? 'NULL') . "\n";
        echo "msg_text:        " . ($row['msg_text'] ?? 'NULL') . "\n";
        echo "raw_event:         " . ($row['raw_event'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
    echo str_repeat("=", 100) . "\n\n";
    
    // Estatísticas
    $processed = 0;
    $queued = 0;
    $failed = 0;
    
    foreach ($results as $row) {
        $status = $row['status'] ?? 'unknown';
        if ($status === 'processed') $processed++;
        elseif ($status === 'queued') $queued++;
        elseif ($status === 'failed') $failed++;
    }
    
    echo "Estatísticas:\n";
    echo str_repeat("-", 80) . "\n";
    echo "Total:     " . count($results) . "\n";
    echo "Processed: {$processed}\n";
    echo "Queued:    {$queued}\n";
    echo "Failed:    {$failed}\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}












