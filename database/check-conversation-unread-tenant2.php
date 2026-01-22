<?php

/**
 * Script para verificar conversa e unread_count do número 5599999999999 para tenant_id = 2
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

echo "=== Verificação: Conversa e unread_count ===\n\n";

try {
    $db = DB::getConnection();
    
    $query = "
        SELECT
          id,
          tenant_id,
          channel_id,
          channel_type,
          contact_external_id,
          conversation_key,
          last_message_at,
          last_message_direction,
          unread_count,
          message_count,
          created_at,
          updated_at
        FROM conversations
        WHERE tenant_id = 2
          AND (contact_external_id LIKE '%5599999999999%' OR conversation_key LIKE '%5599999999999%')
        ORDER BY updated_at DESC
        LIMIT 5
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhuma conversa encontrada para tenant_id=2 e número 5599999999999\n\n";
        exit(0);
    }
    
    echo "✓ Encontradas " . count($results) . " conversa(s)\n\n";
    echo str_repeat("=", 150) . "\n";
    
    // Cabeçalho
    printf("%-5s | %-10s | %-20s | %-10s | %-25s | %-50s | %-19s | %-10s | %-5s | %-5s | %-19s | %-19s\n",
        "ID", "Tenant ID", "Channel ID", "Type", "Contact External ID", "Conversation Key", "Last Message At", "Direction", "Unread", "Msgs", "Created At", "Updated At");
    echo str_repeat("-", 150) . "\n";
    
    // Linhas
    foreach ($results as $row) {
        printf("%-5s | %-10s | %-20s | %-10s | %-25s | %-50s | %-19s | %-10s | %-5s | %-5s | %-19s | %-19s\n",
            $row['id'] ?? 'NULL',
            $row['tenant_id'] ?? 'NULL',
            substr($row['channel_id'] ?? 'NULL', 0, 20),
            $row['channel_type'] ?? 'NULL',
            substr($row['contact_external_id'] ?? 'NULL', 0, 25),
            substr($row['conversation_key'] ?? 'NULL', 0, 50),
            $row['last_message_at'] ?? 'NULL',
            $row['last_message_direction'] ?? 'NULL',
            $row['unread_count'] ?? 'NULL',
            $row['message_count'] ?? 'NULL',
            $row['created_at'] ?? 'NULL',
            $row['updated_at'] ?? 'NULL'
        );
    }
    
    echo str_repeat("=", 150) . "\n\n";
    
    // Análise detalhada da conversa mais recente
    $mostRecent = $results[0];
    echo "Análise da conversa mais recente (ID: {$mostRecent['id']}):\n";
    echo str_repeat("-", 80) . "\n";
    echo "Contact External ID: {$mostRecent['contact_external_id']}\n";
    echo "Conversation Key: {$mostRecent['conversation_key']}\n";
    echo "Last Message At: {$mostRecent['last_message_at']}\n";
    echo "Last Message Direction: {$mostRecent['last_message_direction']}\n";
    echo "Unread Count: {$mostRecent['unread_count']}\n";
    echo "Message Count: {$mostRecent['message_count']}\n";
    echo "Updated At: {$mostRecent['updated_at']}\n";
    
    // Verifica se unread_count foi incrementado
    if ($mostRecent['unread_count'] > 0) {
        echo "\n✅ unread_count > 0 - Mensagens não lidas detectadas\n";
    } else {
        echo "\n⚠️  unread_count = 0 - Pode indicar que mensagem não incrementou contador\n";
    }
    
    // Verifica se last_message_at foi atualizado recentemente
    if ($mostRecent['last_message_at']) {
        $lastMessageTime = strtotime($mostRecent['last_message_at']);
        $now = time();
        $hoursAgo = ($now - $lastMessageTime) / 3600;
        
        if ($hoursAgo < 1) {
            echo "✅ last_message_at atualizado há " . round($hoursAgo * 60, 2) . " minutos\n";
        } else {
            echo "ℹ️  last_message_at atualizado há " . round($hoursAgo, 2) . " horas\n";
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









