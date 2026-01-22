<?php
/**
 * Script para investigar por que mensagens do Ponto Do Golfe não aparecem na thread
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== INVESTIGANDO: PONTO DO GOLFE ===\n\n";

// Busca conversa do Ponto Do Golfe
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        channel_id,
        remote_key,
        message_count,
        last_message_at,
        created_at
    FROM conversations
    WHERE contact_name LIKE '%Ponto Do Golfe%' OR contact_external_id LIKE '%130894027333804%'
    ORDER BY last_message_at DESC
    LIMIT 5
");

$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada!\n";
    exit(1);
}

foreach ($conversations as $conv) {
    echo "📋 CONVERSA ID: {$conv['id']}\n";
    echo "   Nome: {$conv['contact_name']}\n";
    echo "   Contato: {$conv['contact_external_id']}\n";
    echo "   Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "   Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
    echo "   Remote Key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
    echo "   Message Count: {$conv['message_count']}\n";
    echo "   Última mensagem: {$conv['last_message_at']}\n";
    echo "\n";
    
    $conversationId = $conv['id'];
    $contactExternalId = $conv['contact_external_id'];
    $tenantId = $conv['tenant_id'];
    $sessionId = $conv['channel_id'] ?? '';
    
    // 1. Conta eventos brutos
    $pattern = "%{$contactExternalId}%";
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        )
    ");
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $totalEvents = $stmt->fetchColumn();
    
    echo "   1️⃣  Total de eventos (busca por contato): {$totalEvents}\n";
    
    // 2. Conta eventos com filtro de channel_id
    if ($sessionId) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND (
                JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
                OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
                OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
                OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
            )
            AND (
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )
        ");
        $stmt->execute([$sessionId, $sessionId, $sessionId, $sessionId, $pattern, $pattern, $pattern, $pattern]);
        $eventsWithChannel = $stmt->fetchColumn();
        echo "   2️⃣  Eventos com channel_id='{$sessionId}': {$eventsWithChannel}\n";
    }
    
    // 3. Busca eventos recentes para análise
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as event_to,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id
        FROM communication_events ce
        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        )
        ORDER BY ce.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $sampleEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n   3️⃣  Amostra de eventos (últimos 5):\n";
    foreach ($sampleEvents as $idx => $event) {
        $eventNum = $idx + 1;
        $text = $event['text'] ?: $event['message_text'] ?: '[vazio]';
        $textPreview = mb_substr($text, 0, 50);
        
        echo "      Evento #{$eventNum}:\n";
        echo "        ID: {$event['event_id']}\n";
        echo "        Tipo: {$event['event_type']}\n";
        echo "        Created: {$event['created_at']}\n";
        echo "        Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "        From: " . ($event['event_from'] ?: 'NULL') . "\n";
        echo "        To: " . ($event['event_to'] ?: 'NULL') . "\n";
        echo "        Texto: {$textPreview}\n";
        echo "        Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
        
        // Verifica se bate com tenant_id
        if ($tenantId && $event['tenant_id'] != $tenantId) {
            echo "        ⚠️  TENANT_ID NÃO BATE: evento={$event['tenant_id']}, conversa={$tenantId}\n";
        }
        
        // Verifica se bate com channel_id
        if ($sessionId) {
            $eventChannelId = trim($event['metadata_channel_id'] ?? '', '"');
            if ($eventChannelId != $sessionId) {
                echo "        ⚠️  CHANNEL_ID NÃO BATE: evento={$eventChannelId}, conversa={$sessionId}\n";
            }
        }
        
        echo "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

