<?php

/**
 * Script para verificar se a tabela conversations foi criada corretamente
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

echo "=== Verificação da Tabela conversations ===\n\n";

try {
    $db = DB::getConnection();
    
    // Verifica se tabela existe
    $stmt = $db->query("SHOW TABLES LIKE 'conversations'");
    if ($stmt->rowCount() === 0) {
        echo "✗ ERRO: Tabela 'conversations' não existe!\n";
        exit(1);
    }
    
    echo "✓ Tabela 'conversations' existe\n\n";
    
    // Lista colunas
    echo "Colunas da tabela:\n";
    $stmt = $db->query("DESCRIBE conversations");
    $cols = $stmt->fetchAll();
    
    foreach ($cols as $col) {
        $null = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $key = $col['Key'] ? " [{$col['Key']}]" : '';
        echo "  - {$col['Field']} ({$col['Type']}) {$null}{$key}\n";
    }
    
    echo "\n";
    
    // Verifica índices
    echo "Índices:\n";
    $stmt = $db->query("SHOW INDEXES FROM conversations");
    $indexes = $stmt->fetchAll();
    $indexGroups = [];
    foreach ($indexes as $idx) {
        $keyName = $idx['Key_name'];
        if (!isset($indexGroups[$keyName])) {
            $indexGroups[$keyName] = [
                'unique' => $idx['Non_unique'] == 0,
                'columns' => []
            ];
        }
        $indexGroups[$keyName]['columns'][] = $idx['Column_name'];
    }
    
    foreach ($indexGroups as $name => $info) {
        $type = $info['unique'] ? 'UNIQUE' : 'INDEX';
        $cols = implode(', ', $info['columns']);
        echo "  - {$name} ({$type}): {$cols}\n";
    }
    
    echo "\n";
    
    // Verifica foreign keys
    echo "Foreign Keys:\n";
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'conversations'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll();
    
    if (empty($fks)) {
        echo "  (nenhuma foreign key encontrada)\n";
    } else {
        foreach ($fks as $fk) {
            echo "  - {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    }
    
    echo "\n";
    
    // Conta registros
    $stmt = $db->query("SELECT COUNT(*) as total FROM conversations");
    $total = $stmt->fetch()['total'];
    echo "✓ Total de conversas: {$total}\n";
    
    if ($total > 0) {
        echo "\nÚltimas 5 conversas:\n";
        $stmt = $db->query("
            SELECT 
                id, 
                conversation_key, 
                channel_type, 
                contact_external_id, 
                status, 
                message_count,
                created_at
            FROM conversations 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $conversations = $stmt->fetchAll();
        
        foreach ($conversations as $conv) {
            echo "  - ID: {$conv['id']} | Key: {$conv['conversation_key']} | Status: {$conv['status']} | Mensagens: {$conv['message_count']}\n";
        }
    }
    
    echo "\n✓ Verificação concluída com sucesso!\n";
    
} catch (\Exception $e) {
    echo "✗ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

