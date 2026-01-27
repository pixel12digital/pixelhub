<?php

/**
 * Script para verificar se conversa foi criada/atualizada para o número 5599999999999
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

echo "=== Verificação: Conversa para 5599999999999 ===\n\n";

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
        WHERE
          contact_external_id LIKE '%5599999999999%'
          OR conversation_key LIKE '%5599999999999%'
        ORDER BY updated_at DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($results)) {
        echo "✗ Nenhuma conversa encontrada para o número 5599999999999\n\n";
        echo "Isso indica que:\n";
        echo "  - O evento foi ingerido mas resolveConversation() não criou/atualizou a conversa\n";
        echo "  - OU o número não foi normalizado corretamente\n";
        echo "  - OU a conversa foi criada com um formato diferente de número\n";
        exit(0);
    }
    
    echo "✓ Encontradas " . count($results) . " conversa(s)\n\n";
    echo str_repeat("=", 150) . "\n";
    
    // Cabeçalho
    printf("%-5s | %-10s | %-15s | %-10s | %-25s | %-50s | %-19s | %-10s | %-5s | %-5s | %-19s | %-19s\n",
        "ID", "Tenant ID", "Channel ID", "Type", "Contact External ID", "Conversation Key", "Last Message At", "Direction", "Unread", "Msgs", "Created At", "Updated At");
    echo str_repeat("-", 150) . "\n";
    
    // Linhas
    foreach ($results as $row) {
        printf("%-5s | %-10s | %-15s | %-10s | %-25s | %-50s | %-19s | %-10s | %-5s | %-5s | %-19s | %-19s\n",
            $row['id'] ?? 'NULL',
            $row['tenant_id'] ?? 'NULL',
            $row['channel_id'] ?? 'NULL',
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
    
    // Análise
    echo "Análise:\n";
    echo str_repeat("-", 80) . "\n";
    
    $mostRecent = $results[0];
    echo "Conversa mais recente (ID: {$mostRecent['id']}):\n";
    echo "  - Contact External ID: {$mostRecent['contact_external_id']}\n";
    echo "  - Conversation Key: {$mostRecent['conversation_key']}\n";
    echo "  - Last Message At: {$mostRecent['last_message_at']}\n";
    echo "  - Last Message Direction: {$mostRecent['last_message_direction']}\n";
    echo "  - Unread Count: {$mostRecent['unread_count']}\n";
    echo "  - Message Count: {$mostRecent['message_count']}\n";
    echo "  - Updated At: {$mostRecent['updated_at']}\n";
    
    // Verifica se foi atualizada recentemente (últimas 24 horas)
    $updatedAt = strtotime($mostRecent['updated_at']);
    $now = time();
    $hoursAgo = ($now - $updatedAt) / 3600;
    
    if ($hoursAgo < 24) {
        echo "\n✓ Conversa foi atualizada há " . round($hoursAgo, 2) . " horas\n";
    } else {
        echo "\n⚠ Conversa foi atualizada há " . round($hoursAgo / 24, 2) . " dias\n";
    }
    
} catch (\PDOException $e) {
    echo "\n✗ Erro ao executar query: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
    exit(1);
} catch (\Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}












