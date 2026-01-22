<?php
/**
 * Script para verificar TODAS as 15 mensagens do Aguiar
 * e entender por que apenas 1 aparece na thread
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO TODAS AS 15 MENSAGENS DO AGUIAR ===\n\n";

$contactPhone = '556993245042';
$conversationId = 10;
$channelId = 'pixel12digital';

// Busca TODOS os eventos da conversa
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        ce.payload,
        ce.metadata,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as event_to,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.body')) as message_body,
        JSON_EXTRACT(ce.payload, '$.type') as type,
        JSON_EXTRACT(ce.payload, '$.message.type') as message_type
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
    ORDER BY ce.created_at ASC
");
$pattern = "%{$contactPhone}%";
$stmt->execute([$channelId, $channelId, $channelId, $channelId, $pattern, $pattern, $pattern, $pattern]);
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($allEvents) . "\n\n";

$withContent = 0;
$withoutContent = 0;
$withMedia = 0;

foreach ($allEvents as $idx => $event) {
    $eventNum = $idx + 1;
    
    // Extrai conteúdo
    $content = $event['text'] 
        ?: $event['body'] 
        ?: $event['message_text'] 
        ?: $event['message_body'] 
        ?: '';
    
    $type = trim($event['type'] ?: $event['message_type'] ?: '', '"');
    
    // Verifica se tem mídia processada
    $mediaStmt = $db->prepare("SELECT id, media_type, stored_path FROM communication_media WHERE event_id = ? LIMIT 1");
    $mediaStmt->execute([$event['event_id']]);
    $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
    
    $hasContent = !empty(trim($content));
    $hasMedia = !empty($media);
    $hasType = !empty($type) && $type !== 'text';
    
    if ($hasContent) $withContent++;
    if (!$hasContent && !$hasMedia) $withoutContent++;
    if ($hasMedia || $hasType) $withMedia++;
    
    echo "Mensagem #{$eventNum}:\n";
    echo "  ID: {$event['event_id']}\n";
    echo "  Tipo: {$event['event_type']}\n";
    echo "  Created: {$event['created_at']}\n";
    echo "  Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "  From: " . ($event['event_from'] ?: 'NULL') . "\n";
    echo "  To: " . ($event['event_to'] ?: 'NULL') . "\n";
    echo "  Type: " . ($type ?: 'text') . "\n";
    
    if ($hasContent) {
        $preview = mb_substr($content, 0, 100);
        echo "  ✅ TEM CONTEÚDO: " . ($preview ?: '[vazio mas tem campo]') . "\n";
    } else {
        echo "  ❌ SEM CONTEÚDO\n";
    }
    
    if ($hasMedia) {
        echo "  ✅ TEM MÍDIA: {$media['media_type']} - " . ($media['stored_path'] ?: 'sem path') . "\n";
    } else if ($hasType) {
        echo "  ⚠️  TIPO DE MÍDIA (sem arquivo): {$type}\n";
    } else {
        echo "  ❌ SEM MÍDIA\n";
    }
    
    // Verifica se seria filtrada no frontend (linha 1967 do index.php)
    $wouldBeFiltered = !$hasContent && !$hasMedia && !$hasType;
    if ($wouldBeFiltered) {
        echo "  ⚠️  SERIA FILTRADA NO FRONTEND (sem conteúdo nem mídia)\n";
    } else {
        echo "  ✅ SERIA EXIBIDA NO FRONTEND\n";
    }
    
    echo "\n";
}

echo "\n=== RESUMO ===\n";
echo "Total de eventos: " . count($allEvents) . "\n";
echo "Com conteúdo: {$withContent}\n";
echo "Com mídia: {$withMedia}\n";
echo "Sem conteúdo nem mídia: {$withoutContent}\n";
echo "Seriam exibidas: " . ($withContent + $withMedia) . "\n";
echo "Seriam filtradas: {$withoutContent}\n";

