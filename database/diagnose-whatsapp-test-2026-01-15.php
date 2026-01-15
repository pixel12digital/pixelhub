<?php
/**
 * Script de Diagnóstico: Mensagem WhatsApp 07:27 SP (2026-01-15)
 * 
 * Executa as queries de diagnóstico do documento de auditoria para identificar
 * onde a cadeia de processamento quebrou.
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../');
$db = DB::getConnection();

echo "=== DIAGNÓSTICO: Mensagem WhatsApp 07:27 SP (2026-01-15) ===\n\n";

// Horário do teste: 07:27 SP (UTC-3) = 10:27 UTC
// Vamos buscar uma janela maior: 10:20 UTC até 10:35 UTC
$testStart = '2026-01-15 10:20:00';
$testEnd = '2026-01-15 10:35:00';
$phonePattern = '%554796164699%';

echo "📅 Janela de busca: {$testStart} até {$testEnd} (UTC)\n";
echo "📱 Número de teste: 554796164699\n\n";

// ============================================
// PASSO 1: Verificar se evento foi ingerido (communication_events)
// ============================================
echo "--- PASSO 1: Verificando comunicação_events ---\n";

$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_raw,
        JSON_EXTRACT(payload, '$.message.from') as from_message,
        JSON_EXTRACT(payload, '$.text') as message_text,
        JSON_EXTRACT(payload, '$.body') as message_body,
        JSON_EXTRACT(payload, '$.message.text') as message_text_nested,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id_metadata
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= ?
    AND created_at <= ?
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(payload, '$.data.from') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 10
");

$stmt->execute([$testStart, $testEnd, $phonePattern, $phonePattern, $phonePattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ NENHUM EVENTO ENCONTRADO na janela de tempo.\n";
    echo "   Isso indica que:\n";
    echo "   - Webhook não chegou ao Hub, OU\n";
    echo "   - Webhook chegou mas falhou antes do INSERT, OU\n";
    echo "   - Número não bate (normalização diferente)\n\n";
    
    // Busca mais ampla: qualquer evento inbound na janela
    echo "🔍 Buscando QUALQUER evento whatsapp.inbound.message na janela...\n";
    $stmt2 = $db->prepare("
        SELECT 
            id,
            event_id,
            event_type,
            created_at,
            JSON_EXTRACT(payload, '$.from') as from_raw,
            JSON_EXTRACT(payload, '$.message.from') as from_message
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND created_at >= ?
        AND created_at <= ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt2->execute([$testStart, $testEnd]);
    $anyEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($anyEvents)) {
        echo "❌ NENHUM evento whatsapp.inbound.message na janela inteira.\n";
        echo "   CONCLUSÃO: Webhook provavelmente NÃO chegou ao Hub.\n";
        echo "   PRÓXIMO PASSO: Verificar logs do servidor web (Apache/Nginx access.log)\n\n";
    } else {
        echo "⚠️  Encontrados " . count($anyEvents) . " eventos, mas nenhum com o número de teste:\n";
        foreach ($anyEvents as $evt) {
            echo "   - Event ID: {$evt['event_id']}, From: {$evt['from_raw']} ou {$evt['from_message']}, Created: {$evt['created_at']}\n";
        }
        echo "   CONCLUSÃO: Webhook chegou, mas número não bate (problema de normalização)\n\n";
    }
} else {
    echo "✅ ENCONTRADOS " . count($events) . " evento(s):\n\n";
    foreach ($events as $event) {
        echo "   Event ID: {$event['event_id']}\n";
        echo "   Status: {$event['status']}\n";
        echo "   Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "   Created: {$event['created_at']}\n";
        echo "   From (raw): {$event['from_raw']}\n";
        echo "   From (message): {$event['from_message']}\n";
        echo "   Message: " . ($event['message_text'] ?: $event['message_body'] ?: $event['message_text_nested'] ?: 'N/A') . "\n";
        echo "   Channel ID (metadata): " . ($event['channel_id_metadata'] ?: 'NULL') . "\n";
        echo "\n";
    }
    
    $eventId = $events[0]['event_id'];
    $eventCreatedAt = $events[0]['created_at'];
    
    // ============================================
    // PASSO 2: Verificar se conversa foi criada/atualizada
    // ============================================
    echo "--- PASSO 2: Verificando conversations ---\n";
    
    // Tenta encontrar conversa pelo número normalizado (várias variações)
    $stmt2 = $db->prepare("
        SELECT 
            id,
            conversation_key,
            channel_type,
            contact_external_id,
            contact_name,
            tenant_id,
            status,
            last_message_at,
            last_message_direction,
            message_count,
            unread_count,
            created_at,
            updated_at
        FROM conversations
        WHERE channel_type = 'whatsapp'
        AND (
            contact_external_id LIKE ?
            OR contact_external_id LIKE '%554796164699%'
            OR contact_external_id LIKE '%554796164699%'  -- Com 9º dígito
        )
        ORDER BY last_message_at DESC, updated_at DESC
        LIMIT 10
    ");
    
    $stmt2->execute([$phonePattern]);
    $conversations = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "❌ NENHUMA CONVERSA ENCONTRADA para este número.\n";
        echo "   CONCLUSÃO: Evento foi ingerido, mas ConversationService::resolveConversation() falhou.\n";
        echo "   POSSÍVEIS CAUSAS:\n";
        echo "   - extractChannelInfo() retornou NULL\n";
        echo "   - Normalização de número falhou\n";
        echo "   - Erro silencioso na criação de conversa\n\n";
        
        // Verifica se há conversas recentes sem tenant (pode ser a conversa)
        echo "🔍 Buscando conversas WhatsApp criadas/atualizadas na janela...\n";
        $stmt3 = $db->prepare("
            SELECT 
                id,
                conversation_key,
                contact_external_id,
                tenant_id,
                last_message_at,
                created_at
            FROM conversations
            WHERE channel_type = 'whatsapp'
            AND (
                last_message_at >= ?
                OR created_at >= ?
            )
            ORDER BY last_message_at DESC, created_at DESC
            LIMIT 5
        ");
        $stmt3->execute([$testStart, $testStart]);
        $recentConvs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($recentConvs)) {
            echo "❌ Nenhuma conversa WhatsApp criada/atualizada na janela.\n";
        } else {
            echo "⚠️  Encontradas " . count($recentConvs) . " conversas recentes:\n";
            foreach ($recentConvs as $conv) {
                echo "   - ID: {$conv['id']}, Contact: {$conv['contact_external_id']}, Tenant: " . ($conv['tenant_id'] ?: 'NULL') . ", Last: {$conv['last_message_at']}\n";
            }
        }
    } else {
        echo "✅ ENCONTRADAS " . count($conversations) . " conversa(s):\n\n";
        foreach ($conversations as $conv) {
            echo "   Conversation ID: {$conv['id']}\n";
            echo "   Key: {$conv['conversation_key']}\n";
            echo "   Contact: {$conv['contact_external_id']}\n";
            echo "   Contact Name: " . ($conv['contact_name'] ?: 'NULL') . "\n";
            echo "   Tenant ID: " . ($conv['tenant_id'] ?: 'NULL (Sem tenant)') . "\n";
            echo "   Status: {$conv['status']}\n";
            echo "   Last Message At: {$conv['last_message_at']}\n";
            echo "   Last Direction: {$conv['last_message_direction']}\n";
            echo "   Message Count: {$conv['message_count']}\n";
            echo "   Unread Count: {$conv['unread_count']}\n";
            echo "   Created: {$conv['created_at']}\n";
            echo "   Updated: {$conv['updated_at']}\n";
            echo "\n";
            
            // Verifica se last_message_at está próximo do evento
            $eventTime = strtotime($eventCreatedAt);
            $convTime = strtotime($conv['last_message_at']);
            $diff = abs($eventTime - $convTime);
            
            if ($diff > 300) { // Mais de 5 minutos de diferença
                echo "   ⚠️  ATENÇÃO: Diferença de " . round($diff / 60) . " minutos entre evento e last_message_at\n";
            }
        }
        
        // ============================================
        // PASSO 3: Verificar se conversa aparece na query da UI
        // ============================================
        echo "--- PASSO 3: Verificando se conversa aparece na query da UI ---\n";
        
        $conversationId = $conversations[0]['id'];
        $conversationStatus = $conversations[0]['status'];
        $conversationTenantId = $conversations[0]['tenant_id'];
        
        // Simula query do CommunicationHubController::getWhatsAppThreadsFromConversations
        $stmt3 = $db->prepare("
            SELECT 
                c.id,
                c.conversation_key,
                c.contact_external_id,
                c.tenant_id,
                c.status,
                c.last_message_at,
                c.unread_count,
                COALESCE(t.name, 'Sem tenant') as tenant_name
            FROM conversations c
            LEFT JOIN tenants t ON c.tenant_id = t.id
            WHERE c.channel_type = 'whatsapp'
            AND c.status NOT IN ('closed', 'archived')
            AND c.id = ?
        ");
        $stmt3->execute([$conversationId]);
        $uiResult = $stmt3->fetch(PDO::FETCH_ASSOC);
        
        if ($uiResult) {
            echo "✅ Conversa APARECE na query da UI (filtro: status != 'closed'/'archived'):\n";
            echo "   ID: {$uiResult['id']}\n";
            echo "   Status: {$uiResult['status']}\n";
            echo "   Tenant: {$uiResult['tenant_name']}\n";
            echo "   Last Message: {$uiResult['last_message_at']}\n";
            echo "\n";
            echo "   CONCLUSÃO: Conversa existe e deveria aparecer na UI.\n";
            echo "   SE NÃO APARECE: Problema está em:\n";
            echo "   - Filtros da UI (Canal, Status, Cliente)\n";
            echo "   - Polling não está detectando atualizações\n";
            echo "   - Cache do navegador\n";
        } else {
            echo "❌ Conversa NÃO aparece na query da UI.\n";
            echo "   Status da conversa: {$conversationStatus}\n";
            if ($conversationStatus === 'closed' || $conversationStatus === 'archived') {
                echo "   CAUSA: Status '{$conversationStatus}' é excluído pelo filtro 'status != closed/archived'\n";
            }
        }
    }
}

// ============================================
// EXTRA: Verificar canais disponíveis
// ============================================
echo "\n--- EXTRA: Verificando canais WhatsApp configurados ---\n";

$stmt4 = $db->prepare("
    SELECT 
        id,
        tenant_id,
        provider,
        channel_id,
        is_enabled,
        created_at
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    ORDER BY is_enabled DESC, created_at DESC
");

$stmt4->execute();
$channels = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "❌ NENHUM CANAL configurado.\n";
} else {
    echo "✅ Encontrados " . count($channels) . " canal(is):\n";
    foreach ($channels as $ch) {
        $enabled = $ch['is_enabled'] ? '✅ HABILITADO' : '❌ DESABILITADO';
        echo "   - ID: {$ch['id']}, Channel ID: {$ch['channel_id']}, Tenant: " . ($ch['tenant_id'] ?: 'NULL (compartilhado)') . ", {$enabled}\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";

