<?php

/**
 * Verifica conversas do ServPro no banco
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

echo "=== VERIFICAÇÃO CONVERSAS SERVPRO ===\n\n";

$db = DB::getConnection();

// Números do ServPro (com e sem 9º dígito)
$servproNumbers = ['554796474223', '5547996474223'];

echo "1. Buscando conversas do ServPro:\n";
foreach ($servproNumbers as $number) {
    $stmt = $db->prepare("
        SELECT * FROM conversations 
        WHERE contact_external_id = ?
        ORDER BY last_message_at DESC
    ");
    $stmt->execute([$number]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "   ❌ Nenhuma conversa encontrada para {$number}\n";
    } else {
        echo "   ✅ Encontradas " . count($conversations) . " conversa(s) para {$number}:\n";
        foreach ($conversations as $conv) {
            echo "      - ID: {$conv['id']}\n";
            echo "        Key: {$conv['conversation_key']}\n";
            echo "        Channel Type: {$conv['channel_type']}\n";
            echo "        Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
            echo "        Last Message: {$conv['last_message_at']}\n";
            echo "        Message Count: {$conv['message_count']}\n";
            echo "\n";
        }
    }
}

echo "\n";

// Verifica todas as conversas WhatsApp recentes
echo "2. Últimas 10 conversas WhatsApp (todas):\n";
$stmt = $db->query("
    SELECT id, conversation_key, contact_external_id, tenant_id, 
           last_message_at, message_count
    FROM conversations
    WHERE channel_type = 'whatsapp'
    ORDER BY last_message_at DESC
    LIMIT 10
");
$allConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($allConversations)) {
    echo "   ⚠️  Nenhuma conversa WhatsApp encontrada\n";
} else {
    foreach ($allConversations as $conv) {
        echo "   - Contact: {$conv['contact_external_id']}, ";
        echo "Tenant: " . ($conv['tenant_id'] ?: 'NULL') . ", ";
        echo "Last: {$conv['last_message_at']}\n";
    }
}

echo "\n";

// Verifica eventos recentes do ServPro
echo "3. Últimos 5 eventos do ServPro em communication_events:\n";
$stmt = $db->prepare("
    SELECT event_id, event_type, tenant_id, status, created_at,
           JSON_EXTRACT(payload, '$.from') as from_field
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute(['%554796474223%', '%554796474223%']);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ⚠️  Nenhum evento encontrado para ServPro\n";
} else {
    foreach ($events as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     From: {$event['from_field']}\n";
        echo "     Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Status: {$event['status']}\n";
        echo "     Created: {$event['created_at']}\n";
        echo "\n";
    }
}

echo "\n";

