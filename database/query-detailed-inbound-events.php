<?php

/**
 * Script para buscar detalhes completos dos últimos 5 eventos WhatsApp inbound
 * Inclui extrações JSON do payload e metadata
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

echo "=== Query: Detalhes dos últimos 5 eventos WhatsApp Inbound ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          event_id,
          event_type,
          source_system,
          tenant_id,
          status,
          retry_count,
          max_retries,
          next_retry_at,
          created_at,
          updated_at,
          processed_at,
          LEFT(error_message, 180) AS error_preview,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.event'))       AS raw_event,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.channel'))     AS payload_channel,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.session.id'))  AS payload_session_id,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from'))        AS payload_from,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS payload_message_from,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.id'))  AS payload_message_id,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS payload_message_text,
          JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text'))        AS payload_text,
          JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS metadata_channel_id
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        ORDER BY created_at DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum evento encontrado.\n\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " eventos\n\n";
    echo str_repeat("=", 200) . "\n\n";
    
    foreach ($results as $index => $row) {
        echo "EVENTO " . ($index + 1) . ":\n";
        echo str_repeat("-", 200) . "\n";
        
        echo "event_id:              " . ($row['event_id'] ?? 'NULL') . "\n";
        echo "event_type:            " . ($row['event_type'] ?? 'NULL') . "\n";
        echo "source_system:         " . ($row['source_system'] ?? 'NULL') . "\n";
        echo "tenant_id:             " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "status:                " . ($row['status'] ?? 'NULL') . "\n";
        echo "retry_count:           " . ($row['retry_count'] ?? 'NULL') . "\n";
        echo "max_retries:           " . ($row['max_retries'] ?? 'NULL') . "\n";
        echo "next_retry_at:         " . ($row['next_retry_at'] ?? 'NULL') . "\n";
        echo "created_at:            " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "updated_at:            " . ($row['updated_at'] ?? 'NULL') . "\n";
        echo "processed_at:          " . ($row['processed_at'] ?? 'NULL') . "\n";
        echo "error_preview:         " . ($row['error_preview'] ?? 'NULL') . "\n";
        echo "\n";
        echo "--- Extrações do Payload ---\n";
        echo "raw_event:             " . ($row['raw_event'] ?? 'NULL') . "\n";
        echo "payload_channel:       " . ($row['payload_channel'] ?? 'NULL') . "\n";
        echo "payload_session_id:   " . ($row['payload_session_id'] ?? 'NULL') . "\n";
        echo "payload_from:          " . ($row['payload_from'] ?? 'NULL') . "\n";
        echo "payload_message_from:  " . ($row['payload_message_from'] ?? 'NULL') . "\n";
        echo "payload_message_id:    " . ($row['payload_message_id'] ?? 'NULL') . "\n";
        echo "payload_message_text:  " . ($row['payload_message_text'] ?? 'NULL') . "\n";
        echo "payload_text:          " . ($row['payload_text'] ?? 'NULL') . "\n";
        echo "\n";
        echo "--- Extrações do Metadata ---\n";
        echo "metadata_channel_id:     " . ($row['metadata_channel_id'] ?? 'NULL') . "\n";
        
        // Análise de processamento
        echo "\n";
        echo "--- Análise de Processamento ---\n";
        if ($row['retry_count'] == 0 && $row['next_retry_at'] === null) {
            echo "⚠️  ATENÇÃO: retry_count=0 e next_retry_at=NULL - evento nunca foi tentado processar\n";
        }
        if ($row['created_at'] === $row['updated_at'] || 
            (strtotime($row['updated_at']) - strtotime($row['created_at'])) < 1) {
            echo "⚠️  ATENÇÃO: updated_at igual ou muito próximo de created_at - evento não foi atualizado\n";
        }
        if ($row['status'] === 'queued' && $row['processed_at'] === null) {
            echo "⚠️  ATENÇÃO: status=queued e processed_at=NULL - evento nunca foi processado\n";
        }
        
        echo "\n" . str_repeat("=", 200) . "\n\n";
    }
    
    // Resumo geral
    echo "RESUMO:\n";
    echo str_repeat("-", 200) . "\n";
    $queuedCount = 0;
    $neverRetried = 0;
    $neverUpdated = 0;
    
    foreach ($results as $row) {
        if ($row['status'] === 'queued') $queuedCount++;
        if ($row['retry_count'] == 0 && $row['next_retry_at'] === null) $neverRetried++;
        if ($row['created_at'] === $row['updated_at'] || 
            (strtotime($row['updated_at']) - strtotime($row['created_at'])) < 1) {
            $neverUpdated++;
        }
    }
    
    echo "Eventos com status 'queued': {$queuedCount}/" . count($results) . "\n";
    echo "Eventos nunca tentados (retry_count=0, next_retry_at=NULL): {$neverRetried}/" . count($results) . "\n";
    echo "Eventos nunca atualizados (updated_at = created_at): {$neverUpdated}/" . count($results) . "\n";
    
    if ($neverRetried > 0 || $neverUpdated > 0) {
        echo "\n⚠️  CONCLUSÃO: Nada está consumindo a fila! Eventos ficam 'queued' para sempre.\n";
    }
    
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

