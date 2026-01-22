<?php

/**
 * Script para debugar por que mensagens não aparecem na conversa
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

$db = DB::getConnection();

// Busca conversa ID 1 (Servpro)
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        remote_key,
        contact_key,
        thread_key,
        tenant_id,
        channel_id,
        message_count,
        is_incoming_lead
    FROM conversations
    WHERE id = 1
");
$stmt->execute();
$conversation = $stmt->fetch();

if (!$conversation) {
    echo "Conversa não encontrada!\n";
    exit(1);
}

echo "=== CONVERSA ===\n";
echo "ID: {$conversation['id']}\n";
echo "conversation_key: {$conversation['conversation_key']}\n";
echo "contact_external_id: {$conversation['contact_external_id']}\n";
echo "remote_key: " . ($conversation['remote_key'] ?: 'NULL') . "\n";
echo "contact_key: " . ($conversation['contact_key'] ?: 'NULL') . "\n";
echo "thread_key: " . ($conversation['thread_key'] ?: 'NULL') . "\n";
echo "tenant_id: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
echo "channel_id: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
echo "message_count: {$conversation['message_count']}\n";
echo "is_incoming_lead: {$conversation['is_incoming_lead']}\n";
echo "\n";

// Busca eventos relacionados
$contactId = $conversation['contact_external_id'];
$normalizedContactId = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $contactId));

echo "=== BUSCANDO EVENTOS ===\n";
echo "contact_external_id original: {$contactId}\n";
echo "contact_external_id normalizado (apenas dígitos): {$normalizedContactId}\n";
echo "\n";

// Busca eventos que podem estar relacionados
$stmt = $db->prepare("
    SELECT 
        event_id,
        event_type,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_raw,
        JSON_EXTRACT(payload, '$.to') as to_raw,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.message.to') as message_to,
        JSON_EXTRACT(payload, '$.text') as text,
        JSON_EXTRACT(payload, '$.body') as body,
        JSON_EXTRACT(payload, '$.message.text') as message_text,
        JSON_EXTRACT(payload, '$.message.body') as message_body
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.to') LIKE ?
    )
    ORDER BY created_at ASC
");
$pattern = "%{$normalizedContactId}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$events = $stmt->fetchAll();

echo "Eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo "--- Evento ID: {$event['event_id']} ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "from_raw: " . ($event['from_raw'] ?: 'NULL') . "\n";
    echo "to_raw: " . ($event['to_raw'] ?: 'NULL') . "\n";
    echo "message_from: " . ($event['message_from'] ?: 'NULL') . "\n";
    echo "message_to: " . ($event['message_to'] ?: 'NULL') . "\n";
    echo "text: " . ($event['text'] ?: 'NULL') . "\n";
    echo "body: " . ($event['body'] ?: 'NULL') . "\n";
    echo "message_text: " . ($event['message_text'] ?: 'NULL') . "\n";
    echo "message_body: " . ($event['message_body'] ?: 'NULL') . "\n";
    echo "created_at: {$event['created_at']}\n";
    echo "\n";
}

// Busca TODOS os eventos recentes para ver o formato
echo "=== TODOS OS EVENTOS RECENTES (últimos 10) ===\n";
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_raw,
        JSON_EXTRACT(payload, '$.to') as to_raw,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.message.to') as message_to,
        JSON_EXTRACT(payload, '$.text') as text,
        JSON_EXTRACT(payload, '$.message.text') as message_text,
        payload
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY created_at DESC
    LIMIT 10
");
$allEvents = $stmt->fetchAll();

echo "Total de eventos recentes: " . count($allEvents) . "\n\n";

foreach ($allEvents as $event) {
    echo "--- Evento ID: {$event['event_id']} ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "from_raw: " . ($event['from_raw'] ?: 'NULL') . "\n";
    echo "to_raw: " . ($event['to_raw'] ?: 'NULL') . "\n";
    echo "message_from: " . ($event['message_from'] ?: 'NULL') . "\n";
    echo "message_to: " . ($event['message_to'] ?: 'NULL') . "\n";
    echo "text: " . ($event['text'] ?: 'NULL') . "\n";
    echo "message_text: " . ($event['message_text'] ?: 'NULL') . "\n";
    
    // Verifica se contém o número da conversa
    $payloadStr = $event['payload'];
    if (strpos($payloadStr, '554796474223') !== false) {
        echo "*** CONTÉM O NÚMERO 554796474223 ***\n";
        $payload = json_decode($payloadStr, true);
        echo "Payload completo:\n";
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}

