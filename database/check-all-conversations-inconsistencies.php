<?php
/**
 * Script para verificar incoerências em todas as conversas
 * Verifica: timestamp, tenant_id, message_count
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE INCOERÊNCIAS EM TODAS AS CONVERSAS ===\n\n";

// Busca todas as conversas recentes (últimos 30 dias)
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_name,
        c.contact_external_id,
        c.tenant_id,
        c.channel_id,
        c.last_message_at,
        c.message_count,
        c.created_at
    FROM conversations c
    WHERE c.last_message_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       OR c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY c.last_message_at DESC
    LIMIT 50
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 Verificando " . count($conversations) . " conversa(s) recente(s)...\n\n";

$inconsistencies = [];

foreach ($conversations as $conv) {
    $problems = [];
    
    // Busca eventos relacionados
    $eventStmt = $db->prepare("
        SELECT 
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            JSON_EXTRACT(ce.payload, '$.message.timestamp') as message_timestamp,
            JSON_EXTRACT(ce.payload, '$.timestamp') as payload_timestamp,
            JSON_EXTRACT(ce.payload, '$.raw.payload.t') as raw_timestamp,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text
        FROM communication_events ce
        WHERE (
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
            OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
        )
        AND (
            JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
            OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
            OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
        )
        ORDER BY ce.created_at ASC
    ");
    
    $contactPattern = "%{$conv['contact_external_id']}%";
    $channelId = $conv['channel_id'] ?? '';
    $eventStmt->execute([$contactPattern, $contactPattern, $contactPattern, $contactPattern, $channelId, $channelId, $channelId]);
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        continue; // Pula conversas sem eventos
    }
    
    // Analisa eventos
    $latestTimestamp = null;
    $tenantIds = [];
    $eventsWithContent = 0;
    
    foreach ($events as $event) {
        // Extrai timestamp
        $messageTs = $event['message_timestamp'] ? trim($event['message_timestamp'], '"') : null;
        $payloadTs = $event['payload_timestamp'] ? trim($event['payload_timestamp'], '"') : null;
        $rawTs = $event['raw_timestamp'] ? trim($event['raw_timestamp'], '"') : null;
        
        $timestamp = $messageTs ?: $payloadTs ?: $rawTs;
        
        if ($timestamp && is_numeric($timestamp)) {
            $tsInt = (int)$timestamp;
            if ($tsInt > 10000000000) {
                $tsInt = $tsInt / 1000;
            }
            
            date_default_timezone_set('UTC');
            $dt = date('Y-m-d H:i:s', $tsInt);
            date_default_timezone_set('America/Sao_Paulo');
            
            if (!$latestTimestamp || $dt > $latestTimestamp) {
                $latestTimestamp = $dt;
            }
        }
        
        // Verifica tenant_id
        if ($event['tenant_id']) {
            $tenantIds[$event['tenant_id']] = ($tenantIds[$event['tenant_id']] ?? 0) + 1;
        }
        
        // Verifica conteúdo
        $content = $event['text'] ?: $event['body'] ?: $event['message_text'] ?: '';
        $hasMedia = false;
        
        $mediaStmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
        $mediaStmt->execute([$event['event_id']]);
        $hasMedia = $mediaStmt->fetch() !== false;
        
        if (!empty(trim($content)) || $hasMedia) {
            $eventsWithContent++;
        }
    }
    
    // Verifica problemas
    if ($latestTimestamp && $conv['last_message_at'] != $latestTimestamp) {
        $diff = abs(strtotime($conv['last_message_at']) - strtotime($latestTimestamp));
        if ($diff > 60) { // Só reporta se diferença maior que 1 minuto
            $problems[] = [
                'type' => 'timestamp',
                'current' => $conv['last_message_at'],
                'correct' => $latestTimestamp,
                'diff' => $diff
            ];
        }
    }
    
    $mostCommonTenantId = null;
    if (!empty($tenantIds)) {
        $mostCommonTenantId = array_search(max($tenantIds), $tenantIds);
        
        if ($conv['tenant_id'] != $mostCommonTenantId) {
            $problems[] = [
                'type' => 'tenant_id',
                'current' => $conv['tenant_id'],
                'correct' => $mostCommonTenantId,
                'count' => $tenantIds[$mostCommonTenantId]
            ];
        }
    }
    
    if ($conv['message_count'] != $eventsWithContent) {
        $problems[] = [
            'type' => 'message_count',
            'current' => $conv['message_count'],
            'correct' => $eventsWithContent
        ];
    }
    
    if (!empty($problems)) {
        $inconsistencies[] = [
            'conversation' => $conv,
            'problems' => $problems
        ];
    }
}

echo "=== RESULTADOS ===\n\n";

if (empty($inconsistencies)) {
    echo "✅ Nenhuma incoerência encontrada!\n";
} else {
    echo "⚠️  Encontradas " . count($inconsistencies) . " conversa(s) com incoerências:\n\n";
    
    foreach ($inconsistencies as $item) {
        $conv = $item['conversation'];
        echo "📋 CONVERSA ID: {$conv['id']} - {$conv['contact_name']}\n";
        echo "   Contato: {$conv['contact_external_id']}\n";
        
        foreach ($item['problems'] as $problem) {
            switch ($problem['type']) {
                case 'timestamp':
                    echo "   ❌ Timestamp: {$problem['current']} -> {$problem['correct']} (dif: " . round($problem['diff'] / 60, 1) . " min)\n";
                    break;
                case 'tenant_id':
                    echo "   ❌ Tenant ID: " . ($problem['current'] ?: 'NULL') . " -> {$problem['correct']} (em {$problem['count']} eventos)\n";
                    break;
                case 'message_count':
                    echo "   ❌ Message Count: {$problem['current']} -> {$problem['correct']}\n";
                    break;
            }
        }
        echo "\n";
    }
    
    echo "\n💡 Execute o script database/investigate-fix-aguiar-conversation.php para corrigir conversas específicas\n";
    echo "   Ou crie um script similar para corrigir todas automaticamente\n";
}

