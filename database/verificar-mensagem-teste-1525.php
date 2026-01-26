<?php

/**
 * Script para verificar se a mensagem "teste-1525" foi recebida
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando mensagem 'teste-1525' ===\n\n";

// 1. Busca eventos com texto "teste-1525"
echo "1. Buscando eventos com texto 'teste-1525':\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.status,
        ce.tenant_id,
        ce.created_at,
        TIMESTAMPDIFF(SECOND, ce.created_at, NOW()) as seconds_ago,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) as message_body
    FROM communication_events ce
    WHERE (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) LIKE '%teste-1525%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) LIKE '%teste-1525%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) LIKE '%teste-1525%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) LIKE '%teste-1525%'
        OR ce.payload LIKE '%teste-1525%'
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO encontrado com texto 'teste-1525'\n\n";
    
    // 2. Busca eventos muito recentes (últimos 2 minutos)
    echo "2. Buscando eventos dos últimos 2 minutos:\n";
    $stmt2 = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.status,
            ce.created_at,
            TIMESTAMPDIFF(SECOND, ce.created_at, NOW()) as seconds_ago,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt2->execute();
    $recentEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recentEvents)) {
        echo "   ❌ NENHUM EVENTO recebido nos últimos 2 minutos\n";
        echo "   ⚠️  Isso indica que o webhook não está recebendo mensagens\n\n";
    } else {
        echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
        foreach ($recentEvents as $event) {
            $time = date('H:i:s', strtotime($event['created_at']));
            $text = substr(($event['text'] ?: 'NULL'), 0, 50);
            $secondsAgo = $event['seconds_ago'];
            echo "   - {$time} ({$secondsAgo}s atrás) | Status: {$event['status']} | Text: {$text}\n";
        }
        echo "\n";
    }
    
    // 3. Busca eventos do Charles Dietrich recentes
    echo "3. Buscando eventos recentes do Charles Dietrich (últimos 10 minutos):\n";
    $stmt3 = $db->prepare("
        SELECT 
            ce.event_id,
            ce.created_at,
            TIMESTAMPDIFF(SECOND, ce.created_at, NOW()) as seconds_ago,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
        )
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt3->execute();
    $charlesEvents = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($charlesEvents)) {
        echo "   ❌ NENHUM EVENTO encontrado do Charles Dietrich nos últimos 10 minutos\n";
    } else {
        echo "   ✅ Encontrados " . count($charlesEvents) . " evento(s):\n";
        foreach ($charlesEvents as $event) {
            $time = date('H:i:s', strtotime($event['created_at']));
            $secondsAgo = $event['seconds_ago'];
            echo "   - {$time} ({$secondsAgo}s atrás): {$event['text']}\n";
        }
    }
    
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s) com 'teste-1525':\n\n";
    foreach ($events as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Event Type: {$event['event_type']}\n";
        echo "     Status: {$event['status']}\n";
        echo "     From: " . ($event['from_field'] ?: ($event['message_from'] ?: 'NULL')) . "\n";
        echo "     Text: " . ($event['text'] ?: $event['body'] ?: $event['message_text'] ?: $event['message_body'] ?: 'NULL') . "\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID: " . ($event['channel_id'] ?: 'NULL') . "\n";
        echo "     Created At: {$event['created_at']}\n";
        echo "     Seconds Ago: {$event['seconds_ago']}\n";
        
        // Verifica se a conversa existe
        $from = $event['from_field'] ?: $event['message_from'];
        if ($from) {
            $phoneDigits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $from));
            
            echo "\n     4. Verificando se conversa existe:\n";
            $convStmt = $db->prepare("
                SELECT 
                    id,
                    conversation_key,
                    contact_external_id,
                    tenant_id,
                    channel_id,
                    unread_count,
                    last_message_at
                FROM conversations
                WHERE contact_external_id = ?
                   OR contact_external_id LIKE ?
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            $phonePattern = "%{$phoneDigits}%";
            $convStmt->execute([$phoneDigits, $phonePattern]);
            $conversation = $convStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conversation) {
                echo "        ✅ Conversa encontrada:\n";
                echo "          ID: {$conversation['id']}\n";
                echo "          Tenant ID: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
                echo "          Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
                echo "          Unread Count: {$conversation['unread_count']}\n";
                echo "          Last Message At: " . ($conversation['last_message_at'] ?: 'NULL') . "\n";
            } else {
                echo "        ❌ Nenhuma conversa encontrada para {$phoneDigits}\n";
            }
        }
        
        echo "\n";
    }
}

// 4. Verifica eventos em 'queued' ou 'failed' recentes
echo "\n4. Verificando eventos com problemas (queued/failed) dos últimos 5 minutos:\n";
$stmt4 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.status,
        ce.created_at,
        TIMESTAMPDIFF(SECOND, ce.created_at, NOW()) as seconds_ago,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.status IN ('queued', 'failed')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt4->execute();
$problemEvents = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($problemEvents)) {
    echo "   ✅ Nenhum evento com problemas encontrado\n";
} else {
    echo "   ⚠️  Encontrados " . count($problemEvents) . " evento(s) com problemas:\n";
    foreach ($problemEvents as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        $secondsAgo = $event['seconds_ago'];
        echo "   - {$time} ({$secondsAgo}s atrás) | Status: {$event['status']} | Text: " . substr(($event['text'] ?: 'NULL'), 0, 50) . "\n";
    }
}

echo "\n=== Conclusão ===\n";
if (empty($events)) {
    echo "❌ A mensagem 'teste-1525' NÃO foi encontrada no banco de dados.\n";
    echo "   Isso significa que ela não chegou ao webhook ou não foi processada.\n";
    echo "\n   Verifique:\n";
    echo "   1. Se a mensagem foi realmente enviada pelo WhatsApp\n";
    echo "   2. Os logs do servidor para erros recentes\n";
    echo "   3. Se o webhook está acessível do gateway\n";
} else {
    echo "✅ A mensagem 'teste-1525' FOI encontrada no banco de dados!\n";
    echo "   Verifique se ela aparece no painel de comunicação.\n";
}

echo "\n=== Fim da verificação ===\n";

