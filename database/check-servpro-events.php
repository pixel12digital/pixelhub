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

echo "=== VERIFICAÇÃO: EVENTOS SERVPRO ===\n\n";

$db = DB::getConnection();

// Busca últimos eventos do ServPro
echo "1. Últimos 15 eventos do ServPro (554796474223):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as msg_from,
        JSON_EXTRACT(payload, '$.message.text') as text,
        JSON_EXTRACT(payload, '$.message.timestamp') as msg_timestamp,
        JSON_EXTRACT(payload, '$.timestamp') as timestamp,
        JSON_EXTRACT(payload, '$.message.forwarded') as forwarded,
        JSON_EXTRACT(payload, '$.forwarded') as is_forwarded
    FROM communication_events
    WHERE event_type LIKE '%whatsapp%'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%554796474223%'
    )
    ORDER BY created_at DESC
    LIMIT 15
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ Nenhum evento encontrado!\n";
} else {
    foreach ($events as $i => $event) {
        $from = trim($event['from_field'] ?: $event['msg_from'] ?: 'N/A', '"');
        $text = substr(trim($event['text'] ?: 'sem texto', '"'), 0, 40);
        $msgTs = $event['msg_timestamp'] ?: $event['timestamp'];
        $forwarded = $event['forwarded'] ?: $event['is_forwarded'];
        $isForwarded = ($forwarded && (trim($forwarded, '"') === 'true' || $forwarded === true));
        
        echo "   " . ($i + 1) . ". {$event['created_at']} | From: {$from} | Type: {$event['event_type']}\n";
        echo "      Text: {$text}\n";
        echo "      Msg Timestamp: {$msgTs}\n";
        if ($isForwarded) {
            echo "      ⚠️  ENCAMINHADA!\n";
        }
        echo "\n";
    }
}

echo "\n";

// Verifica conversa do ServPro
echo "2. Conversa do ServPro no banco:\n";
$stmt = $db->query("
    SELECT 
        id,
        contact_external_id,
        contact_name,
        last_message_at,
        updated_at,
        message_count,
        unread_count,
        last_message_direction
    FROM conversations
    WHERE contact_external_id = '554796474223'
");
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "   - ID: {$conversation['id']}\n";
    echo "   - Contact: {$conversation['contact_external_id']}\n";
    echo "   - Name: {$conversation['contact_name']}\n";
    echo "   - Last Message At: {$conversation['last_message_at']}\n";
    echo "   - Updated At: {$conversation['updated_at']}\n";
    echo "   - Message Count: {$conversation['message_count']}\n";
    echo "   - Unread Count: {$conversation['unread_count']}\n";
    echo "   - Last Direction: {$conversation['last_message_direction']}\n";
    
    // Compara com último evento
    if (!empty($events)) {
        $lastEvent = $events[0];
        $lastEventTime = $lastEvent['created_at'];
        $convLastTime = $conversation['last_message_at'];
        
        echo "\n   Comparação:\n";
        echo "   - Último evento criado: {$lastEventTime}\n";
        echo "   - Last message at na conversa: {$convLastTime}\n";
        
        if ($lastEventTime !== $convLastTime) {
            echo "   ⚠️  TIMESTAMPS DIFERENTES!\n";
        }
    }
} else {
    echo "   ❌ Conversa não encontrada!\n";
}

echo "\n";

// Verifica se há mensagens encaminhadas que não foram processadas
echo "3. Verificando mensagens encaminhadas não processadas:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        created_at,
        JSON_EXTRACT(payload, '$.message.forwarded') as forwarded,
        JSON_EXTRACT(payload, '$.forwarded') as is_forwarded,
        JSON_EXTRACT(payload, '$.from') as from_field
    FROM communication_events
    WHERE event_type LIKE '%whatsapp%'
    AND (
        JSON_EXTRACT(payload, '$.message.forwarded') = 'true'
        OR JSON_EXTRACT(payload, '$.forwarded') = 'true'
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$forwarded = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($forwarded)) {
    echo "   ✅ Nenhuma mensagem encaminhada encontrada nos eventos\n";
} else {
    echo "   ⚠️  Encontradas " . count($forwarded) . " mensagens encaminhadas:\n";
    foreach ($forwarded as $f) {
        $from = trim($f['from_field'] ?: 'N/A', '"');
        echo "   - {$f['created_at']} | From: {$from}\n";
    }
}

echo "\n";

