<?php
/**
 * Diagnóstico da mensagem outbound "0923" enviada às 09:24
 * 
 * Objetivo: Verificar se a mensagem chegou ao PixelHub e como foi processada
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

echo "=== DIAGNÓSTICO MENSAGEM OUTBOUND 0923 ===\n\n";

$db = DB::getConnection();

// 1. Buscar eventos entre 09:24 e 09:25 (horário Brasil = 12:24 UTC)
echo "1. EVENTOS ENTRE 09:24 E 09:25 (horário Brasil):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id, 
        event_id,
        event_type, 
        tenant_id, 
        conversation_id, 
        status,
        created_at,
        SUBSTRING(payload, 1, 500) as payload_preview
    FROM communication_events 
    WHERE created_at >= '2026-01-29 09:24:00' 
    AND created_at <= '2026-01-29 09:26:00'
    ORDER BY created_at ASC
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Nenhum evento encontrado nesse período.\n\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']}\n";
        echo "Event ID: {$event['event_id']}\n";
        echo "Tipo: {$event['event_type']}\n";
        echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "Conversation ID: " . ($event['conversation_id'] ?? 'NULL') . "\n";
        echo "Status: {$event['status']}\n";
        echo "Criado em: {$event['created_at']}\n";
        echo "Payload (500 chars): {$event['payload_preview']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 2. Buscar eventos com eventType outbound
echo "\n2. EVENTOS OUTBOUND RECENTES (últimas 2 horas):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id, 
        event_id,
        event_type, 
        tenant_id, 
        conversation_id, 
        status,
        created_at,
        SUBSTRING(payload, 1, 300) as payload_preview
    FROM communication_events 
    WHERE event_type LIKE '%outbound%'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");

$outboundEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($outboundEvents)) {
    echo "Nenhum evento outbound encontrado nas últimas 2 horas.\n\n";
} else {
    foreach ($outboundEvents as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Conv: " . ($event['conversation_id'] ?? 'NULL') . " | {$event['created_at']}\n";
    }
}

// 3. Buscar a conversa "Pixel12Digital" que está aparecendo errada
echo "\n3. CONVERSA 'Pixel12Digital' (9730-9525):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id, 
        conversation_key,
        channel_type,
        channel_id,
        contact_external_id,
        contact_name,
        tenant_id,
        status,
        last_message_at,
        message_count
    FROM conversations 
    WHERE contact_external_id LIKE '%9730%' 
       OR contact_external_id LIKE '%97309525%'
       OR contact_name LIKE '%Pixel12%'
    ORDER BY last_message_at DESC
    LIMIT 5
");

$convPixel = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convPixel)) {
    echo "Nenhuma conversa encontrada com esse critério.\n\n";
} else {
    foreach ($convPixel as $conv) {
        echo "ID: {$conv['id']}\n";
        echo "Key: {$conv['conversation_key']}\n";
        echo "Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
        echo "Contact External ID: {$conv['contact_external_id']}\n";
        echo "Contact Name: " . ($conv['contact_name'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "Last Message: {$conv['last_message_at']}\n";
        echo "Message Count: {$conv['message_count']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 4. Buscar a conversa do Charles Dietrich (4699)
echo "\n4. CONVERSA CHARLES DIETRICH (99616-4699):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id, 
        conversation_key,
        channel_type,
        channel_id,
        contact_external_id,
        contact_name,
        tenant_id,
        status,
        last_message_at,
        message_count
    FROM conversations 
    WHERE contact_external_id LIKE '%4699%' 
       OR contact_external_id LIKE '%96164699%'
       OR contact_name LIKE '%Charles%'
    ORDER BY last_message_at DESC
    LIMIT 5
");

$convCharles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convCharles)) {
    echo "Nenhuma conversa encontrada com esse critério.\n\n";
} else {
    foreach ($convCharles as $conv) {
        echo "ID: {$conv['id']}\n";
        echo "Key: {$conv['conversation_key']}\n";
        echo "Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
        echo "Contact External ID: {$conv['contact_external_id']}\n";
        echo "Contact Name: " . ($conv['contact_name'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "Last Message: {$conv['last_message_at']}\n";
        echo "Message Count: {$conv['message_count']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 5. Verificar eventos recentes com "0923" no payload
echo "\n5. EVENTOS COM '0923' NO PAYLOAD (últimas 2 horas):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id, 
        event_id,
        event_type, 
        tenant_id, 
        conversation_id, 
        status,
        created_at
    FROM communication_events 
    WHERE payload LIKE '%0923%'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");

$events0923 = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events0923)) {
    echo "Nenhum evento com '0923' no payload encontrado.\n\n";
} else {
    foreach ($events0923 as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Conv: " . ($event['conversation_id'] ?? 'NULL') . " | Status: {$event['status']} | {$event['created_at']}\n";
    }
}

// 6. Verificar se há eventos com fromMe=true
echo "\n6. EVENTOS COM fromMe NO PAYLOAD (últimas 2 horas):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->query("
    SELECT 
        id, 
        event_id,
        event_type, 
        conversation_id, 
        status,
        created_at,
        SUBSTRING(payload, 1, 400) as payload_preview
    FROM communication_events 
    WHERE (payload LIKE '%\"fromMe\":true%' OR payload LIKE '%\"fromMe\": true%')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");

$eventsFromMe = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($eventsFromMe)) {
    echo "Nenhum evento com fromMe=true encontrado nas últimas 2 horas.\n\n";
} else {
    foreach ($eventsFromMe as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Conv: " . ($event['conversation_id'] ?? 'NULL') . " | {$event['created_at']}\n";
        echo "Payload: {$event['payload_preview']}\n";
        echo str_repeat("-", 40) . "\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
