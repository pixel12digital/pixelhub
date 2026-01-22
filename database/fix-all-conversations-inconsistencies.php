<?php
/**
 * Script para corrigir todas as incoerências encontradas nas conversas
 * Corrige: timestamp, tenant_id, message_count
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORREÇÃO AUTOMÁTICA DE INCOERÊNCIAS ===\n\n";

// Busca todas as conversas recentes (últimos 30 dias)
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_name,
        c.contact_external_id,
        c.tenant_id,
        c.channel_id,
        c.last_message_at,
        c.last_message_direction,
        c.message_count,
        c.created_at
    FROM conversations c
    WHERE c.last_message_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       OR c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY c.last_message_at DESC
    LIMIT 100
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 Processando " . count($conversations) . " conversa(s)...\n\n";

$fixed = 0;
$skipped = 0;
$errors = 0;

$db->beginTransaction();

try {
    foreach ($conversations as $conv) {
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
            $skipped++;
            continue;
        }
        
        // Analisa eventos
        $latestTimestamp = null;
        $latestDirection = null;
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
                    $latestDirection = strpos($event['event_type'], 'inbound') !== false ? 'inbound' : 'outbound';
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
        
        // Prepara correções
        $updates = [];
        $params = [];
        $needsUpdate = false;
        
        // Corrige timestamp
        if ($latestTimestamp && $conv['last_message_at'] != $latestTimestamp) {
            $diff = abs(strtotime($conv['last_message_at']) - strtotime($latestTimestamp));
            if ($diff > 60) { // Só corrige se diferença maior que 1 minuto
                $updates[] = "last_message_at = ?";
                $params[] = $latestTimestamp;
                $needsUpdate = true;
            }
        }
        
        // Corrige tenant_id
        $mostCommonTenantId = null;
        if (!empty($tenantIds)) {
            $mostCommonTenantId = array_search(max($tenantIds), $tenantIds);
            
            if ($conv['tenant_id'] != $mostCommonTenantId) {
                $updates[] = "tenant_id = ?";
                $params[] = $mostCommonTenantId;
                $updates[] = "is_incoming_lead = 0";
                $needsUpdate = true;
            }
        }
        
        // Corrige message_count
        if ($conv['message_count'] != $eventsWithContent) {
            $updates[] = "message_count = ?";
            $params[] = $eventsWithContent;
            $needsUpdate = true;
        }
        
        // Corrige last_message_direction
        if ($latestDirection && $conv['last_message_direction'] != $latestDirection) {
            $updates[] = "last_message_direction = ?";
            $params[] = $latestDirection;
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $updates[] = "updated_at = NOW()";
            $params[] = $conv['id'];
            
            $sql = "UPDATE conversations SET " . implode(', ', $updates) . " WHERE id = ?";
            $updateStmt = $db->prepare($sql);
            $updateStmt->execute($params);
            
            $fixed++;
            echo "✅ Corrigida conversa ID {$conv['id']} - {$conv['contact_name']}\n";
        } else {
            $skipped++;
        }
    }
    
    $db->commit();
    
    echo "\n=== RESULTADO ===\n";
    echo "✅ Corrigidas: {$fixed}\n";
    echo "⏭️  Ignoradas (sem problemas): {$skipped}\n";
    echo "❌ Erros: {$errors}\n";
    echo "\n✅ Processo concluído com sucesso!\n";
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Rollback executado. Nenhuma alteração foi aplicada.\n";
    exit(1);
}

