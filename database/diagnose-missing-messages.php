<?php

/**
 * Script para diagnosticar mensagens que não aparecem
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== DIAGNÓSTICO: MENSAGENS QUE NÃO APARECEM ===\n\n";

// 1. Busca eventos recentes (últimas 2 horas)
echo "1. EVENTOS RECENTES (últimas 2 horas):\n";
$stmt = $db->query("
    SELECT 
        event_id,
        id,
        event_type,
        tenant_id,
        created_at,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.from') as from_raw,
        JSON_EXTRACT(payload, '$.to') as to_raw,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.message.to') as message_to,
        JSON_EXTRACT(payload, '$.message.text') as message_text,
        JSON_EXTRACT(payload, '$.text') as text
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$events = $stmt->fetchAll();

echo "Total de eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    $from = trim($event['from_raw'] ?: $event['message_from'] ?: '', '"');
    $to = trim($event['to_raw'] ?: $event['message_to'] ?: '', '"');
    $text = trim($event['message_text'] ?: $event['text'] ?: '', '"');
    $channelId = trim($event['channel_id'] ?: '', '"');
    
    echo "--- Evento ID: {$event['event_id']} (PK: {$event['id']}) ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "channel_id: " . ($channelId ?: 'NULL') . "\n";
    echo "from: " . ($from ?: 'NULL') . "\n";
    echo "to: " . ($to ?: 'NULL') . "\n";
    echo "text: " . ($text ?: 'NULL') . "\n";
    echo "created_at: {$event['created_at']}\n";
    
    // Verifica se existe conversa para este contato
    $normalizedFrom = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $from));
    if ($normalizedFrom) {
        $convStmt = $db->prepare("
            SELECT id, conversation_key, contact_external_id, remote_key, message_count
            FROM conversations
            WHERE contact_external_id LIKE ?
               OR remote_key LIKE ?
            LIMIT 5
        ");
        $pattern1 = "%{$normalizedFrom}%";
        $pattern2 = "%tel:{$normalizedFrom}%";
        $convStmt->execute([$pattern1, $pattern2]);
        $conversations = $convStmt->fetchAll();
        
        if (count($conversations) > 0) {
            echo "  -> Conversas encontradas: " . count($conversations) . "\n";
            foreach ($conversations as $conv) {
                echo "     - ID: {$conv['id']}, key: {$conv['conversation_key']}, contact: {$conv['contact_external_id']}, messages: {$conv['message_count']}\n";
            }
        } else {
            echo "  -> NENHUMA CONVERSA ENCONTRADA para este número!\n";
        }
    }
    echo "\n";
}

// 2. Verifica conversas recentes
echo "\n2. CONVERSAS RECENTES:\n";
$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        remote_key,
        tenant_id,
        channel_id,
        message_count,
        last_message_at,
        created_at
    FROM conversations
    WHERE last_message_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
       OR created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY last_message_at DESC, created_at DESC
    LIMIT 10
");
$conversations = $stmt->fetchAll();

echo "Total de conversas encontradas: " . count($conversations) . "\n\n";

foreach ($conversations as $conv) {
    echo "--- Conversa ID: {$conv['id']} ---\n";
    echo "conversation_key: {$conv['conversation_key']}\n";
    echo "contact_external_id: {$conv['contact_external_id']}\n";
    echo "remote_key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
    echo "tenant_id: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "channel_id: " . ($conv['channel_id'] ?: 'NULL') . "\n";
    echo "message_count: {$conv['message_count']}\n";
    echo "last_message_at: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
    
    // Verifica quantos eventos existem para este contato
    $contactId = $conv['contact_external_id'];
    $normalizedContactId = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $contactId));
    
    if ($normalizedContactId) {
        $eventCountStmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM communication_events
            WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND (
                JSON_EXTRACT(payload, '$.from') LIKE ?
                OR JSON_EXTRACT(payload, '$.to') LIKE ?
                OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
                OR JSON_EXTRACT(payload, '$.message.to') LIKE ?
            )
        ");
        $pattern = "%{$normalizedContactId}%";
        $eventCountStmt->execute([$pattern, $pattern, $pattern, $pattern]);
        $eventCount = $eventCountStmt->fetchColumn();
        
        echo "  -> Eventos encontrados para este contato: {$eventCount}\n";
        echo "  -> message_count na conversa: {$conv['message_count']}\n";
        
        if ($eventCount > $conv['message_count']) {
            echo "  -> AVISO: Há mais eventos ({$eventCount}) do que message_count ({$conv['message_count']})!\n";
        }
    }
    echo "\n";
}

// 3. Verifica eventos sem conversa associada
echo "\n3. EVENTOS SEM CONVERSA ASSOCIADA:\n";
$stmt = $db->query("
    SELECT 
        ce.event_id,
        ce.id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND NOT EXISTS (
        SELECT 1 FROM conversations c
        WHERE (
            c.contact_external_id LIKE CONCAT('%', REGEXP_REPLACE(JSON_EXTRACT(ce.payload, '$.message.from'), '[^0-9]', ''), '%')
            OR c.remote_key LIKE CONCAT('%tel:', REGEXP_REPLACE(JSON_EXTRACT(ce.payload, '$.message.from'), '[^0-9]', ''), '%')
        )
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$orphanEvents = $stmt->fetchAll();

echo "Total de eventos órfãos (sem conversa): " . count($orphanEvents) . "\n\n";

foreach ($orphanEvents as $event) {
    $from = trim($event['message_from'] ?: '', '"');
    $to = trim($event['message_to'] ?: '', '"');
    $text = trim($event['message_text'] ?: '', '"');
    
    echo "--- Evento Órfão ID: {$event['event_id']} ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "from: " . ($from ?: 'NULL') . "\n";
    echo "to: " . ($to ?: 'NULL') . "\n";
    echo "text: " . ($text ?: 'NULL') . "\n";
    echo "created_at: {$event['created_at']}\n";
    echo "\n";
}

