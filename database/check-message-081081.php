<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO: Mensagem 081081 ===\n\n";

// 1. Buscar evento mais recente com texto "081081"
echo "1) EVENTOS COM TEXTO '081081':\n";
echo str_repeat("=", 80) . "\n";

$stmt = $pdo->prepare("
    SELECT 
        id,
        event_type,
        status,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) AS from_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) AS to_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body')) AS body,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) AS text,
        LEFT(payload, 300) AS payload_preview
    FROM communication_events
    WHERE (payload LIKE '%081081%' OR payload LIKE '%08081%')
       AND created_at >= NOW() - INTERVAL 2 HOUR
    ORDER BY id DESC
    LIMIT 10
");

$stmt->execute();
$events = $stmt->fetchAll();

if (empty($events)) {
    echo "❌ Nenhum evento encontrado com texto '081081' nas últimas 2 horas\n\n";
} else {
    foreach ($events as $event) {
        echo "  ID: {$event['id']}\n";
        echo "  Tipo: {$event['event_type']}\n";
        echo "  Status: {$event['status']}\n";
        echo "  Channel ID: " . ($event['channel_id'] ?: 'NULL') . "\n";
        echo "  From: " . ($event['from_id'] ?: 'NULL') . "\n";
        echo "  To: " . ($event['to_id'] ?: 'NULL') . "\n";
        echo "  Body: " . ($event['body'] ?: ($event['text'] ?: 'NULL')) . "\n";
        echo "  Created: {$event['created_at']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 2. Buscar conversas recentes (últimas 2 horas)
echo "\n2) CONVERSAS RECENTES (últimas 2 horas):\n";
echo str_repeat("=", 80) . "\n";

$stmt2 = $pdo->prepare("
    SELECT 
        id,
        channel_id,
        session_id,
        contact_external_id,
        remote_key,
        contact_key,
        thread_key,
        updated_at,
        message_count
    FROM conversations
    WHERE updated_at >= NOW() - INTERVAL 2 HOUR
    ORDER BY updated_at DESC
    LIMIT 10
");

$stmt2->execute();
$conversations = $stmt2->fetchAll();

if (empty($conversations)) {
    echo "❌ Nenhuma conversa atualizada nas últimas 2 horas\n\n";
} else {
    foreach ($conversations as $conv) {
        echo "  ID: {$conv['id']}\n";
        echo "  Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
        echo "  Session ID: " . ($conv['session_id'] ?: 'NULL') . "\n";
        echo "  Contact External ID: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
        echo "  Remote Key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
        echo "  Contact Key: " . ($conv['contact_key'] ?: 'NULL') . "\n";
        echo "  Thread Key: " . ($conv['thread_key'] ?: 'NULL') . "\n";
        echo "  Updated: {$conv['updated_at']}\n";
        echo "  Message Count: {$conv['message_count']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 3. Se encontrou evento, verificar se conversa foi criada/atualizada
if (!empty($events)) {
    $event = $events[0];
    $fromId = $event['from_id'] ?: null;
    $channelId = $event['channel_id'] ?: null;
    
    echo "\n3) VERIFICAÇÃO DE MATCH (evento vs conversa):\n";
    echo str_repeat("=", 80) . "\n";
    
    if ($fromId && $channelId) {
        // Calcula remote_key esperado
        $remoteKeyExpected = null;
        if (strpos($fromId, '@lid') !== false) {
            if (preg_match('/^([0-9]+)@lid$/', $fromId, $m)) {
                $remoteKeyExpected = 'lid:' . $m[1];
            }
        } else {
            $digits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $fromId));
            if ($digits !== '') {
                $remoteKeyExpected = 'tel:' . $digits;
            }
        }
        
        echo "  Evento From ID: {$fromId}\n";
        echo "  Channel ID: {$channelId}\n";
        echo "  Remote Key Esperado: " . ($remoteKeyExpected ?: 'NULL') . "\n";
        
        if ($remoteKeyExpected) {
            // Busca conversa com esse remote_key
            $stmt3 = $pdo->prepare("
                SELECT 
                    id,
                    channel_id,
                    session_id,
                    contact_external_id,
                    remote_key,
                    contact_key,
                    thread_key,
                    updated_at
                FROM conversations
                WHERE remote_key = ?
                   AND (channel_id = ? OR session_id = ?)
                ORDER BY updated_at DESC
                LIMIT 5
            ");
            
            $stmt3->execute([$remoteKeyExpected, $channelId, $channelId]);
            $matched = $stmt3->fetchAll();
            
            if (empty($matched)) {
                echo "  ❌ NENHUMA CONVERSA ENCONTRADA com remote_key='{$remoteKeyExpected}' e channel_id='{$channelId}'\n";
                echo "     Isso significa que a conversa NÃO FOI CRIADA/ATUALIZADA!\n";
            } else {
                echo "  ✅ Conversa(s) encontrada(s):\n";
                foreach ($matched as $match) {
                    echo "     - Conversation ID: {$match['id']}\n";
                    echo "       Remote Key: {$match['remote_key']}\n";
                    echo "       Updated: {$match['updated_at']}\n";
                }
            }
        }
    }
}

echo "\n";

