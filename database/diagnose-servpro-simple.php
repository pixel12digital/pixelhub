<?php
/**
 * Diagnóstico Simples: Mensagem ServPro não sobe pro topo
 * 
 * Execute este script após enviar uma mensagem de teste do ServPro
 * ou forneça o texto/horário da mensagem quando solicitado.
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== DIAGNÓSTICO: Mensagem ServPro Inbound ===\n\n";

// Busca eventos recentes do ServPro (últimos 30 minutos)
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        ce.payload,
        ce.metadata,
        JSON_EXTRACT(ce.payload, '$.session.id') as session_id,
        JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    AND (
        ce.payload LIKE '%554796474223%'
        OR ce.payload LIKE '%4796474223%'
        OR ce.payload LIKE '%TESTE SERVPRO%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 5
");

$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento do ServPro encontrado nos últimos 30 minutos.\n";
    echo "   Envie uma mensagem de teste e execute novamente.\n";
    exit(1);
}

$testEvent = $events[0];
$payload = json_decode($testEvent['payload'], true);

// Extrai channel_id
$channelId = $testEvent['metadata_channel_id'] ?? $testEvent['session_id'] ?? null;
if ($channelId) {
    $channelId = trim($channelId, '"');
}

$isInbound = ($testEvent['event_type'] === 'whatsapp.inbound.message');

echo "📋 EVENTO ENCONTRADO:\n";
echo "   event_id: {$testEvent['event_id']}\n";
echo "   event_type: {$testEvent['event_type']} " . ($isInbound ? "✅" : "❌ DEVERIA SER INBOUND") . "\n";
echo "   channel_id: " . ($channelId ?: 'NULL') . "\n";
echo "   tenant_id: " . ($testEvent['tenant_id'] ?: 'NULL') . "\n";
echo "   created_at: {$testEvent['created_at']}\n\n";

// Busca conversa do ServPro
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.contact_external_id,
        c.tenant_id,
        c.last_message_at,
        c.last_message_direction,
        c.message_count,
        c.unread_count,
        c.updated_at
    FROM conversations c
    WHERE c.contact_external_id = '554796474223'
    OR c.contact_external_id LIKE '554796474223%'
    ORDER BY c.last_message_at DESC
    LIMIT 1
");

$stmt->execute();
$servproConv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($servproConv) {
    echo "📋 CONVERSA DO SERVPRO:\n";
    echo "   conversation_id: {$servproConv['id']}\n";
    echo "   conversation_key: {$servproConv['conversation_key']}\n";
    echo "   last_message_at: {$servproConv['last_message_at']}\n";
    echo "   last_message_direction: " . ($servproConv['last_message_direction'] ?: 'NULL') . "\n";
    echo "   unread_count: {$servproConv['unread_count']}\n";
    echo "   message_count: {$servproConv['message_count']}\n";
    echo "   updated_at: {$servproConv['updated_at']}\n\n";
    
    // Verifica se foi atualizada recentemente
    $eventTime = strtotime($testEvent['created_at']);
    $convTime = strtotime($servproConv['updated_at']);
    $diffSeconds = abs($eventTime - $convTime);
    
    if ($diffSeconds <= 60) {
        echo "   ✅ Conversa atualizada recentemente (diferença: {$diffSeconds}s)\n";
    } else {
        echo "   ⚠️  Conversa atualizada há " . round($diffSeconds/60, 1) . " minutos\n";
    }
    
    // Verifica se unread_count incrementou
    if ($isInbound && $servproConv['unread_count'] > 0) {
        echo "   ✅ unread_count > 0 (correto para inbound)\n";
    } elseif ($isInbound && $servproConv['unread_count'] == 0) {
        echo "   ❌ unread_count = 0 (deveria ser > 0 para inbound)\n";
    }
    
    // Verifica se last_message_direction está correto
    if ($isInbound && $servproConv['last_message_direction'] === 'inbound') {
        echo "   ✅ last_message_direction = inbound (correto)\n";
    } elseif ($isInbound && $servproConv['last_message_direction'] !== 'inbound') {
        echo "   ❌ last_message_direction = {$servproConv['last_message_direction']} (deveria ser 'inbound')\n";
    }
} else {
    echo "❌ CONVERSA DO SERVPRO NÃO ENCONTRADA\n";
    echo "   Isso indica que ConversationService::resolveConversation() não criou/atualizou a conversa.\n";
}

echo "\n";

// Verifica conversa do Charles
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.last_message_at,
        c.updated_at
    FROM conversations c
    WHERE c.contact_external_id = '554796164699'
    LIMIT 1
");

$stmt->execute();
$charlesConv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($charlesConv) {
    $eventTime = strtotime($testEvent['created_at']);
    $charlesTime = strtotime($charlesConv['updated_at']);
    $diffSeconds = abs($eventTime - $charlesTime);
    
    echo "🔍 CONVERSA DO CHARLES (verificação de isolamento):\n";
    echo "   conversation_id: {$charlesConv['id']}\n";
    echo "   updated_at: {$charlesConv['updated_at']}\n";
    echo "   Diferença do evento: " . round($diffSeconds/60, 1) . " minutos\n";
    
    if ($diffSeconds <= 60) {
        echo "   ⚠️  ATENÇÃO: Conversa do Charles foi atualizada recentemente!\n";
        echo "      Possível matching indevido (heurística do 9º dígito).\n";
    } else {
        echo "   ✅ Conversa do Charles não foi atualizada (isolamento OK)\n";
    }
}

echo "\n";

// Testa endpoint de updates
$afterTimestamp = date('Y-m-d H:i:s', strtotime('-1 hour'));
$stmt = $db->prepare("
    SELECT MAX(GREATEST(COALESCE(c.updated_at, '1970-01-01'), COALESCE(c.last_message_at, '1970-01-01'))) as latest_update_ts
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
    AND (c.updated_at > ? OR c.last_message_at > ?)
    LIMIT 1
");

$stmt->execute([$afterTimestamp, $afterTimestamp]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$latestUpdateTs = $result['latest_update_ts'] ?? null;

echo "🔍 ENDPOINT DE UPDATES:\n";
if ($latestUpdateTs) {
    echo "   ✅ Retornaria: has_updates = true\n";
    echo "   latest_update_ts: {$latestUpdateTs}\n";
    
    // Verifica se ServPro está incluído
    $stmt = $db->prepare("
        SELECT c.id, c.contact_external_id, c.last_message_at
        FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        AND (c.contact_external_id = '554796474223' OR c.contact_external_id LIKE '554796474223%')
        AND (c.updated_at > ? OR c.last_message_at > ?)
        LIMIT 1
    ");
    $stmt->execute([$afterTimestamp, $afterTimestamp]);
    $servproInUpdate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($servproInUpdate) {
        echo "   ✅ Conversa do ServPro está incluída no resultado\n";
    } else {
        echo "   ⚠️  Conversa do ServPro NÃO está incluída no resultado\n";
    }
} else {
    echo "   ❌ Retornaria: has_updates = false\n";
}

echo "\n=== RESUMO ===\n";
echo "event_id: {$testEvent['event_id']}\n";
echo "event_type: {$testEvent['event_type']}\n";
echo "channel_id: " . ($channelId ?: 'NULL') . "\n";
echo "tenant_id: " . ($testEvent['tenant_id'] ?: 'NULL') . "\n";
echo "conversation_id: " . ($servproConv['id'] ?? 'NENHUMA') . "\n";
echo "last_message_at: " . ($servproConv['last_message_at'] ?? 'N/A') . "\n";
echo "unread_count: " . ($servproConv['unread_count'] ?? 'N/A') . "\n";
echo "last_message_direction: " . ($servproConv['last_message_direction'] ?? 'N/A') . "\n";
echo "endpoint_updates: " . ($latestUpdateTs ? 'has_updates=true' : 'has_updates=false') . "\n";

echo "\n=== CONCLUSÃO ===\n";
if (!$isInbound) {
    echo "(A) CLASSIFICAÇÃO: ❌ Evento classificado como OUTBOUND\n";
} else {
    echo "(A) CLASSIFICAÇÃO: ✅ OK\n";
}

if (empty($servproConv) || ($isInbound && $servproConv['unread_count'] == 0)) {
    echo "(B) MATCHING: ❌ Conversa não atualizada ou unread_count não incrementou\n";
} elseif ($charlesConv) {
    $eventTime = strtotime($testEvent['created_at']);
    $charlesTime = strtotime($charlesConv['updated_at']);
    if (abs($eventTime - $charlesTime) <= 60) {
        echo "(B) MATCHING: ❌ Conversa errada atualizada (Charles)\n";
    } else {
        echo "(B) MATCHING: ✅ OK\n";
    }
} else {
    echo "(B) MATCHING: ✅ OK\n";
}

if (!$latestUpdateTs) {
    echo "(C) POLLING: ❌ Endpoint não retorna atualização\n";
} else {
    echo "(C) POLLING: ✅ OK\n";
}

