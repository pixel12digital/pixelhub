<?php

/**
 * Executa a migration para adicionar channel_id à tabela conversations
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

echo "=== EXECUTANDO MIGRATION: Adicionar channel_id ===\n\n";

$db = DB::getConnection();

// Carrega a migration
require_once __DIR__ . '/migrations/20260113_alter_conversations_add_channel_id.php';

$migration = new AlterConversationsAddChannelId();

try {
    echo "Executando migration...\n";
    $migration->up($db);
    echo "✅ Migration executada com sucesso!\n\n";
    
    // Verifica se a coluna foi criada
    $stmt = $db->query("SHOW COLUMNS FROM conversations LIKE 'channel_id'");
    if ($stmt->rowCount() > 0) {
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Coluna 'channel_id' criada:\n";
        echo "   Tipo: {$col['Type']}\n";
        echo "   Null: {$col['Null']}\n";
        echo "   Key: {$col['Key']}\n";
    } else {
        echo "⚠️  Coluna 'channel_id' ainda não existe (pode já ter existido antes)\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO ao executar migration: {$e->getMessage()}\n";
    echo "   Stack trace:\n";
    echo "   " . str_replace("\n", "\n   ", $e->getTraceAsString()) . "\n";
    exit(1);
}

echo "\n";

