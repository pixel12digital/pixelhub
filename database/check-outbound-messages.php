<?php

/**
 * Verifica se mensagens outbound estão sendo processadas
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

echo "=== VERIFICAÇÃO: MENSAGENS OUTBOUND ===\n\n";

$db = DB::getConnection();

// Busca eventos outbound recentes
echo "1. Eventos outbound recentes (últimas 2 horas):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.message.to') as msg_to,
        JSON_EXTRACT(payload, '$.message.text') as text,
        JSON_EXTRACT(payload, '$.event') as raw_event
    FROM communication_events
    WHERE (
        event_type LIKE '%outbound%'
        OR JSON_EXTRACT(payload, '$.event') LIKE '%sent%'
        OR JSON_EXTRACT(payload, '$.event') LIKE '%status%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ Nenhum evento outbound encontrado!\n";
    echo "   ⚠️  Isso pode indicar que mensagens enviadas não estão gerando eventos.\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " eventos outbound:\n\n";
    foreach ($events as $i => $event) {
        $to = trim($event['to_field'] ?: $event['msg_to'] ?: 'N/A', '"');
        $text = substr(trim($event['text'] ?: 'sem texto', '"'), 0, 40);
        $rawEvent = trim($event['raw_event'] ?: 'N/A', '"');
        
        echo "   " . ($i + 1) . ". {$event['created_at']} | Type: {$event['event_type']} | Raw: {$rawEvent}\n";
        echo "      To: {$to} | Text: {$text}\n\n";
    }
}

echo "\n";

// Verifica eventos recentes do ServPro
echo "2. Eventos recentes relacionados ao ServPro (554796474223) - últimas 2 horas:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.message.from') as msg_from,
        JSON_EXTRACT(payload, '$.message.to') as msg_to,
        JSON_EXTRACT(payload, '$.event') as raw_event,
        JSON_EXTRACT(payload, '$.message.text') as text
    FROM communication_events
    WHERE (
        JSON_EXTRACT(payload, '$.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%554796474223%'
    )
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$servproEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($servproEvents)) {
    echo "   ❌ Nenhum evento encontrado para ServPro nas últimas 2 horas!\n";
} else {
    foreach ($servproEvents as $i => $event) {
        $from = trim($event['from_field'] ?: $event['msg_from'] ?: 'N/A', '"');
        $to = trim($event['to_field'] ?: $event['msg_to'] ?: 'N/A', '"');
        $text = substr(trim($event['text'] ?: 'sem texto', '"'), 0, 40);
        $rawEvent = trim($event['raw_event'] ?: 'N/A', '"');
        
        echo "   " . ($i + 1) . ". {$event['created_at']} | Type: {$event['event_type']} | Raw: {$rawEvent}\n";
        echo "      From: {$from} | To: {$to} | Text: {$text}\n\n";
    }
}

echo "\n";

// Verifica conversa do ServPro
echo "3. Estado atual da conversa do ServPro:\n";
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
    echo "   - Last Message At: {$conversation['last_message_at']}\n";
    echo "   - Updated At: {$conversation['updated_at']}\n";
    echo "   - Message Count: {$conversation['message_count']}\n";
    echo "   - Last Direction: {$conversation['last_message_direction']}\n";
    
    if ($conversation['updated_at'] > $conversation['last_message_at']) {
        echo "   ⚠️  PROBLEMA: updated_at ({$conversation['updated_at']}) é mais recente que last_message_at ({$conversation['last_message_at']})!\n";
        echo "      Isso indica que a conversa foi atualizada mas last_message_at não foi atualizado.\n";
    }
} else {
    echo "   ❌ Conversa não encontrada!\n";
}

echo "\n";













