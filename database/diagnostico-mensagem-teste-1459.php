<?php

/**
 * Script de diagnóstico para verificar a mensagem "teste-1459"
 * 
 * Uso: php database/diagnostico-mensagem-teste-1459.php
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== DIAGNÓSTICO: Mensagem 'teste-1459' não aparece ===\n\n";

// 1. Busca eventos com texto "teste-1459"
echo "1. Buscando eventos com texto 'teste-1459':\n";
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
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
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) LIKE '%teste-1459%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) LIKE '%teste-1459%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) LIKE '%teste-1459%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) LIKE '%teste-1459%'
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO ENCONTRADO com texto 'teste-1459'\n";
    echo "\n   2. Buscando eventos recentes do Charles Dietrich (554796164699):\n";
    
    // Busca eventos recentes do número do Charles
    $stmt2 = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.tenant_id,
            ce.created_at,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id
        FROM communication_events ce
        WHERE (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
        )
        AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt2->execute();
    $recentEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recentEvents)) {
        echo "      ❌ NENHUM EVENTO RECENTE encontrado para 554796164699\n";
    } else {
        echo "      ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
        foreach ($recentEvents as $event) {
            echo "      - Event ID: {$event['event_id']}\n";
            echo "        From: " . ($event['from_field'] ?: 'NULL') . "\n";
            echo "        Text: " . substr(($event['text'] ?: 'NULL'), 0, 100) . "\n";
            echo "        Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
            echo "        Channel ID: " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
            echo "        Created At: {$event['created_at']}\n";
            echo "\n";
        }
    }
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s) com 'teste-1459':\n";
    foreach ($events as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Event Type: {$event['event_type']}\n";
        echo "     From: " . ($event['from_field'] ?: ($event['message_from'] ?: 'NULL')) . "\n";
        echo "     To: " . ($event['to_field'] ?: ($event['message_to'] ?: 'NULL')) . "\n";
        echo "     Text: " . ($event['text'] ?: $event['body'] ?: $event['message_text'] ?: $event['message_body'] ?: 'NULL') . "\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
        echo "     Created At: {$event['created_at']}\n";
        echo "\n";
        
        // Verifica se a conversa existe e se o evento seria encontrado
        $from = $event['from_field'] ?: $event['message_from'];
        if ($from) {
            // Remove @c.us, @s.whatsapp.net, etc.
            $phoneDigits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $from));
            
            echo "     3. Verificando se conversa existe para este evento:\n";
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
                    
                    // Verifica se o evento seria encontrado pela query de busca
                    $conversationId = $conv['id'];
                    $contactExternalId = $conv['contact_external_id'];
                    $tenantId = $conv['tenant_id'];
                    $channelId = $conv['channel_id'];
                    
                    echo "\n         4. Verificando se evento seria encontrado pela query de busca:\n";
                    
                    // Simula a query de busca
                    $contactPattern = "%{$contactExternalId}%";
                    $checkStmt = $db->prepare("
                        SELECT COUNT(*) as count
                        FROM communication_events ce
                        WHERE ce.event_id = ?
                        AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                        AND (
                            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
                        )
                    ");
                    $checkStmt->execute([$event['event_id'], $contactPattern, $contactPattern, $contactPattern, $contactPattern]);
                    $checkResult = $checkStmt->fetch();
                    
                    if ($checkResult['count'] > 0) {
                        echo "            ✅ Evento SERIA encontrado pela query de busca\n";
                    } else {
                        echo "            ❌ Evento NÃO seria encontrado pela query de busca\n";
                        echo "            Motivo: Contact External ID '{$contactExternalId}' não bate com From '{$from}'\n";
                    }
                    
                    // Verifica filtro de tenant_id/channel_id
                    if ($channelId) {
                        $checkStmt2 = $db->prepare("
                            SELECT COUNT(*) as count
                            FROM communication_events ce
                            WHERE ce.event_id = ?
                            AND (
                                LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = LOWER(TRIM(REPLACE(?, ' ', '')))
                                OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
                                OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
                                OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
                            )
                        ");
                        $checkStmt2->execute([$event['event_id'], $channelId, $channelId, $channelId, $channelId]);
                        $checkResult2 = $checkStmt2->fetch();
                        
                        if ($checkResult2['count'] > 0) {
                            echo "            ✅ Evento passaria no filtro de channel_id\n";
                        } else {
                            echo "            ❌ Evento NÃO passaria no filtro de channel_id\n";
                            echo "            Motivo: Channel ID da conversa '{$channelId}' não bate com metadata '{$event['metadata_channel_id']}'\n";
                        }
                    }
                    
                    echo "\n";
                }
            }
        }
    }
}

// 5. Busca eventos com timestamp próximo a "14:59" (assumindo que foi enviado hoje)
echo "\n5. Buscando eventos recentes (últimas 2 horas) do Charles Dietrich:\n";
$stmt3 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.tenant_id,
        ce.created_at,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id
    FROM communication_events ce
    WHERE (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt3->execute();
$recentEvents2 = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents2)) {
    echo "   ❌ NENHUM EVENTO encontrado nas últimas 2 horas\n";
} else {
    echo "   ✅ Encontrados " . count($recentEvents2) . " evento(s) nas últimas 2 horas:\n";
    foreach ($recentEvents2 as $event) {
        $text = substr(($event['text'] ?: 'NULL'), 0, 50);
        echo "   - Created At: {$event['created_at']}\n";
        echo "     Text: {$text}\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID: " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
        echo "\n";
    }
}

echo "\n=== Fim do diagnóstico ===\n";

