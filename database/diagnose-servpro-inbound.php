<?php
/**
 * Diagnóstico Cirúrgico: Mensagem ServPro não sobe pro topo
 * 
 * Este script verifica:
 * 1. Se o evento entrou em communication_events
 * 2. Se foi classificado como inbound
 * 3. Qual conversa foi atualizada
 * 4. Se o banco mudou corretamente
 * 5. Se o endpoint de updates devolveu
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
Env::load(__DIR__ . '/../');

$db = DB::getConnection();

echo "========================================\n";
echo "DIAGNÓSTICO: Mensagem ServPro Inbound\n";
echo "========================================\n\n";

// PASSO 1: Solicita dados do teste
echo "PASSO 1: Dados do Teste\n";
echo "------------------------\n";
echo "Por favor, envie uma mensagem do ServPro (554796474223) para o número da sessão 'Pixel12 Digital'\n";
echo "Anote o horário exato e o texto da mensagem.\n\n";

$testText = readline("Digite o texto exato da mensagem de teste (ou pressione Enter para buscar a última): ");
$testTime = readline("Digite o horário aproximado (YYYY-MM-DD HH:MM:SS) ou pressione Enter para buscar agora: ");

if (empty($testTime)) {
    $testTime = date('Y-m-d H:i:s');
}

echo "\n";

// PASSO 2: Verificar se entrou em communication_events
echo "PASSO 2: Verificando communication_events\n";
echo "------------------------------------------\n";

$where = ["ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"];
$params = [];

if (!empty($testText)) {
    $where[] = "ce.payload LIKE ?";
    $params[] = "%{$testText}%";
}

if (!empty($testTime)) {
    // Busca eventos nos últimos 10 minutos
    $where[] = "ce.created_at >= DATE_SUB(?, INTERVAL 10 MINUTE)";
    $params[] = $testTime;
}

$whereClause = "WHERE " . implode(" AND ", $where);

$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.source_system,
        ce.tenant_id,
        ce.created_at,
        ce.payload,
        ce.metadata,
        JSON_EXTRACT(ce.payload, '$.session.id') as session_id,
        JSON_EXTRACT(ce.payload, '$.session.session') as session_session,
        JSON_EXTRACT(ce.payload, '$.from') as payload_from,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.to') as payload_to,
        JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
        JSON_EXTRACT(ce.metadata, '$.channel_id') as metadata_channel_id
    FROM communication_events ce
    {$whereClause}
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ NENHUM EVENTO ENCONTRADO nos últimos 10 minutos.\n";
    echo "   Verifique se a mensagem foi enviada e se o webhook está funcionando.\n\n";
    exit(1);
}

echo "✅ Encontrados " . count($events) . " evento(s) recente(s).\n\n";

// Filtra eventos do ServPro (554796474223)
$servproEvents = [];
foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    $from = $payload['from'] ?? $payload['message']['from'] ?? null;
    
    // Remove sufixos @c.us, @lid, etc.
    if ($from) {
        $from = preg_replace('/@.*$/', '', $from);
    }
    
    // Verifica se é do ServPro
    if (strpos($from, '554796474223') !== false || strpos($from, '4796474223') !== false) {
        $servproEvents[] = $event;
    }
}

if (empty($servproEvents)) {
    echo "⚠️  Nenhum evento do ServPro (554796474223) encontrado.\n";
    echo "   Mostrando todos os eventos encontrados:\n\n";
    $servproEvents = $events;
}

// Analisa o primeiro evento do ServPro (ou mais recente)
$testEvent = $servproEvents[0];

echo "📋 EVENTO DE TESTE IDENTIFICADO:\n";
echo "   event_id: {$testEvent['event_id']}\n";
echo "   event_type: {$testEvent['event_type']}\n";
echo "   source_system: {$testEvent['source_system']}\n";
echo "   tenant_id: " . ($testEvent['tenant_id'] ?: 'NULL') . "\n";
echo "   created_at: {$testEvent['created_at']}\n";

// Extrai channel_id
$channelId = $testEvent['metadata_channel_id'] 
    ?? $testEvent['session_id'] 
    ?? $testEvent['session_session']
    ?? null;

if ($channelId) {
    $channelId = trim($channelId, '"');
}

echo "   channel_id/session.id: " . ($channelId ?: 'NULL') . "\n";

// Verifica classificação
$isInbound = ($testEvent['event_type'] === 'whatsapp.inbound.message');
echo "   ✅ Classificação: " . ($isInbound ? "INBOUND ✅" : "OUTBOUND ❌") . "\n";

if (!$isInbound) {
    echo "\n⚠️  PROBLEMA IDENTIFICADO: Evento classificado como OUTBOUND!\n";
    echo "   Isso explica por que unread_count não incrementou.\n";
}

echo "\n";

// PASSO 3: Verificar qual conversa foi atualizada
echo "PASSO 3: Verificando conversas atualizadas\n";
echo "--------------------------------------------\n";

// Busca conversa do ServPro
$servproContactId = '554796474223'; // Normalizado E.164

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.conversation_key,
        c.channel_type,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id,
        c.status,
        c.last_message_at,
        c.last_message_direction,
        c.message_count,
        c.unread_count,
        c.updated_at,
        c.created_at
    FROM conversations c
    WHERE c.contact_external_id = ?
    OR c.contact_external_id LIKE ?
    ORDER BY c.last_message_at DESC
    LIMIT 5
");

$stmt->execute([$servproContactId, $servproContactId . '%']);
$servproConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($servproConversations)) {
    echo "❌ NENHUMA CONVERSA ENCONTRADA para ServPro (554796474223).\n";
    echo "   Isso indica que ConversationService::resolveConversation() não criou/atualizou a conversa.\n\n";
} else {
    echo "✅ Encontrada(s) " . count($servproConversations) . " conversa(s) do ServPro:\n\n";
    
    foreach ($servproConversations as $conv) {
        echo "📋 CONVERSA ID: {$conv['id']}\n";
        echo "   conversation_key: {$conv['conversation_key']}\n";
        echo "   contact_external_id: {$conv['contact_external_id']}\n";
        echo "   tenant_id: " . ($conv['tenant_id'] ?: 'NULL') . "\n";
        echo "   last_message_at: {$conv['last_message_at']}\n";
        echo "   last_message_direction: " . ($conv['last_message_direction'] ?: 'NULL') . "\n";
        echo "   message_count: {$conv['message_count']}\n";
        echo "   unread_count: {$conv['unread_count']}\n";
        echo "   updated_at: {$conv['updated_at']}\n";
        
        // Verifica se foi atualizada recentemente (últimos 5 minutos)
        $updatedTime = strtotime($conv['updated_at']);
        $testTimeStamp = strtotime($testTime);
        $diffMinutes = ($testTimeStamp - $updatedTime) / 60;
        
        if (abs($diffMinutes) <= 5) {
            echo "   ✅ Atualizada recentemente (diferença: " . round($diffMinutes, 1) . " minutos)\n";
        } else {
            echo "   ⚠️  Última atualização há " . round(abs($diffMinutes), 1) . " minutos\n";
        }
        
        // Verifica se unread_count incrementou
        if ($isInbound && $conv['unread_count'] > 0) {
            echo "   ✅ unread_count > 0 (esperado para inbound)\n";
        } elseif ($isInbound && $conv['unread_count'] == 0) {
            echo "   ❌ unread_count = 0 (deveria ser > 0 para inbound)\n";
        }
        
        // Verifica se last_message_direction está correto
        if ($isInbound && $conv['last_message_direction'] === 'inbound') {
            echo "   ✅ last_message_direction = inbound (correto)\n";
        } elseif ($isInbound && $conv['last_message_direction'] !== 'inbound') {
            echo "   ❌ last_message_direction = {$conv['last_message_direction']} (deveria ser 'inbound')\n";
        }
        
        echo "\n";
    }
}

// Verifica conversa do Charles (para garantir que não foi ela que recebeu o update)
echo "🔍 Verificando conversa do Charles (554796164699) para garantir isolamento:\n";
$charlesContactId = '554796164699';

$stmt = $db->prepare("
    SELECT 
        c.id,
        c.contact_external_id,
        c.last_message_at,
        c.last_message_direction,
        c.unread_count,
        c.updated_at
    FROM conversations c
    WHERE c.contact_external_id = ?
    LIMIT 1
");

$stmt->execute([$charlesContactId]);
$charlesConv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($charlesConv) {
    $charlesUpdatedTime = strtotime($charlesConv['updated_at']);
    $testTimeStamp = strtotime($testTime);
    $diffMinutes = ($testTimeStamp - $charlesUpdatedTime) / 60;
    
    echo "   conversation_id: {$charlesConv['id']}\n";
    echo "   last_message_at: {$charlesConv['last_message_at']}\n";
    echo "   updated_at: {$charlesConv['updated_at']}\n";
    echo "   Diferença do teste: " . round(abs($diffMinutes), 1) . " minutos\n";
    
    if (abs($diffMinutes) <= 5) {
        echo "   ⚠️  ATENÇÃO: Conversa do Charles foi atualizada recentemente!\n";
        echo "      Isso pode indicar matching indevido (heurística do 9º dígito).\n";
    } else {
        echo "   ✅ Conversa do Charles não foi atualizada (isolamento OK)\n";
    }
} else {
    echo "   ℹ️  Conversa do Charles não encontrada (não é problema)\n";
}

echo "\n";

// PASSO 4: Validar heurística do 9º dígito
echo "PASSO 4: Validando heurística do 9º dígito\n";
echo "--------------------------------------------\n";

if (!empty($servproConversations)) {
    $servproConv = $servproConversations[0];
    
    // Verifica se há conversas "equivalentes" (variação do 9º dígito)
    // ServPro: 554796474223 (12 dígitos após 55)
    // Se houver conversa com 55479647423 (11 dígitos), pode ser match indevido
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.conversation_key,
            c.contact_external_id,
            c.last_message_at,
            c.updated_at
        FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        AND (
            c.contact_external_id LIKE '5547964742%'
            OR c.contact_external_id LIKE '554796474%'
        )
        AND c.id != ?
        ORDER BY c.updated_at DESC
        LIMIT 5
    ");
    
    $stmt->execute([$servproConv['id']]);
    $similarConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($similarConversations)) {
        echo "⚠️  Encontradas conversas similares (possível conflito de matching):\n\n";
        foreach ($similarConversations as $sim) {
            echo "   conversation_id: {$sim['id']}\n";
            echo "   contact_external_id: {$sim['contact_external_id']}\n";
            echo "   conversation_key: {$sim['conversation_key']}\n";
            echo "   updated_at: {$sim['updated_at']}\n";
            
            $simUpdatedTime = strtotime($sim['updated_at']);
            $testTimeStamp = strtotime($testTime);
            $diffMinutes = ($testTimeStamp - $simUpdatedTime) / 60;
            
            if (abs($diffMinutes) <= 5) {
                echo "   ⚠️  ATUALIZADA RECENTEMENTE (possível match indevido!)\n";
            }
            echo "\n";
        }
    } else {
        echo "✅ Nenhuma conversa similar encontrada (heurística OK)\n";
    }
} else {
    echo "⚠️  Não foi possível validar (conversa do ServPro não encontrada)\n";
}

echo "\n";

// PASSO 5: Testar endpoint de updates
echo "PASSO 5: Testando endpoint de updates\n";
echo "--------------------------------------\n";

// Simula chamada ao endpoint check-updates
$afterTimestamp = date('Y-m-d H:i:s', strtotime('-1 hour'));

$stmt = $db->prepare("
    SELECT MAX(GREATEST(COALESCE(c.updated_at, '1970-01-01'), COALESCE(c.last_message_at, '1970-01-01'))) as latest_update_ts
    FROM conversations c
    WHERE c.channel_type = 'whatsapp'
    AND (c.updated_at > ? OR c.last_message_at > ?)
    LIMIT 1
");

$stmt->execute([$afterTimestamp, $afterTimestamp]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$latestUpdateTs = $result['latest_update_ts'] ?? null;

if ($latestUpdateTs) {
    echo "✅ Endpoint retornaria: has_updates = true\n";
    echo "   latest_update_ts: {$latestUpdateTs}\n";
    
    // Verifica se a conversa do ServPro está incluída
    $stmt = $db->prepare("
        SELECT c.id, c.contact_external_id, c.last_message_at
        FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        AND c.contact_external_id LIKE ?
        AND (c.updated_at > ? OR c.last_message_at > ?)
        LIMIT 1
    ");
    
    $stmt->execute(["%4796474223%", $afterTimestamp, $afterTimestamp]);
    $servproInUpdate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($servproInUpdate) {
        echo "   ✅ Conversa do ServPro está incluída no resultado\n";
    } else {
        echo "   ⚠️  Conversa do ServPro NÃO está incluída no resultado\n";
        echo "      (pode ser problema de filtro ou timestamp)\n";
    }
} else {
    echo "❌ Endpoint retornaria: has_updates = false\n";
    echo "   Isso explica por que a UI não atualizou.\n";
}

echo "\n";

// RESUMO FINAL
echo "========================================\n";
echo "RESUMO DO DIAGNÓSTICO\n";
echo "========================================\n\n";

echo "📋 DADOS DO TESTE:\n";
echo "   event_id: {$testEvent['event_id']}\n";
echo "   event_type: {$testEvent['event_type']}\n";
echo "   channel_id: " . ($channelId ?: 'NULL') . "\n";
echo "   tenant_id: " . ($testEvent['tenant_id'] ?: 'NULL') . "\n";

if (!empty($servproConversations)) {
    $servproConv = $servproConversations[0];
    echo "   conversation_id atualizado: {$servproConv['id']}\n";
    echo "   last_message_at: {$servproConv['last_message_at']}\n";
    echo "   unread_count: {$servproConv['unread_count']}\n";
    echo "   last_message_direction: " . ($servproConv['last_message_direction'] ?: 'NULL') . "\n";
} else {
    echo "   conversation_id atualizado: NENHUMA\n";
}

if ($charlesConv) {
    $charlesUpdatedTime = strtotime($charlesConv['updated_at']);
    $testTimeStamp = strtotime($testTime);
    $diffMinutes = ($testTimeStamp - $charlesUpdatedTime) / 60;
    
    if (abs($diffMinutes) <= 5) {
        echo "   ⚠️  Outra conversa atualizada: Charles (ID: {$charlesConv['id']})\n";
    }
}

echo "   Endpoint updates: " . ($latestUpdateTs ? "has_updates = true" : "has_updates = false") . "\n";

echo "\n";

// CONCLUSÃO
echo "🎯 CONCLUSÃO:\n";

if (!$isInbound) {
    echo "   (A) CLASSIFICAÇÃO: ❌ Evento classificado como OUTBOUND\n";
    echo "      Correção: Ajustar WhatsAppWebhookController::mapEventType()\n";
} else {
    echo "   (A) CLASSIFICAÇÃO: ✅ OK\n";
}

if (empty($servproConversations) || (!empty($servproConversations) && $servproConversations[0]['unread_count'] == 0 && $isInbound)) {
    echo "   (B) MATCHING: ❌ Conversa não atualizada ou unread_count não incrementou\n";
    echo "      Correção: Verificar ConversationService::resolveConversation()\n";
} elseif (!empty($charlesConv)) {
    $charlesUpdatedTime = strtotime($charlesConv['updated_at']);
    $testTimeStamp = strtotime($testTime);
    $diffMinutes = ($testTimeStamp - $charlesUpdatedTime) / 60;
    
    if (abs($diffMinutes) <= 5) {
        echo "   (B) MATCHING: ❌ Conversa errada atualizada (Charles)\n";
        echo "      Correção: Ajustar heurística do 9º dígito em ConversationService\n";
    } else {
        echo "   (B) MATCHING: ✅ OK\n";
    }
} else {
    echo "   (B) MATCHING: ✅ OK\n";
}

if (!$latestUpdateTs || (!empty($servproConversations) && !$servproInUpdate)) {
    echo "   (C) POLLING: ❌ Endpoint não retorna atualização\n";
    echo "      Correção: Verificar filtros e timestamp em CommunicationHubController::checkUpdates()\n";
} else {
    echo "   (C) POLLING: ✅ OK\n";
}

echo "\n";

