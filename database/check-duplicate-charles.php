<?php

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

$db = DB::getConnection();

echo "=== VERIFICAÇÃO: DUPLICATAS CHARLES ===\n\n";

$stmt = $db->query("
    SELECT 
        id, 
        conversation_key, 
        contact_external_id, 
        last_message_at, 
        updated_at, 
        unread_count, 
        message_count, 
        channel_id, 
        tenant_id,
        status
    FROM conversations 
    WHERE contact_external_id = '554796164699' 
    ORDER BY last_message_at DESC
");

$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Encontradas " . count($conversations) . " conversa(s) do Charles:\n\n";

foreach ($conversations as $i => $c) {
    echo ($i + 1) . ". ID: {$c['id']}\n";
    echo "   Key: {$c['conversation_key']}\n";
    echo "   Last Message: {$c['last_message_at']}\n";
    echo "   Updated: {$c['updated_at']}\n";
    echo "   Unread: {$c['unread_count']}\n";
    echo "   Messages: {$c['message_count']}\n";
    echo "   Channel ID: " . ($c['channel_id'] ?: 'NULL') . "\n";
    echo "   Tenant ID: " . ($c['tenant_id'] ?: 'NULL') . "\n";
    echo "   Status: {$c['status']}\n";
    echo "\n";
}

if (count($conversations) > 1) {
    echo "⚠️  PROBLEMA: Há " . count($conversations) . " conversas do mesmo número!\n";
    echo "   Isso pode causar confusão na UI.\n";
    echo "   A conversa mais recente (ID: {$conversations[0]['id']}) deveria ser a única.\n\n";
    
    echo "💡 SOLUÇÃO:\n";
    echo "   1. Verificar por que findEquivalentConversation() não está encontrando a conversa existente\n";
    echo "   2. Mesclar as conversas ou deletar a antiga\n";
    echo "   3. Verificar se a UI está mostrando a conversa correta\n";
}

