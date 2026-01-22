<?php
/**
 * Script para investigar e corrigir incoerências na conversa do Aguiar
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO E CORREÇÃO: CONVERSA DO AGUIAR ===\n\n";

// 1. Busca conversa do Aguiar
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
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "❌ Nenhuma conversa encontrada\n";
    exit(1);
}

$conversation = $conversations[0];
echo "📋 CONVERSA ENCONTRADA:\n";
echo "   ID: {$conversation['id']}\n";
echo "   Nome: {$conversation['contact_name']}\n";
echo "   Contato: {$conversation['contact_external_id']}\n";
echo "   Tenant: " . ($conversation['tenant_name'] ?: 'NULL') . " (ID: " . ($conversation['tenant_id'] ?: 'NULL') . ")\n";
echo "   Canal: {$conversation['channel_id']}\n";
echo "   Última mensagem: {$conversation['last_message_at']}\n";
echo "   Direção: {$conversation['last_message_direction']}\n";
echo "   Total de mensagens: {$conversation['message_count']}\n";
echo "   Não lidas: {$conversation['unread_count']}\n";
echo "\n";

// 2. Busca TODOS os eventos relacionados
echo "=== BUSCANDO EVENTOS RELACIONADOS ===\n";
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
    ORDER BY ce.created_at ASC
");
$contactPattern = "%{$conversation['contact_external_id']}%";
$channelId = $conversation['channel_id'] ?? 'pixel12digital';
$eventStmt->execute([$contactPattern, $contactPattern, $contactPattern, $contactPattern, $channelId, $channelId, $channelId]);
$events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

echo "✅ Encontrados " . count($events) . " evento(s)\n\n";

if (empty($events)) {
    echo "⚠️  Nenhum evento encontrado - conversa pode estar órfã\n";
    exit(1);
}

// 3. Analisa cada evento e identifica problemas
echo "=== ANÁLISE DE INCOERÊNCIAS ===\n\n";

$problems = [];
$latestEvent = null;
$latestTimestamp = null;
$tenantIds = [];
$hasContentCount = 0;

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
        
        // Converte para UTC
        date_default_timezone_set('UTC');
        $dt = date('Y-m-d H:i:s', $tsInt);
        date_default_timezone_set('America/Sao_Paulo'); // Restaura
        
        if (!$latestTimestamp || $dt > $latestTimestamp) {
            $latestTimestamp = $dt;
            $latestEvent = $event;
        }
    }
    
    // Verifica tenant_id
    if ($event['tenant_id']) {
        $tenantIds[$event['tenant_id']] = ($tenantIds[$event['tenant_id']] ?? 0) + 1;
    }
    
    // Verifica conteúdo
    $content = $event['text'] ?: $event['body'] ?: $event['message_text'] ?: '';
    if (!empty(trim($content))) {
        $hasContentCount++;
    }
}

echo "📊 ESTATÍSTICAS:\n";
echo "   Total de eventos: " . count($events) . "\n";
echo "   Eventos com conteúdo: {$hasContentCount}\n";
echo "   Tenant IDs encontrados: " . implode(', ', array_keys($tenantIds)) . "\n";
echo "   Último timestamp calculado: " . ($latestTimestamp ?: 'N/A') . "\n";
echo "\n";

// 4. Identifica problemas
echo "=== PROBLEMAS IDENTIFICADOS ===\n\n";

// Problema 1: Timestamp incorreto
if ($latestTimestamp && $conversation['last_message_at'] != $latestTimestamp) {
    $problems[] = [
        'type' => 'timestamp_mismatch',
        'current' => $conversation['last_message_at'],
        'correct' => $latestTimestamp,
        'description' => 'last_message_at não corresponde ao timestamp mais recente dos eventos'
    ];
    echo "❌ PROBLEMA 1: Timestamp incorreto\n";
    echo "   Atual: {$conversation['last_message_at']}\n";
    echo "   Correto: {$latestTimestamp}\n";
    echo "   Diferença: " . abs(strtotime($conversation['last_message_at']) - strtotime($latestTimestamp)) . " segundos\n\n";
}

// Problema 2: Tenant ID inconsistente
$mostCommonTenantId = null;
if (!empty($tenantIds)) {
    $mostCommonTenantId = array_search(max($tenantIds), $tenantIds);
    
    if ($conversation['tenant_id'] != $mostCommonTenantId) {
        $problems[] = [
            'type' => 'tenant_id_mismatch',
            'current' => $conversation['tenant_id'],
            'correct' => $mostCommonTenantId,
            'description' => 'tenant_id da conversa não corresponde ao tenant_id mais comum nos eventos'
        ];
        echo "❌ PROBLEMA 2: Tenant ID inconsistente\n";
        echo "   Atual: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
        echo "   Correto: {$mostCommonTenantId} (aparece em {$tenantIds[$mostCommonTenantId]} eventos)\n\n";
    }
}

// Problema 3: Contagem de mensagens
$eventsWithContent = 0;
foreach ($events as $event) {
    $content = $event['text'] ?: $event['body'] ?: $event['message_text'] ?: '';
    $hasMedia = false;
    
    // Verifica se tem mídia
    $mediaStmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
    $mediaStmt->execute([$event['event_id']]);
    $hasMedia = $mediaStmt->fetch() !== false;
    
    if (!empty(trim($content)) || $hasMedia) {
        $eventsWithContent++;
    }
}

if ($conversation['message_count'] != $eventsWithContent) {
    $problems[] = [
        'type' => 'message_count_mismatch',
        'current' => $conversation['message_count'],
        'correct' => $eventsWithContent,
        'description' => 'message_count não corresponde ao número real de eventos com conteúdo'
    ];
    echo "❌ PROBLEMA 3: Contagem de mensagens incorreta\n";
    echo "   Atual: {$conversation['message_count']}\n";
    echo "   Correto: {$eventsWithContent}\n\n";
}

if (empty($problems)) {
    echo "✅ Nenhum problema identificado!\n\n";
} else {
    echo "=== APLICANDO CORREÇÕES ===\n\n";
    
    $db->beginTransaction();
    
    try {
        $updates = [];
        $params = [];
        
        // Corrige timestamp
        if ($latestTimestamp && $conversation['last_message_at'] != $latestTimestamp) {
            $updates[] = "last_message_at = ?";
            $params[] = $latestTimestamp;
            echo "✅ Corrigindo last_message_at para: {$latestTimestamp}\n";
        }
        
        // Corrige tenant_id
        if ($mostCommonTenantId && $conversation['tenant_id'] != $mostCommonTenantId) {
            $updates[] = "tenant_id = ?";
            $params[] = $mostCommonTenantId;
            $updates[] = "is_incoming_lead = 0";
            echo "✅ Corrigindo tenant_id para: {$mostCommonTenantId}\n";
        }
        
        // Corrige message_count
        if ($conversation['message_count'] != $eventsWithContent) {
            $updates[] = "message_count = ?";
            $params[] = $eventsWithContent;
            echo "✅ Corrigindo message_count para: {$eventsWithContent}\n";
        }
        
        // Atualiza last_message_direction se necessário
        if ($latestEvent) {
            $direction = strpos($latestEvent['event_type'], 'inbound') !== false ? 'inbound' : 'outbound';
            if ($conversation['last_message_direction'] != $direction) {
                $updates[] = "last_message_direction = ?";
                $params[] = $direction;
                echo "✅ Corrigindo last_message_direction para: {$direction}\n";
            }
        }
        
        // Atualiza updated_at
        $updates[] = "updated_at = NOW()";
        
        if (!empty($updates)) {
            $params[] = $conversation['id'];
            $sql = "UPDATE conversations SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            echo "\n✅ Correções aplicadas com sucesso!\n";
            $db->commit();
        } else {
            echo "\n⚠️  Nenhuma correção necessária\n";
            $db->rollBack();
        }
        
    } catch (\Exception $e) {
        $db->rollBack();
        echo "\n❌ ERRO ao aplicar correções: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n=== VERIFICAÇÃO PÓS-CORREÇÃO ===\n";
$stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->execute([$conversation['id']]);
$updated = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📋 CONVERSA APÓS CORREÇÃO:\n";
echo "   ID: {$updated['id']}\n";
echo "   Tenant ID: " . ($updated['tenant_id'] ?: 'NULL') . "\n";
echo "   Última mensagem: {$updated['last_message_at']}\n";
echo "   Direção: {$updated['last_message_direction']}\n";
echo "   Total de mensagens: {$updated['message_count']}\n";
echo "   Não lidas: {$updated['unread_count']}\n";
echo "   Atualizada em: {$updated['updated_at']}\n";

echo "\n✅ Processo concluído!\n";

