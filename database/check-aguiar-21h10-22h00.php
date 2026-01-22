<?php
/**
 * Script para verificar mensagens do Aguiar entre 21:10 e 22:00
 * Canal: pixel12digital
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO MENSAGENS DO AGUIAR (21:10 - 22:00) ===\n\n";

$contactPhone = '556993245042';
$conversationId = 10; // ID da conversa do Aguiar
$channelId = 'pixel12digital';
$startTime = '2026-01-19 21:10:00';
$endTime = '2026-01-19 22:00:00';

// 1. Verifica conversa
echo "1. Informações da conversa:\n";
$stmt = $db->prepare("
    SELECT 
        id,
        contact_external_id,
        contact_name,
        tenant_id,
        channel_id,
        message_count,
        last_message_at
    FROM conversations
    WHERE id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "   ID: {$conversation['id']}\n";
    echo "   Nome: {$conversation['contact_name']}\n";
    echo "   Contato: {$conversation['contact_external_id']}\n";
    echo "   Tenant ID: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
    echo "   Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
    echo "   Message Count: {$conversation['message_count']}\n";
    echo "   Última mensagem: {$conversation['last_message_at']}\n";
} else {
    echo "   ❌ Conversa não encontrada!\n";
    exit(1);
}

echo "\n";

// 2. Busca TODOS os eventos do período (sem filtros)
echo "2. Eventos no período {$startTime} - {$endTime} (sem filtros):\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as event_to,
        JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id,
        JSON_EXTRACT(ce.payload, '$.session.id') as payload_session_id,
        LEFT(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')), 100) as text_preview
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= ? AND ce.created_at <= ?
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
    )
    ORDER BY ce.created_at ASC
");
$pattern = "%{$contactPhone}%";
$stmt->execute([$startTime, $endTime, $pattern, $pattern, $pattern, $pattern]);
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Total encontrado: " . count($allEvents) . " eventos\n\n";

if (empty($allEvents)) {
    echo "   ⚠️  NENHUM EVENTO ENCONTRADO NO PERÍODO!\n";
} else {
    foreach ($allEvents as $idx => $event) {
        $eventNum = $idx + 1;
        echo "   Evento #{$eventNum}:\n";
        echo "      ID: {$event['event_id']}\n";
        echo "      Tipo: {$event['event_type']}\n";
        echo "      Created: {$event['created_at']}\n";
        echo "      Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "      From: " . ($event['event_from'] ?: 'NULL') . "\n";
        echo "      To: " . ($event['event_to'] ?: 'NULL') . "\n";
        echo "      Channel ID (metadata): " . ($event['metadata_channel_id'] ?: 'NULL') . "\n";
        echo "      Session ID (payload): " . ($event['payload_session_id'] ?: 'NULL') . "\n";
        echo "      Texto: " . ($event['text_preview'] ?: '[vazio ou mídia]') . "\n";
        
        // Verifica se bate com channel_id
        $matchesChannel = false;
        if ($event['metadata_channel_id'] && trim($event['metadata_channel_id'], '"') === $channelId) {
            $matchesChannel = true;
        }
        if ($event['payload_session_id'] && trim($event['payload_session_id'], '"') === $channelId) {
            $matchesChannel = true;
        }
        
        if (!$matchesChannel && $channelId) {
            echo "      ⚠️  CHANNEL_ID NÃO BATE com '{$channelId}'\n";
        }
        
        // Verifica se bate com tenant_id
        if ($conversation['tenant_id'] && $event['tenant_id'] != $conversation['tenant_id']) {
            echo "      ⚠️  TENANT_ID NÃO BATE: evento={$event['tenant_id']}, conversa={$conversation['tenant_id']}\n";
        }
        
        echo "\n";
    }
}

echo "\n";

// 3. Busca eventos com filtro de channel_id
echo "3. Eventos com filtro de channel_id='{$channelId}':\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= ? AND ce.created_at <= ?
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
    ORDER BY ce.created_at ASC
");
$stmt->execute([$startTime, $endTime, $channelId, $channelId, $channelId, $channelId, $pattern, $pattern, $pattern, $pattern]);
$filteredEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Total encontrado: " . count($filteredEvents) . " eventos\n";

// 4. Busca TODOS os eventos da conversa (sem filtro de período)
echo "\n4. TODOS os eventos da conversa (sem filtro de período):\n";
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        MIN(created_at) as primeira,
        MAX(created_at) as ultima
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
$stmt->execute([$channelId, $channelId, $channelId, $channelId, $pattern, $pattern, $pattern, $pattern]);
$totalStats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "   Total de eventos: {$totalStats['total']}\n";
echo "   Primeira mensagem: {$totalStats['primeira']}\n";
echo "   Última mensagem: {$totalStats['ultima']}\n";

echo "\n✅ Verificação concluída!\n";

