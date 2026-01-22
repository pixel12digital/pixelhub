<?php

/**
 * Script para buscar os últimos 20 eventos WhatsApp inbound
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

echo "=== Query: Últimos 20 eventos WhatsApp Inbound ===\n\n";

try {
    $db = DB::getConnection();
    
    // Primeiro, verifica se channel_id existe
    $checkColumns = $db->query("SHOW COLUMNS FROM communication_events LIKE 'channel_id'");
    $hasChannelId = $checkColumns->rowCount() > 0;
    
    if ($hasChannelId) {
        $query = "
            SELECT
              event_id,
              tenant_id,
              channel_id,
              status,
              created_at
            FROM communication_events
            WHERE event_type = 'whatsapp.inbound.message'
            ORDER BY created_at DESC
            LIMIT 20
        ";
    } else {
        // Se channel_id não existe, remove da query
        $query = "
            SELECT
              event_id,
              tenant_id,
              status,
              created_at
            FROM communication_events
            WHERE event_type = 'whatsapp.inbound.message'
            ORDER BY created_at DESC
            LIMIT 20
        ";
    }
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhum evento encontrado.\n\n";
        exit(0);
    }
    
    echo "✓ Encontrados " . count($results) . " eventos\n\n";
    echo str_repeat("=", 120) . "\n";
    
    if ($hasChannelId) {
        printf("%-36s | %-10s | %-15s | %-15s | %-19s\n", 
            "Event ID", "Tenant ID", "Channel ID", "Status", "Created At");
    } else {
        printf("%-36s | %-10s | %-15s | %-19s\n", 
            "Event ID", "Tenant ID", "Status", "Created At");
    }
    echo str_repeat("-", 120) . "\n";
    
    foreach ($results as $row) {
        // Encurta event_id para exibição (primeiros 8 e últimos 4 caracteres)
        $eventId = $row['event_id'];
        if (strlen($eventId) > 20) {
            $eventId = substr($eventId, 0, 8) . '...' . substr($eventId, -4);
        }
        
        $tenantId = $row['tenant_id'] ?? 'NULL';
        $status = $row['status'] ?? 'NULL';
        $createdAt = $row['created_at'] ?? 'NULL';
        
        if ($hasChannelId) {
            $channelId = $row['channel_id'] ?? 'NULL';
            printf("%-36s | %-10s | %-15s | %-15s | %-19s\n", 
                $eventId, $tenantId, $channelId, $status, $createdAt);
        } else {
            printf("%-36s | %-10s | %-15s | %-19s\n", 
                $eventId, $tenantId, $status, $createdAt);
        }
    }
    
    echo str_repeat("=", 120) . "\n\n";
    
    // Mostra também os event_ids completos em formato de lista
    echo "Event IDs completos:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($results as $index => $row) {
        echo ($index + 1) . ". " . $row['event_id'] . "\n";
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

