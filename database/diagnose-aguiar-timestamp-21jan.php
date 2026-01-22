<?php
/**
 * Script para diagnosticar a conversa do Aguiar em 21/01 às 14:18
 * e entender por que aparece no sistema mas não no histórico do WhatsApp
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== DIAGNÓSTICO: CONVERSA DO AGUIAR 21/01 14:18 ===\n\n";

// Busca conversa do Aguiar
$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_type,
        c.channel_id,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.last_message_at,
        c.last_message_direction,
        c.message_count,
        c.unread_count,
        c.created_at,
        c.updated_at,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.contact_name LIKE '%Aguiar%' 
       OR c.contact_external_id LIKE '%556993245042%'
    ORDER BY c.last_message_at DESC, c.created_at DESC
    LIMIT 5
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada com 'Aguiar' ou número 556993245042\n";
    exit(1);
}

echo "✅ Encontradas " . count($conversations) . " conversa(s):\n\n";

foreach ($conversations as $conv) {
    echo "--- CONVERSA ID: {$conv['id']} ---\n";
    echo "   Tenant: " . ($conv['tenant_name'] ?: 'NULL') . " (ID: " . ($conv['tenant_id'] ?: 'NULL') . ")\n";
    echo "   Contato: {$conv['contact_name']} ({$conv['contact_external_id']})\n";
    echo "   Canal: {$conv['channel_id']}\n";
    echo "   Última mensagem: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
    echo "   Direção: " . ($conv['last_message_direction'] ?: 'NULL') . "\n";
    echo "   Total de mensagens: {$conv['message_count']}\n";
    echo "   Criada em: {$conv['created_at']}\n";
    echo "   Atualizada em: {$conv['updated_at']}\n";
    
    // Verifica se last_message_at está próximo de 21/01 14:18
    $targetDate = '2025-01-21 14:18';
    if ($conv['last_message_at']) {
        $lastMsgTime = strtotime($conv['last_message_at']);
        $targetTime = strtotime($targetDate);
        $diffMinutes = abs($lastMsgTime - $targetTime) / 60;
        
        if ($diffMinutes < 60) {
            echo "   ⚠️  ATENÇÃO: last_message_at está próximo de 21/01 14:18 (diferença: " . round($diffMinutes, 1) . " minutos)\n";
        }
    }
    
    echo "\n";
    
    // Busca eventos de comunicação relacionados a esta conversa
    echo "   --- EVENTOS DE COMUNICAÇÃO ---\n";
    
    // Tenta encontrar eventos pelo contact_external_id e channel_id
    $eventStmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as event_to,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as message_text,
            JSON_EXTRACT(ce.payload, '$.message.timestamp') as message_timestamp,
            JSON_EXTRACT(ce.payload, '$.timestamp') as payload_timestamp,
            JSON_EXTRACT(ce.payload, '$.raw.payload.t') as raw_timestamp,
            JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id,
            JSON_EXTRACT(ce.payload, '$.session.id') as session_id,
            ce.payload
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
        ORDER BY ce.created_at DESC
        LIMIT 20
    ");
    
    $contactPattern = "%{$conv['contact_external_id']}%";
    $channelId = $conv['channel_id'] ?? 'pixel12digital';
    $eventStmt->execute([$contactPattern, $contactPattern, $contactPattern, $contactPattern, $channelId, $channelId, $channelId]);
    $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "   ❌ Nenhum evento encontrado para esta conversa\n";
    } else {
        echo "   ✅ Encontrados " . count($events) . " evento(s):\n\n";
        
        foreach ($events as $event) {
            echo "   Evento ID: {$event['event_id']}\n";
            echo "   Tipo: {$event['event_type']}\n";
            echo "   Criado em: {$event['created_at']}\n";
            echo "   Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
            echo "   From: " . ($event['event_from'] ?: 'NULL') . "\n";
            echo "   To: " . ($event['event_to'] ?: 'NULL') . "\n";
            
            // Extrai timestamps do payload
            $messageTs = $event['message_timestamp'] ? trim($event['message_timestamp'], '"') : null;
            $payloadTs = $event['payload_timestamp'] ? trim($event['payload_timestamp'], '"') : null;
            $rawTs = $event['raw_timestamp'] ? trim($event['raw_timestamp'], '"') : null;
            
            echo "   Timestamps no payload:\n";
            if ($messageTs) {
                $ts = is_numeric($messageTs) ? (int)$messageTs : null;
                if ($ts && $ts > 10000000000) $ts = $ts / 1000; // Converte milissegundos
                if ($ts) {
                    $dt = date('Y-m-d H:i:s', $ts);
                    echo "      - message.timestamp: {$messageTs} -> {$dt}\n";
                } else {
                    echo "      - message.timestamp: {$messageTs} (não numérico)\n";
                }
            }
            if ($payloadTs) {
                $ts = is_numeric($payloadTs) ? (int)$payloadTs : null;
                if ($ts && $ts > 10000000000) $ts = $ts / 1000;
                if ($ts) {
                    $dt = date('Y-m-d H:i:s', $ts);
                    echo "      - payload.timestamp: {$payloadTs} -> {$dt}\n";
                } else {
                    echo "      - payload.timestamp: {$payloadTs} (não numérico)\n";
                }
            }
            if ($rawTs) {
                $ts = is_numeric($rawTs) ? (int)$rawTs : null;
                if ($ts && $ts > 10000000000) $ts = $ts / 1000;
                if ($ts) {
                    $dt = date('Y-m-d H:i:s', $ts);
                    echo "      - raw.payload.t: {$rawTs} -> {$dt}\n";
                } else {
                    echo "      - raw.payload.t: {$rawTs} (não numérico)\n";
                }
            }
            
            // Verifica se algum timestamp corresponde a 21/01 14:18
            $targetTime = strtotime('2025-01-21 14:18:00');
            $foundMatch = false;
            
            if ($messageTs && is_numeric($messageTs)) {
                $ts = (int)$messageTs;
                if ($ts > 10000000000) $ts = $ts / 1000;
                $diff = abs($ts - $targetTime);
                if ($diff < 3600) { // Dentro de 1 hora
                    echo "      ⚠️  message.timestamp está próximo de 21/01 14:18 (diferença: " . round($diff / 60, 1) . " minutos)\n";
                    $foundMatch = true;
                }
            }
            
            if ($payloadTs && is_numeric($payloadTs)) {
                $ts = (int)$payloadTs;
                if ($ts > 10000000000) $ts = $ts / 1000;
                $diff = abs($ts - $targetTime);
                if ($diff < 3600) {
                    echo "      ⚠️  payload.timestamp está próximo de 21/01 14:18 (diferença: " . round($diff / 60, 1) . " minutos)\n";
                    $foundMatch = true;
                }
            }
            
            if ($rawTs && is_numeric($rawTs)) {
                $ts = (int)$rawTs;
                if ($ts > 10000000000) $ts = $ts / 1000;
                $diff = abs($ts - $targetTime);
                if ($diff < 3600) {
                    echo "      ⚠️  raw.payload.t está próximo de 21/01 14:18 (diferença: " . round($diff / 60, 1) . " minutos)\n";
                    $foundMatch = true;
                }
            }
            
            // Verifica se o evento foi criado próximo de 21/01 14:18
            $eventCreatedTime = strtotime($event['created_at']);
            $diff = abs($eventCreatedTime - $targetTime);
            if ($diff < 3600) {
                echo "      ⚠️  Evento criado próximo de 21/01 14:18 (diferença: " . round($diff / 60, 1) . " minutos)\n";
            }
            
            // Mostra conteúdo da mensagem
            $content = $event['text'] ?: $event['body'] ?: $event['message_text'] ?: '[sem conteúdo]';
            $preview = mb_strlen($content) > 100 ? mb_substr($content, 0, 100) . '...' : $content;
            echo "   Conteúdo: {$preview}\n";
            
            echo "\n";
        }
    }
    
    echo "\n";
}

echo "\n=== ANÁLISE ===\n";
echo "Possíveis causas da discrepância:\n";
echo "1. O timestamp extraído do webhook pode estar incorreto ou em timezone diferente\n";
echo "2. A mensagem pode ter sido processada tardiamente (webhook chegou depois)\n";
echo "3. A mensagem pode ter sido reenviada ou reprocessada\n";
echo "4. Pode haver um problema de sincronização entre o sistema e o WhatsApp\n";
echo "5. O last_message_at pode ter sido atualizado por uma mensagem que não existe mais no WhatsApp\n";

