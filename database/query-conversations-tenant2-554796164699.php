<?php

/**
 * Script para buscar conversas do tenant_id = 2 com contato 554796164699
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

echo "=== Query: Conversas do Tenant 2 com contato 554796164699 ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          id,
          tenant_id,
          channel_id,
          contact_external_id,
          conversation_key,
          last_message_at,
          last_message_direction,
          unread_count,
          message_count,
          updated_at
        FROM conversations
        WHERE tenant_id = 2
          AND (contact_external_id LIKE '%554796164699%' OR conversation_key LIKE '%554796164699%')
        ORDER BY updated_at DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhuma conversa encontrada\n\n";
        exit(0);
    }
    
    echo "✓ Encontradas " . count($results) . " conversa(s)\n\n";
    
    // Exibe os resultados em formato tabular
    echo str_repeat("=", 120) . "\n";
    printf("%-5s %-10s %-15s %-25s %-30s %-20s %-20s %-12s %-12s %-20s\n",
        "ID", "Tenant", "Channel", "Contact External", "Conversation Key", 
        "Last Message At", "Direction", "Unread", "Messages", "Updated At");
    echo str_repeat("-", 120) . "\n";
    
    foreach ($results as $row) {
        printf("%-5s %-10s %-15s %-25s %-30s %-20s %-20s %-12s %-12s %-20s\n",
            $row['id'] ?? 'NULL',
            $row['tenant_id'] ?? 'NULL',
            $row['channel_id'] ?? 'NULL',
            substr($row['contact_external_id'] ?? 'NULL', 0, 24),
            substr($row['conversation_key'] ?? 'NULL', 0, 29),
            $row['last_message_at'] ?? 'NULL',
            $row['last_message_direction'] ?? 'NULL',
            $row['unread_count'] ?? 'NULL',
            $row['message_count'] ?? 'NULL',
            $row['updated_at'] ?? 'NULL'
        );
    }
    
    echo str_repeat("=", 120) . "\n\n";
    
    // Exibe detalhes completos de cada conversa
    foreach ($results as $index => $row) {
        echo "CONVERSA " . ($index + 1) . ":\n";
        echo str_repeat("-", 100) . "\n";
        echo "id:                      " . ($row['id'] ?? 'NULL') . "\n";
        echo "tenant_id:               " . ($row['tenant_id'] ?? 'NULL') . "\n";
        echo "channel_id:              " . ($row['channel_id'] ?? 'NULL') . "\n";
        echo "contact_external_id:     " . ($row['contact_external_id'] ?? 'NULL') . "\n";
        echo "conversation_key:        " . ($row['conversation_key'] ?? 'NULL') . "\n";
        echo "last_message_at:         " . ($row['last_message_at'] ?? 'NULL') . "\n";
        echo "last_message_direction:  " . ($row['last_message_direction'] ?? 'NULL') . "\n";
        echo "unread_count:            " . ($row['unread_count'] ?? 'NULL') . "\n";
        echo "message_count:           " . ($row['message_count'] ?? 'NULL') . "\n";
        echo "updated_at:              " . ($row['updated_at'] ?? 'NULL') . "\n";
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

