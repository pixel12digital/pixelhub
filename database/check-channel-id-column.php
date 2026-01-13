<?php

/**
 * Verifica se a coluna channel_id existe na tabela conversations
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

echo "=== VERIFICAÇÃO COLUNA channel_id ===\n\n";

$db = DB::getConnection();

// Verifica se a coluna channel_id existe
$stmt = $db->query("SHOW COLUMNS FROM conversations LIKE 'channel_id'");
$exists = $stmt->rowCount() > 0;

if ($exists) {
    echo "✅ Coluna 'channel_id' EXISTE na tabela conversations\n";
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   Tipo: {$col['Type']}\n";
    echo "   Null: {$col['Null']}\n";
} else {
    echo "❌ Coluna 'channel_id' NÃO EXISTE na tabela conversations\n";
    echo "\n";
    echo "⚠️  PROBLEMA IDENTIFICADO:\n";
    echo "   O código do ConversationService está tentando inserir/atualizar\n";
    echo "   a coluna 'channel_id', mas ela não existe na tabela!\n";
    echo "\n";
    echo "💡 SOLUÇÃO:\n";
    echo "   Adicionar a coluna 'channel_id' à tabela conversations.\n";
    echo "   SQL:\n";
    echo "   ALTER TABLE conversations ADD COLUMN channel_id VARCHAR(255) NULL AFTER channel_account_id;\n";
}

echo "\n";

