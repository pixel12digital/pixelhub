<?php

/**
 * Verificação imediata das tabelas no banco
 * Executa as queries SQL diretamente
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

echo "=== VERIFICAÇÃO DE TABELAS NO BANCO ===\n\n";

$db = DB::getConnection();

// 1. Verifica tabela conversations
echo "1. Verificando tabela 'conversations':\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'conversations'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo "   ✅ Tabela 'conversations' EXISTE\n";
        
        // Mostra estrutura
        echo "   📋 Estrutura da tabela:\n";
        $stmt = $db->query("DESCRIBE conversations");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "      - {$col['Field']} ({$col['Type']})" . 
                 ($col['Null'] === 'YES' ? ' NULL' : ' NOT NULL') .
                 ($col['Key'] ? " [{$col['Key']}]" : '') . "\n";
        }
        
        // Conta registros
        $stmt = $db->query("SELECT COUNT(*) as total FROM conversations");
        $count = $stmt->fetch()['total'];
        echo "   📊 Total de registros: {$count}\n";
    } else {
        echo "   ❌ Tabela 'conversations' NÃO EXISTE\n";
        echo "   ⚠️  Isso explica por que as conversas não estão sendo criadas!\n";
    }
} catch (\Exception $e) {
    echo "   ❌ ERRO ao verificar: {$e->getMessage()}\n";
}

echo "\n";

// 2. Verifica tabela messages
echo "2. Verificando tabela 'messages':\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'messages'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo "   ✅ Tabela 'messages' EXISTE\n";
        
        // Mostra estrutura
        echo "   📋 Estrutura da tabela:\n";
        $stmt = $db->query("DESCRIBE messages");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "      - {$col['Field']} ({$col['Type']})" . 
                 ($col['Null'] === 'YES' ? ' NULL' : ' NOT NULL') .
                 ($col['Key'] ? " [{$col['Key']}]" : '') . "\n";
        }
        
        // Conta registros
        $stmt = $db->query("SELECT COUNT(*) as total FROM messages");
        $count = $stmt->fetch()['total'];
        echo "   📊 Total de registros: {$count}\n";
    } else {
        echo "   ❌ Tabela 'messages' NÃO EXISTE\n";
    }
} catch (\Exception $e) {
    echo "   ❌ ERRO ao verificar: {$e->getMessage()}\n";
}

echo "\n";

// 3. Verifica tabela communication_events
echo "3. Verificando tabela 'communication_events':\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'communication_events'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo "   ✅ Tabela 'communication_events' EXISTE\n";
        
        // Mostra estrutura
        echo "   📋 Estrutura da tabela:\n";
        $stmt = $db->query("DESCRIBE communication_events");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "      - {$col['Field']} ({$col['Type']})" . 
                 ($col['Null'] === 'YES' ? ' NULL' : ' NOT NULL') .
                 ($col['Key'] ? " [{$col['Key']}]" : '') . "\n";
        }
        
        // Conta registros
        $stmt = $db->query("SELECT COUNT(*) as total FROM communication_events");
        $count = $stmt->fetch()['total'];
        echo "   📊 Total de registros: {$count}\n";
        
        // Mostra últimos 3 eventos
        echo "   📋 Últimos 3 eventos:\n";
        $stmt = $db->query("
            SELECT event_id, event_type, tenant_id, status, created_at
            FROM communication_events
            ORDER BY created_at DESC
            LIMIT 3
        ");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($events as $event) {
            echo "      - {$event['event_type']} (ID: {$event['event_id']}, Tenant: " . 
                 ($event['tenant_id'] ?: 'NULL') . ", Status: {$event['status']}, " .
                 "Created: {$event['created_at']})\n";
        }
    } else {
        echo "   ❌ Tabela 'communication_events' NÃO EXISTE\n";
    }
} catch (\Exception $e) {
    echo "   ❌ ERRO ao verificar: {$e->getMessage()}\n";
}

echo "\n";

// Resumo
echo str_repeat("=", 60) . "\n";
echo "RESUMO\n";
echo str_repeat("=", 60) . "\n";

$tables = ['conversations', 'messages', 'communication_events'];
$missing = [];

foreach ($tables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() === 0) {
            $missing[] = $table;
        }
    } catch (\Exception $e) {
        $missing[] = $table;
    }
}

if (empty($missing)) {
    echo "✅ Todas as tabelas necessárias existem!\n";
} else {
    echo "❌ Tabelas faltando: " . implode(', ', $missing) . "\n";
    echo "\n";
    echo "⚠️  AÇÃO NECESSÁRIA:\n";
    echo "   Execute as migrations para criar as tabelas faltantes.\n";
    echo "   Procure por arquivos de migration com nomes como:\n";
    foreach ($missing as $table) {
        echo "   - *create_{$table}_table.php\n";
    }
}

echo "\n";

