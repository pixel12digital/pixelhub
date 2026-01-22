<?php

/**
 * Script para buscar eventos de comunicação do WhatsApp para o número 554796164699
 * a partir de 2026-01-15 14:15:00
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

echo "=== Query: Eventos de Comunicação WhatsApp - 554796164699 (a partir de 2026-01-15 14:15:00) ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          status,
          tenant_id,
          created_at,
          processed_at,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS msg_from,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_root,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS msg_text,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event')) AS raw_event,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) AS raw_type,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.timestamp')) AS msg_ts,
          JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS meta_channel_id,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id')) AS session_id
        FROM communication_events
        WHERE source_system = 'wpp_gateway'
          AND event_type = 'whatsapp.inbound.message'
          AND created_at >= '2026-01-15 14:15:00'
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE '%@lid%'
          )
        ORDER BY created_at DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✓ 0 linhas encontradas\n\n";
        echo "Nenhum evento encontrado que atenda aos critérios:\n";
        echo "  - source_system = 'wpp_gateway'\n";
        echo "  - event_type = 'whatsapp.inbound.message'\n";
        echo "  - created_at >= '2026-01-15 14:15:00'\n";
        echo "  - msg_from contém '554796164699' ou '@lid'\n\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " evento(s)\n\n";
    
    // Exibe os resultados em formato tabular
    echo str_repeat("=", 150) . "\n";
    printf("%-10s %-10s %-8s %-20s %-20s %-25s %-25s %-30s %-15s %-15s %-15s %-20s %-20s\n",
        "Event ID", "Status", "Tenant", "Created At", "Processed At", 
        "Msg From", "From Root", "Msg Text (trunc)", "Raw Event", "Raw Type", 
        "Msg TS", "Meta Channel", "Session ID");
    echo str_repeat("-", 150) . "\n";
    
    foreach ($results as $row) {
        $msgText = $row['msg_text'] ?? 'NULL';
        if (strlen($msgText) > 25) {
            $msgText = substr($msgText, 0, 22) . '...';
        }
        
        printf("%-10s %-10s %-8s %-20s %-20s %-25s %-25s %-30s %-15s %-15s %-15s %-20s %-20s\n",
            $row['event_id'] ?? 'NULL',
            $row['status'] ?? 'NULL',
            $row['tenant_id'] ?? 'NULL',
            $row['created_at'] ?? 'NULL',
            $row['processed_at'] ?? 'NULL',
            substr($row['msg_from'] ?? 'NULL', 0, 24),
            substr($row['from_root'] ?? 'NULL', 0, 24),
            $msgText,
            substr($row['raw_event'] ?? 'NULL', 0, 14),
            substr($row['raw_type'] ?? 'NULL', 0, 14),
            $row['msg_ts'] ?? 'NULL',
            substr($row['meta_channel_id'] ?? 'NULL', 0, 19),
            substr($row['session_id'] ?? 'NULL', 0, 19)
        );
    }
    
    echo str_repeat("=", 150) . "\n\n";
    
    // Exibe detalhes completos de cada evento
    foreach ($results as $index => $row) {
        echo "EVENTO " . ($index + 1) . ":\n";
        echo str_repeat("-", 100) . "\n";
        echo "event_id:        " . ($row['event_id'] ?? 'NULL') . "\n";
        echo "status:          " . ($row['status'] ?? 'NULL') . "\n";
        echo "tenant_id:       " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "created_at:      " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "processed_at:    " . ($row['processed_at'] ?? 'NULL') . "\n";
        echo "msg_from:        " . ($row['msg_from'] ?? 'NULL') . "\n";
        echo "from_root:       " . ($row['from_root'] ?? 'NULL') . "\n";
        echo "msg_text:        " . ($row['msg_text'] ?? 'NULL') . "\n";
        echo "raw_event:       " . ($row['raw_event'] ?? 'NULL') . "\n";
        echo "raw_type:        " . ($row['raw_type'] ?? 'NULL') . "\n";
        echo "msg_ts:          " . ($row['msg_ts'] ?? 'NULL') . "\n";
        echo "meta_channel_id: " . ($row['meta_channel_id'] ?? 'NULL') . "\n";
        echo "session_id:      " . ($row['session_id'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

