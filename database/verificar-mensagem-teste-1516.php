<?php

/**
 * Script para verificar se a mensagem "teste-1516" foi recebida
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

echo "=== Verificando mensagem 'teste-1516' ===\n\n";

// 1. Busca eventos com texto "teste-1516"
echo "1. Buscando eventos com texto 'teste-1516':\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.status,
        ce.tenant_id,
        ce.created_at,
        ce.metadata,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) as message_body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id,
        ce.payload
    FROM communication_events ce
    WHERE (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) LIKE '%teste-1516%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) LIKE '%teste-1516%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) LIKE '%teste-1516%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) LIKE '%teste-1516%'
        OR ce.payload LIKE '%teste-1516%'
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO encontrado com texto 'teste-1516'\n";
    
    // 2. Busca eventos recentes do Charles Dietrich
    echo "\n2. Buscando eventos recentes do Charles Dietrich (últimas 2 horas):\n";
    $stmt2 = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.status,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
        )
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    $stmt2->execute();
    $recentEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recentEvents)) {
        echo "   ❌ NENHUM EVENTO encontrado nas últimas 2 horas do Charles Dietrich\n";
    } else {
        echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
        foreach ($recentEvents as $event) {
            $time = date('H:i:s', strtotime($event['created_at']));
            $text = substr(($event['text'] ?: 'NULL'), 0, 50);
            echo "   - {$time} | Status: {$event['status']} | Text: {$text}\n";
        }
    }
    
    // 3. Busca eventos com padrão "teste-15XX" de hoje
    echo "\n3. Buscando eventos com padrão 'teste-15XX' de hoje:\n";
    $stmt3 = $db->prepare("
        SELECT 
            ce.event_id,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND DATE(ce.created_at) = CURDATE()
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) LIKE 'teste-15%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) LIKE 'teste-15%'
        )
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
        )
        ORDER BY ce.created_at DESC
    ");
    $stmt3->execute();
    $teste15Events = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($teste15Events)) {
        echo "   ❌ NENHUM EVENTO encontrado com padrão 'teste-15XX'\n";
    } else {
        echo "   ✅ Encontrados " . count($teste15Events) . " evento(s):\n";
        foreach ($teste15Events as $event) {
            $time = date('H:i:s', strtotime($event['created_at']));
            echo "   - {$time}: {$event['text']}\n";
        }
    }
    
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s) com 'teste-1516':\n";
    foreach ($events as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Event Type: {$event['event_type']}\n";
        echo "     Status: {$event['status']}\n";
        echo "     From: " . ($event['from_field'] ?: ($event['message_from'] ?: 'NULL')) . "\n";
        echo "     To: " . ($event['to_field'] ?: ($event['message_to'] ?: 'NULL')) . "\n";
        echo "     Text: " . ($event['text'] ?: $event['body'] ?: $event['message_text'] ?: $event['message_body'] ?: 'NULL') . "\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
        echo "     Created At: {$event['created_at']}\n";
        
        // Verifica se a conversa existe e se o evento seria encontrado
        $from = $event['from_field'] ?: $event['message_from'];
        if ($from) {
            // Remove @c.us, @s.whatsapp.net, etc.
            $phoneDigits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $from));
            
            echo "\n     4. Verificando se conversa existe para este evento:\n";
            $convStmt = $db->prepare("
                SELECT 
                    id,
                    conversation_key,
                    contact_external_id,
                    remote_key,
                    tenant_id,
                    channel_id,
                    unread_count,
                    last_message_at
                FROM conversations
                WHERE contact_external_id = ?
                   OR contact_external_id LIKE ?
                   OR remote_key LIKE ?
                ORDER BY last_message_at DESC
                LIMIT 5
            ");
            $phonePattern = "%{$phoneDigits}%";
            $convStmt->execute([$phoneDigits, $phonePattern, "%tel:{$phoneDigits}%"]);
            $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($conversations)) {
                echo "        ❌ NENHUMA CONVERSA encontrada para {$phoneDigits}\n";
            } else {
                echo "        ✅ Encontradas " . count($conversations) . " conversa(s):\n";
                foreach ($conversations as $conv) {
                    echo "        - Conversation ID: {$conv['id']}\n";
                    echo "          Contact External ID: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
                    echo "          Remote Key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
                    echo "          Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
                    echo "          Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
                    echo "          Unread Count: {$conv['unread_count']}\n";
                    echo "          Last Message At: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
                    echo "\n";
                }
            }
        }
        
        echo "\n";
    }
}

// 4. Verifica eventos muito recentes (últimos 5 minutos)
echo "\n4. Eventos dos últimos 5 minutos (para verificar se webhook está funcionando):\n";
$stmt4 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.status,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt4->execute();
$veryRecentEvents = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($veryRecentEvents)) {
    echo "   ⚠️  NENHUM EVENTO recebido nos últimos 5 minutos\n";
    echo "   Isso indica que o webhook pode não estar recebendo mensagens\n";
} else {
    echo "   ✅ Encontrados " . count($veryRecentEvents) . " evento(s) recentes:\n";
    foreach ($veryRecentEvents as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        $text = substr(($event['text'] ?: 'NULL'), 0, 50);
        echo "   - {$time} | Status: {$event['status']} | Channel: " . ($event['channel_id'] ?: 'NULL') . " | Text: {$text}\n";
    }
}

echo "\n=== Conclusão ===\n";
if (empty($events)) {
    echo "❌ A mensagem 'teste-1516' NÃO foi encontrada no banco de dados.\n";
    echo "   Isso significa que ela não chegou ao webhook ou não foi processada.\n";
    echo "\n   Possíveis causas:\n";
    echo "   1. A mensagem não foi enviada pelo WhatsApp\n";
    echo "   2. O webhook não foi chamado pelo gateway\n";
    echo "   3. O webhook foi chamado mas houve erro no processamento\n";
    echo "   4. Há um problema de rede entre o gateway e o servidor\n";
} else {
    echo "✅ A mensagem 'teste-1516' FOI encontrada no banco de dados!\n";
    echo "   Verifique se ela aparece no painel de comunicação.\n";
}

echo "\n=== Fim da verificação ===\n";

