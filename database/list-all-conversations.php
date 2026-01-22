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

echo "═══════════════════════════════════════════════════════════════\n";
echo "LISTAGEM DE TODAS AS CONVERSAS (formatado para UI)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        channel_type,
        contact_external_id,
        contact_name,
        tenant_id,
        status,
        message_count,
        unread_count,
        last_message_at,
        created_at
    FROM conversations
    WHERE channel_type = 'whatsapp'
    ORDER BY last_message_at DESC
    LIMIT 20
");

$conversations = $stmt->fetchAll();

echo "✓ " . count($conversations) . " conversa(s) encontrada(s):\n\n";

foreach ($conversations as $conv) {
    $threadId = "whatsapp_{$conv['id']}";
    
    echo "Thread ID para UI: {$threadId}\n";
    echo "  Key: {$conv['conversation_key']}\n";
    echo "  Contact: {$conv['contact_external_id']} ({$conv['contact_name']})\n";
    echo "  Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    echo "  Status: {$conv['status']}\n";
    echo "  Messages: {$conv['message_count']}\n";
    echo "  Unread: {$conv['unread_count']}\n";
    echo "  Last Message: {$conv['last_message_at']}\n";
    echo "  Created: {$conv['created_at']}\n";
    echo "\n";
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "RESUMO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

if (count($conversations) > 0) {
    echo "✓ Conversas existem e estão atualizadas\n";
    echo "✓ Thread IDs formatados corretamente: whatsapp_{conversation_id}\n";
    echo "✓ UI deve encontrar essas conversas ao chamar getWhatsAppThreads()\n\n";
    echo "📌 Se não aparecer na UI, verifique:\n";
    echo "  1. Filtros (tenant_id, status, channel)\n";
    echo "  2. Cache do navegador\n";
    echo "  3. Logs de erro do CommunicationHubController\n";
} else {
    echo "❌ Nenhuma conversa encontrada\n";
    echo "   Verifique se ConversationService está sendo chamado\n";
}

