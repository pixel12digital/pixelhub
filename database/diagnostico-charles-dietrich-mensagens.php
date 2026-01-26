<?php

/**
 * Script de diagnóstico para verificar por que as mensagens do Charles Dietrich não aparecem
 * 
 * Uso: php database/diagnostico-charles-dietrich-mensagens.php
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

echo "=== DIAGNÓSTICO: Mensagens do Charles Dietrich não aparecem ===\n\n";

// 1. Busca conversas do Charles Dietrich
echo "1. Buscando conversas do Charles Dietrich:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        remote_key,
        tenant_id,
        channel_id,
        channel_type,
        unread_count,
        last_message_at,
        created_at,
        updated_at
    FROM conversations
    WHERE contact_name LIKE '%Charles%' OR contact_name LIKE '%Dietrich%'
    ORDER BY last_message_at DESC
    LIMIT 10
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ❌ NENHUMA CONVERSA ENCONTRADA\n";
} else {
    echo "   ✅ Encontradas " . count($conversations) . " conversa(s):\n";
    foreach ($conversations as $conv) {
        echo "   - Conversation ID: {$conv['id']}\n";
        echo "     Contact External ID: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
        echo "     Remote Key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
        echo "     Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
        echo "     Unread Count: {$conv['unread_count']}\n";
        echo "     Last Message At: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
        echo "\n";
        
        // Para cada conversa, verifica eventos
        $conversationId = $conv['id'];
        $contactExternalId = $conv['contact_external_id'];
        $tenantId = $conv['tenant_id'];
        $channelId = $conv['channel_id'];
        
        echo "   2. Verificando eventos para conversation_id={$conversationId}:\n";
        
        // Busca eventos que deveriam aparecer nesta conversa
        $eventStmt = $db->prepare("
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
                JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND (
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )
            ORDER BY ce.created_at DESC
            LIMIT 20
        ");
        
        // Tenta diferentes variações do contact_external_id
        $contactPattern = "%{$contactExternalId}%";
        $eventStmt->execute([$contactPattern, $contactPattern, $contactPattern, $contactPattern]);
        $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            echo "      ❌ NENHUM EVENTO ENCONTRADO para contact_external_id='{$contactExternalId}'\n";
            
            // Tenta buscar sem filtro de contato (apenas por tenant_id e channel_id)
            if ($tenantId && $channelId) {
                echo "      Tentando buscar por tenant_id={$tenantId} e channel_id='{$channelId}':\n";
                $eventStmt2 = $db->prepare("
                    SELECT 
                        ce.id,
                        ce.event_id,
                        ce.event_type,
                        ce.tenant_id,
                        ce.created_at,
                        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
                        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as metadata_channel_id
                    FROM communication_events ce
                    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                    AND ce.tenant_id = ?
                    AND (
                        LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = LOWER(TRIM(REPLACE(?, ' ', '')))
                        OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
                        OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
                        OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
                    )
                    ORDER BY ce.created_at DESC
                    LIMIT 20
                ");
                $eventStmt2->execute([$tenantId, $channelId, $channelId, $channelId, $channelId]);
                $events2 = $eventStmt2->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($events2)) {
                    echo "         ❌ NENHUM EVENTO ENCONTRADO mesmo com tenant_id e channel_id\n";
                } else {
                    echo "         ✅ Encontrados " . count($events2) . " evento(s) por tenant_id/channel_id:\n";
                    foreach ($events2 as $event) {
                        echo "         - Event ID: {$event['event_id']}\n";
                        echo "           From: " . ($event['from_field'] ?: 'NULL') . "\n";
                        echo "           Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
                        echo "           Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
                        echo "           Created At: {$event['created_at']}\n";
                        echo "\n";
                    }
                }
            }
        } else {
            echo "      ✅ Encontrados " . count($events) . " evento(s):\n";
            foreach ($events as $event) {
                echo "      - Event ID: {$event['event_id']}\n";
                echo "        Event Type: {$event['event_type']}\n";
                echo "        From: " . ($event['from_field'] ?: ($event['message_from'] ?: 'NULL')) . "\n";
                echo "        To: " . ($event['to_field'] ?: ($event['message_to'] ?: 'NULL')) . "\n";
                echo "        Text: " . substr(($event['text'] ?: $event['body'] ?: $event['message_text'] ?: $event['message_body'] ?: 'NULL'), 0, 100) . "\n";
                echo "        Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
                echo "        Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
                echo "        Created At: {$event['created_at']}\n";
                echo "\n";
            }
        }
        
        echo "\n";
    }
}

// 3. Busca eventos recentes do pixel12digital (channel)
echo "\n3. Buscando eventos recentes do channel 'pixel12digital':\n";
$stmt = $db->prepare("
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
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = LOWER(TRIM(REPLACE('pixel12digital', ' ', '')))
        OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.sessionId') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.channelId') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.channel') = 'pixel12digital'
    )
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute();
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents)) {
    echo "   ❌ NENHUM EVENTO ENCONTRADO para channel 'pixel12digital'\n";
} else {
    echo "   ✅ Encontrados " . count($recentEvents) . " evento(s) recentes:\n";
    foreach ($recentEvents as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     From: " . ($event['from_field'] ?: 'NULL') . "\n";
        echo "     Text: " . substr(($event['text'] ?: 'NULL'), 0, 100) . "\n";
        echo "     Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
        echo "     Created At: {$event['created_at']}\n";
        echo "\n";
    }
}

// 4. Verifica tenant_message_channels para pixel12digital
echo "\n4. Verificando tenant_message_channels para 'pixel12digital':\n";
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        provider,
        channel_id,
        session_id,
        is_enabled
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    AND (
        channel_id = 'pixel12digital'
        OR LOWER(TRIM(REPLACE(channel_id, ' ', ''))) = LOWER(TRIM(REPLACE('pixel12digital', ' ', '')))
        OR session_id = 'pixel12digital'
        OR LOWER(TRIM(REPLACE(session_id, ' ', ''))) = LOWER(TRIM(REPLACE('pixel12digital', ' ', '')))
    )
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "   ❌ NENHUM CANAL ENCONTRADO para 'pixel12digital'\n";
} else {
    echo "   ✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $channel) {
        echo "   - ID: {$channel['id']}\n";
        echo "     Tenant ID: " . ($channel['tenant_id'] ?: 'NULL') . "\n";
        echo "     Channel ID: " . ($channel['channel_id'] ?: 'NULL') . "\n";
        echo "     Session ID: " . ($channel['session_id'] ?: 'NULL') . "\n";
        echo "     Is Enabled: " . ($channel['is_enabled'] ? 'true' : 'false') . "\n";
        echo "\n";
    }
}

echo "\n=== Fim do diagnóstico ===\n";

