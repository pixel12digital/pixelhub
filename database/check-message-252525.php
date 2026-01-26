<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';

\PixelHub\Core\Env::load();
$pdo = \PixelHub\Core\DB::getConnection();

echo "=== VERIFICAÇÃO: Mensagem 252525 ===\n\n";

// 1. Buscar eventos recentes com texto "252525"
echo "1) EVENTOS COM TEXTO '252525' (últimas 2 horas):\n";
echo str_repeat("=", 80) . "\n";

$stmt = $pdo->prepare("
    SELECT 
        id,
        event_type,
        status,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) AS from_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) AS to_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.text')) AS text,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.raw.payload.body')) AS body
    FROM communication_events
    WHERE (payload LIKE '%252525%' OR payload LIKE '%25252%')
       AND created_at >= NOW() - INTERVAL 2 HOUR
    ORDER BY id DESC
    LIMIT 10
");

$stmt->execute();
$events = $stmt->fetchAll();

if (empty($events)) {
    echo "❌ Nenhum evento encontrado com texto '252525' nas últimas 2 horas\n\n";
} else {
    foreach ($events as $event) {
        echo "  ID: {$event['id']}\n";
        echo "  Tipo: {$event['event_type']}\n";
        echo "  Status: {$event['status']}\n";
        echo "  Channel ID: " . ($event['channel_id'] ?: 'NULL') . "\n";
        echo "  From: " . ($event['from_id'] ?: 'NULL') . "\n";
        echo "  To: " . ($event['to_id'] ?: 'NULL') . "\n";
        echo "  Text: " . ($event['text'] ?: ($event['body'] ?: 'NULL')) . "\n";
        echo "  Created: {$event['created_at']}\n";
        echo str_repeat("-", 80) . "\n";
    }
}

// 2. Para cada evento, verificar se conversa foi criada/atualizada
if (!empty($events)) {
    echo "\n2) VERIFICAÇÃO DE CONVERSAS:\n";
    echo str_repeat("=", 80) . "\n";
    
    foreach ($events as $event) {
        $fromId = $event['from_id'] ?: null;
        $channelId = $event['channel_id'] ?: null;
        
        if (!$fromId || !$channelId) {
            echo "⚠️  Evento {$event['id']}: from_id ou channel_id está NULL, pulando...\n\n";
            continue;
        }
        
        echo "Evento {$event['id']}:\n";
        echo "  From ID: {$fromId}\n";
        echo "  Channel ID: {$channelId}\n";
        
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
        
        echo "  Remote Key Esperado: " . ($remoteKeyExpected ?: 'NULL') . "\n";
        
        if ($remoteKeyExpected) {
            // Busca conversa com esse remote_key
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
                WHERE remote_key = ?
                  AND (channel_id = ? OR session_id = ?)
                ORDER BY updated_at DESC
                LIMIT 3
            ");
            
            $stmt2->execute([$remoteKeyExpected, $channelId, $channelId]);
            $conversations = $stmt2->fetchAll();
            
            if (empty($conversations)) {
                echo "  ❌ NENHUMA CONVERSA ENCONTRADA com remote_key='{$remoteKeyExpected}' e channel_id='{$channelId}'\n";
                echo "     Status do evento: {$event['status']}\n";
                echo "     ⚠️  A conversa NÃO FOI CRIADA/ATUALIZADA para este evento!\n";
            } else {
                echo "  ✅ Conversa(s) encontrada(s):\n";
                foreach ($conversations as $conv) {
                    echo "     - Conversation ID: {$conv['id']}\n";
                    echo "       Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
                    echo "       Session ID: " . ($conv['session_id'] ?: 'NULL') . "\n";
                    echo "       Contact External ID: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
                    echo "       Remote Key: {$conv['remote_key']}\n";
                    echo "       Contact Key: " . ($conv['contact_key'] ?: 'NULL') . "\n";
                    echo "       Thread Key: " . ($conv['thread_key'] ?: 'NULL') . "\n";
                    echo "       Updated: {$conv['updated_at']}\n";
                    echo "       Message Count: {$conv['message_count']}\n";
                    
                    // Verifica se há mensagens dessa conversa nos eventos
                    $stmt3 = $pdo->prepare("
                        SELECT COUNT(*) as total
                        FROM communication_events
                        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                          AND (payload LIKE ? OR payload LIKE ?)
                          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) = ?
                          AND created_at >= NOW() - INTERVAL 2 HOUR
                    ");
                    $fromPattern = '%' . $pdo->quote($fromId) . '%';
                    $stmt3->execute([$fromPattern, $fromPattern, $channelId]);
                    $msgCount = $stmt3->fetch()['total'];
                    echo "       Eventos de mensagem encontrados (últimas 2h): {$msgCount}\n";
                }
            }
        }
        echo "\n";
    }
}

// 3. Estatísticas gerais
echo "\n3) ESTATÍSTICAS RECENTES:\n";
echo str_repeat("=", 80) . "\n";

$stmt4 = $pdo->query("
    SELECT 
        COUNT(*) as total_events,
        SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.channel_id')) AS channel_id,
        COUNT(*) as count
    FROM communication_events
    WHERE created_at >= NOW() - INTERVAL 2 HOUR
      AND event_type LIKE 'whatsapp%message%'
    GROUP BY channel_id
    ORDER BY count DESC
");

$stats = $stmt4->fetchAll();

if (!empty($stats)) {
    foreach ($stats as $stat) {
        echo "  Channel: " . ($stat['channel_id'] ?: 'NULL') . "\n";
        echo "    Total eventos: {$stat['total_events']}\n";
        echo "    Processed: {$stat['processed']}\n";
        echo "    Failed: {$stat['failed']}\n";
        echo "\n";
    }
}

echo "\n";









