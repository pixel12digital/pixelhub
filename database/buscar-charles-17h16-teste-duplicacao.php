<?php

/**
 * Localiza mensagem enviada a Charles Dietrich (47 99616-4699) às 17:16:
 * "Teste de duplicação de mensagem"
 * 
 * Uso: php database/buscar-charles-17h16-teste-duplicacao.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== Busca: Charles Dietrich 4796164699 - 17:16 - 'Teste de duplicação de mensagem' ===\n\n";

// Padrões de número (Brasil: 55 + DDD + número)
$patterns = ['%4796164699%', '%554796164699%', '%996164699%', '%47961646999%'];

// 1. Busca por texto no payload (outbound)
echo "1. Eventos outbound com texto 'Teste de duplicação':\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.conversation_id,
        ce.tenant_id,
        ce.status,
        ce.created_at,
        ce.idempotency_key,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.outbound.message'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) LIKE '%Teste de duplicação%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) LIKE '%Teste de duplicação%'
        OR ce.payload LIKE '%Teste de duplicação%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute();
$byText = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($byText)) {
    echo "   ❌ Nenhum evento encontrado por texto\n";
} else {
    echo "   ✅ Encontrados " . count($byText) . " evento(s):\n";
    foreach ($byText as $e) {
        echo "   - event_id: {$e['event_id']}\n";
        echo "     source_system: {$e['source_system']}\n";
        echo "     conversation_id: " . ($e['conversation_id'] ?: 'NULL') . "\n";
        echo "     to: " . ($e['to_field'] ?: $e['message_to'] ?: 'NULL') . "\n";
        echo "     text: " . substr($e['text'] ?: $e['message_text'] ?: 'NULL', 0, 80) . "\n";
        echo "     created_at: {$e['created_at']}\n";
        echo "     idempotency_key: " . ($e['idempotency_key'] ?: 'NULL') . "\n\n";
    }
}

// 2. Busca por número 4796164699 em payload.to (outbound) - 02/02 ~17:16
echo "\n2. Eventos outbound para 4796164699 em 02/02:\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.conversation_id,
        ce.tenant_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.outbound.message'
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%4796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE '%4796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE '%554796164699%'
        OR ce.payload LIKE '%4796164699%'
        OR ce.payload LIKE '%554796164699%'
    )
    AND ce.created_at >= '2026-02-02 16:00:00'
    AND ce.created_at <= '2026-02-02 18:00:00'
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute();
$byPhone = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($byPhone)) {
    echo "   ❌ Nenhum evento encontrado por número no horário\n";
} else {
    echo "   ✅ Encontrados " . count($byPhone) . " evento(s):\n";
    foreach ($byPhone as $e) {
        echo "   - event_id: {$e['event_id']}\n";
        echo "     source_system: {$e['source_system']}\n";
        echo "     conversation_id: " . ($e['conversation_id'] ?: 'NULL') . "\n";
        echo "     to: " . ($e['to_field'] ?: 'NULL') . "\n";
        echo "     text: " . substr($e['text'] ?: $e['message_text'] ?: 'NULL', 0, 80) . "\n";
        echo "     created_at: {$e['created_at']}\n\n";
    }
}

// 3. Conversas do Charles Dietrich
echo "\n3. Conversas com Charles Dietrich / 4796164699:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        channel_id,
        last_message_at,
        created_at
    FROM conversations
    WHERE contact_name LIKE '%Charles%'
       OR contact_name LIKE '%Dietrich%'
       OR contact_external_id LIKE '%4796164699%'
       OR contact_external_id LIKE '%554796164699%'
    ORDER BY last_message_at DESC
    LIMIT 10
");
$stmt->execute();
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($convs)) {
    echo "   ❌ Nenhuma conversa encontrada\n";
} else {
    echo "   ✅ Encontradas " . count($convs) . " conversa(s):\n";
    foreach ($convs as $c) {
        echo "   - id: {$c['id']} | key: {$c['conversation_key']}\n";
        echo "     contact_external_id: " . ($c['contact_external_id'] ?: 'NULL') . "\n";
        echo "     contact_name: " . ($c['contact_name'] ?: 'NULL') . "\n";
        echo "     channel_id: " . ($c['channel_id'] ?: 'NULL') . "\n";
        echo "     last_message_at: " . ($c['last_message_at'] ?: 'NULL') . "\n\n";
        
        // Eventos dessa conversa
        $evStmt = $db->prepare("
            SELECT event_id, event_type, source_system, conversation_id, created_at,
                   JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text,
                   JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) as msg_text
            FROM communication_events
            WHERE conversation_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $evStmt->execute([$c['id']]);
        $evs = $evStmt->fetchAll(PDO::FETCH_ASSOC);
        echo "     Eventos (conversation_id={$c['id']}): " . count($evs) . "\n";
        foreach ($evs as $ev) {
            echo "       - {$ev['event_id']} | {$ev['event_type']} | " . substr($ev['text'] ?: $ev['msg_text'] ?: '', 0, 50) . " | {$ev['created_at']}\n";
        }
        echo "\n";
    }
}

// 4. Eventos outbound sem conversation_id (possível causa: nova conversa)
echo "\n4. Eventos outbound recentes SEM conversation_id (possível 'nova conversa'):\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.source_system,
        ce.tenant_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.outbound.message'
    AND ce.conversation_id IS NULL
    AND ce.created_at >= '2026-02-02 16:00:00'
    ORDER BY ce.created_at DESC
    LIMIT 15
");
$stmt->execute();
$orphans = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orphans)) {
    echo "   Nenhum outbound órfão (sem conversation_id) no período\n";
} else {
    echo "   ✅ Encontrados " . count($orphans) . " evento(s) sem conversation_id:\n";
    foreach ($orphans as $o) {
        echo "   - {$o['event_id']} | to: " . ($o['to_field'] ?: 'NULL') . " | " . substr($o['text'] ?: '', 0, 60) . " | {$o['created_at']}\n";
    }
}

echo "\n=== Fim da busca ===\n";
