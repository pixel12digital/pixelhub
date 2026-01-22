<?php

/**
 * Verifica mensagens encaminhadas e como estão sendo processadas
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

echo "=== VERIFICAÇÃO: MENSAGENS ENCAMINHADAS ===\n\n";

$db = DB::getConnection();

// Busca eventos que podem ser mensagens encaminhadas
echo "1. Buscando eventos recentes do ServPro (554796474223) e Pixel12 Digital:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as msg_from,
        JSON_EXTRACT(payload, '$.to') as to_field,
        JSON_EXTRACT(payload, '$.message.to') as msg_to,
        JSON_EXTRACT(payload, '$.message.forwarded') as forwarded,
        JSON_EXTRACT(payload, '$.forwarded') as is_forwarded,
        JSON_EXTRACT(payload, '$.message.text') as text,
        JSON_EXTRACT(payload, '$.message.caption') as caption
    FROM communication_events
    WHERE event_type LIKE '%whatsapp%'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%554796474223%'
        OR JSON_EXTRACT(payload, '$.from') LIKE '%Pixel12%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%Pixel12%'
        OR JSON_EXTRACT(payload, '$.to') LIKE '%Pixel12%'
        OR JSON_EXTRACT(payload, '$.message.to') LIKE '%Pixel12%'
    )
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ Nenhum evento encontrado!\n";
} else {
    foreach ($events as $i => $event) {
        $from = trim($event['from_field'] ?: $event['msg_from'] ?: 'N/A', '"');
        $to = trim($event['to_field'] ?: $event['msg_to'] ?: 'N/A', '"');
        $text = substr(trim($event['text'] ?: $event['caption'] ?: 'sem texto', '"'), 0, 50);
        $forwarded = $event['forwarded'] ?: $event['is_forwarded'];
        $isForwarded = ($forwarded && (trim($forwarded, '"') === 'true' || $forwarded === true));
        
        echo "   " . ($i + 1) . ". {$event['created_at']} | From: {$from} | To: {$to}\n";
        echo "      Text: {$text}\n";
        if ($isForwarded) {
            echo "      ⚠️  ENCAMINHADA!\n";
        }
        echo "\n";
    }
}

echo "\n";

// Verifica se há conversas criadas para mensagens encaminhadas
echo "2. Verificando conversas relacionadas:\n";
$stmt = $db->query("
    SELECT 
        id,
        contact_external_id,
        contact_name,
        last_message_at,
        message_count
    FROM conversations
    WHERE contact_external_id IN ('554796474223', '554796164699')
    ORDER BY last_message_at DESC
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "   - {$conv['contact_name']} ({$conv['contact_external_id']}): {$conv['message_count']} mensagens, última: {$conv['last_message_at']}\n";
}

echo "\n";

// Verifica se há eventos que não geraram conversas
echo "3. Verificando eventos que podem não ter gerado conversas:\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.created_at,
        ce.event_type,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as msg_from,
        (
            SELECT COUNT(*) 
            FROM conversations c 
            WHERE c.contact_external_id = COALESCE(
                TRIM(BOTH '\"' FROM JSON_EXTRACT(ce.payload, '$.from')),
                TRIM(BOTH '\"' FROM JSON_EXTRACT(ce.payload, '$.message.from'))
            )
        ) as has_conversation
    FROM communication_events ce
    WHERE ce.event_type LIKE '%whatsapp.inbound%'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    HAVING has_conversation = 0
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$orphanEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orphanEvents)) {
    echo "   ✅ Todos os eventos têm conversas associadas\n";
} else {
    echo "   ⚠️  Encontrados " . count($orphanEvents) . " eventos sem conversas:\n";
    foreach ($orphanEvents as $event) {
        $from = trim($event['from_field'] ?: $event['msg_from'] ?: 'N/A', '"');
        echo "   - {$event['created_at']} | From: {$from} | Type: {$event['event_type']}\n";
    }
}

echo "\n";

