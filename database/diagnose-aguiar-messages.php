<?php
/**
 * Script de diagnóstico: Por que aparecem 15 mensagens do Aguiar mas não são exibidas na thread?
 * 
 * Este script verifica:
 * 1. Quantas mensagens estão na tabela conversations (message_count)
 * 2. Quantos eventos existem em communication_events para essa conversa
 * 3. Quantos eventos passam pelos filtros de busca
 * 4. Por que os eventos estão sendo excluídos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

// Busca conversa do Aguiar
echo "=== DIAGNÓSTICO: CONVERSA DO AGUIAR ===\n\n";

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
    WHERE contact_name LIKE '%Aguiar%' OR contact_external_id LIKE '%556993245042%'
    ORDER BY last_message_at DESC
    LIMIT 5
");

$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada com 'Aguiar' ou número 556993245042\n";
    exit(1);
}

foreach ($conversations as $conv) {
    echo "📋 CONVERSA ID: {$conv['id']}\n";
    echo "   Nome: {$conv['contact_name']}\n";
    echo "   Contato: {$conv['contact_external_id']}\n";
    echo "   Tenant ID: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
    echo "   Channel ID: " . ($conv['channel_id'] ?: 'NULL') . "\n";
    echo "   Remote Key: " . ($conv['remote_key'] ?: 'NULL') . "\n";
    echo "   Message Count (tabela): {$conv['message_count']}\n";
    echo "   Última mensagem: {$conv['last_message_at']}\n";
    echo "\n";
    
    $conversationId = $conv['id'];
    $contactExternalId = $conv['contact_external_id'];
    $tenantId = $conv['tenant_id'];
    $sessionId = $conv['channel_id'] ?? '';
    
    // 1. Conta eventos brutos em communication_events
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
    
    $pattern = "%{$contactExternalId}%";
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $totalEvents = $stmt->fetchColumn();
    
    echo "   1️⃣  Total de eventos em communication_events (busca por contato): {$totalEvents}\n";
    
    // 2. Conta eventos com filtro de tenant_id
    if ($tenantId) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND ce.tenant_id = ?
            AND (
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )
        ");
        $stmt->execute([$tenantId, $pattern, $pattern, $pattern, $pattern]);
        $eventsWithTenant = $stmt->fetchColumn();
        echo "   2️⃣  Eventos com tenant_id={$tenantId}: {$eventsWithTenant}\n";
    } else {
        echo "   2️⃣  Tenant ID é NULL - não filtra por tenant_id\n";
    }
    
    // 3. Conta eventos com filtro de channel_id
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
        echo "   3️⃣  Eventos com channel_id='{$sessionId}': {$eventsWithChannel}\n";
    } else {
        echo "   3️⃣  Channel ID é NULL - não filtra por channel_id\n";
    }
    
    // 4. Busca alguns eventos para análise detalhada
    $stmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            ce.payload,
            ce.metadata
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
    
        echo "\n   4️⃣  Amostra de eventos (últimos 5):\n";
    foreach ($sampleEvents as $idx => $event) {
        $payload = json_decode($event['payload'], true);
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'NULL';
        $to = $payload['to'] ?? $payload['message']['to'] ?? 'NULL';
        $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? '';
        $textPreview = mb_substr($text, 0, 50);
        $eventNum = $idx + 1;
        
        echo "      Evento #{$eventNum}:\n";
        echo "        ID: {$event['event_id']}\n";
        echo "        Tipo: {$event['event_type']}\n";
        echo "        Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "        From: {$from}\n";
        echo "        To: {$to}\n";
        echo "        Texto: " . ($textPreview ?: '[vazio ou mídia]') . "\n";
        echo "        Created: {$event['created_at']}\n";
        
        // Verifica se bate com tenant_id da conversa
        if ($tenantId && $event['tenant_id'] != $tenantId) {
            echo "        ⚠️  TENANT_ID NÃO BATE: evento={$event['tenant_id']}, conversa={$tenantId}\n";
        }
        
        // Verifica se bate com channel_id da conversa
        if ($sessionId) {
            $eventMetadata = json_decode($event['metadata'] ?? '{}', true);
            $eventChannelId = $payload['channel_id'] 
                ?? $payload['channel'] 
                ?? $payload['session']['id'] 
                ?? $payload['session']['session']
                ?? $eventMetadata['channel_id'] ?? null;
            
            if ($eventChannelId != $sessionId) {
                echo "        ⚠️  CHANNEL_ID NÃO BATE: evento={$eventChannelId}, conversa={$sessionId}\n";
            }
        }
        
        echo "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
}

echo "✅ Diagnóstico concluído!\n";
echo "\n";
echo "💡 POSSÍVEIS CAUSAS:\n";
echo "   1. Eventos têm tenant_id diferente do da conversa\n";
echo "   2. Eventos têm channel_id diferente do da conversa\n";
echo "   3. Eventos não têm conteúdo nem mídia (são pulados no frontend)\n";
echo "   4. Normalização de telefone não está batendo (ex: com/sem 9º dígito)\n";
echo "   5. Eventos usam @lid mas não estão mapeados corretamente\n";

