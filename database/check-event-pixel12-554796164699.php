<?php

/**
 * Script para buscar evento mais recente do canal Pixel12 Digital do número 554796164699
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

echo "=== Busca: Evento mais recente do Pixel12 Digital - 554796164699 ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          tenant_id,
          status,
          processed_at,
          created_at,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS raw_event,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id')) AS session_id,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_root,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS msg_text,
          JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS meta_channel_id,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.timestamp')) AS msg_ts
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
          AND source_system = 'wpp_gateway'
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = 'Pixel12 Digital'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id')) = 'Pixel12 Digital'
          )
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%554796164699%'
          )
        ORDER BY created_at DESC
        LIMIT 5
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
        echo "tenant_id:       " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "status:          " . ($row['status'] ?? 'NULL') . "\n";
        echo "processed_at:    " . ($row['processed_at'] ?? 'NULL') . "\n";
        echo "created_at:      " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "raw_event:       " . ($row['raw_event'] ?? 'NULL') . "\n";
        echo "session_id:      " . ($row['session_id'] ?? 'NULL') . "\n";
        echo "msg_from:        " . ($row['msg_from'] ?? 'NULL') . "\n";
        echo "from_root:       " . ($row['from_root'] ?? 'NULL') . "\n";
        echo "msg_text:        " . ($row['msg_text'] ?? 'NULL') . "\n";
        echo "meta_channel_id: " . ($row['meta_channel_id'] ?? 'NULL') . "\n";
        echo "msg_ts:          " . ($row['msg_ts'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
    echo str_repeat("=", 100) . "\n\n";
    
    // Análise detalhada do evento mais recente
    $mostRecent = $results[0];
    echo "Evento mais recente:\n";
    echo str_repeat("-", 80) . "\n";
    echo "Event ID:        " . ($mostRecent['event_id'] ?? 'NULL') . "\n";
    echo "Tenant ID:       " . ($mostRecent['tenant_id'] ?? 'NULL') . "\n";
    echo "Status:          " . ($mostRecent['status'] ?? 'NULL') . "\n";
    echo "Processed At:    " . ($mostRecent['processed_at'] ?? 'NULL') . "\n";
    echo "Created At:      " . ($mostRecent['created_at'] ?? 'NULL') . "\n";
    echo "Raw Event:       " . ($mostRecent['raw_event'] ?? 'NULL') . "\n";
    echo "Session ID:      " . ($mostRecent['session_id'] ?? 'NULL') . "\n";
    echo "Msg From:        " . ($mostRecent['msg_from'] ?? 'NULL') . "\n";
    echo "From Root:       " . ($mostRecent['from_root'] ?? 'NULL') . "\n";
    echo "Msg Text:        " . ($mostRecent['msg_text'] ?? 'NULL') . "\n";
    echo "Meta Channel ID: " . ($mostRecent['meta_channel_id'] ?? 'NULL') . "\n";
    echo "Msg Timestamp:   " . ($mostRecent['msg_ts'] ?? 'NULL') . "\n";
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

