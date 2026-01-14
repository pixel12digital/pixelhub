<?php
/**
 * Teste: Valida ordenação e last_message_at das conversas
 * 
 * Objetivo: Confirmar que:
 * 1. last_message_at está sendo atualizado corretamente quando inbound chega
 * 2. Ordenação por last_message_at DESC está correta
 * 3. Charles (whatsapp_34) tem last_message_at > ServPro quando Charles envia
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/DB.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== TESTE: Ordenação e last_message_at ===\n\n";

// 1. Busca conversas ordenadas por last_message_at DESC
echo "1. Buscando conversas ordenadas por last_message_at DESC:\n";
$stmt = $db->query("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        tenant_id,
        last_message_at,
        last_message_direction,
        unread_count,
        message_count,
        updated_at
    FROM conversations
    WHERE channel_type = 'whatsapp'
    ORDER BY last_message_at DESC, created_at DESC
    LIMIT 10
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ⚠️  Nenhuma conversa encontrada!\n\n";
    exit(1);
}

echo "   Encontradas " . count($conversations) . " conversas:\n";
foreach ($conversations as $i => $conv) {
    $threadId = "whatsapp_{$conv['id']}";
    $lastActivity = $conv['last_message_at'] ?: 'NULL';
    $unread = $conv['unread_count'] ?? 0;
    $direction = $conv['last_message_direction'] ?? 'N/A';
    echo sprintf(
        "   [%d] thread_id=%s, contact=%s, last_message_at=%s, direction=%s, unread_count=%d, message_count=%d\n",
        $i + 1,
        $threadId,
        $conv['contact_external_id'] ?? 'NULL',
        $lastActivity,
        $direction,
        $unread,
        $conv['message_count'] ?? 0
    );
}

// 2. Valida ordenação
echo "\n2. Validando ordenação:\n";
$isOrdered = true;
for ($i = 1; $i < count($conversations); $i++) {
    $prevTime = strtotime($conversations[$i-1]['last_message_at'] ?: '1970-01-01');
    $currTime = strtotime($conversations[$i]['last_message_at'] ?: '1970-01-01');
    
    if ($currTime > $prevTime) {
        $isOrdered = false;
        echo sprintf(
            "   ❌ ORDENAÇÃO QUEBRADA: conversa[%d].last_message_at=%s > conversa[%d].last_message_at=%s\n",
            $i,
            $conversations[$i]['last_message_at'],
            $i-1,
            $conversations[$i-1]['last_message_at']
        );
        break;
    }
}

if ($isOrdered) {
    echo "   ✅ Ordenação está correta (DESC)\n";
} else {
    echo "   ⚠️  Ordenação está incorreta!\n";
}

// 3. Busca conversa do Charles (whatsapp_34)
echo "\n3. Buscando conversa do Charles (whatsapp_34):\n";
$charlesStmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        tenant_id,
        last_message_at,
        last_message_direction,
        unread_count,
        message_count
    FROM conversations
    WHERE id = 34
    LIMIT 1
");
$charlesStmt->execute();
$charles = $charlesStmt->fetch(PDO::FETCH_ASSOC);

if ($charles) {
    echo sprintf(
        "   ✅ Charles encontrado: thread_id=whatsapp_34, contact=%s, last_message_at=%s, direction=%s, unread_count=%d\n",
        $charles['contact_external_id'] ?? 'NULL',
        $charles['last_message_at'] ?? 'NULL',
        $charles['last_message_direction'] ?? 'N/A',
        $charles['unread_count'] ?? 0
    );
    
    // Compara com primeira conversa da lista
    if (!empty($conversations)) {
        $firstConv = $conversations[0];
        $charlesTime = strtotime($charles['last_message_at'] ?: '1970-01-01');
        $firstTime = strtotime($firstConv['last_message_at'] ?: '1970-01-01');
        
        if ($charlesTime > $firstTime) {
            echo "   ⚠️  PROBLEMA: Charles tem last_message_at MAIOR que primeira conversa, mas não está no topo!\n";
            echo sprintf(
                "      Charles: %s (%s)\n",
                $charles['last_message_at'],
                date('Y-m-d H:i:s', $charlesTime)
            );
            echo sprintf(
                "      Primeira: %s (%s)\n",
                $firstConv['last_message_at'],
                date('Y-m-d H:i:s', $firstTime)
            );
        } elseif ($charlesTime === $firstTime && $charles['id'] != $firstConv['id']) {
            echo "   ⚠️  AVISO: Charles tem mesmo last_message_at que primeira conversa (empate)\n";
        } else {
            echo "   ✅ Charles está corretamente posicionado (não é o mais recente)\n";
        }
    }
} else {
    echo "   ⚠️  Charles (whatsapp_34) não encontrado!\n";
}

// 4. Busca conversa do ServPro (final 4223)
echo "\n4. Buscando conversas do ServPro (final 4223):\n";
$servproStmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        tenant_id,
        last_message_at,
        last_message_direction,
        unread_count,
        message_count
    FROM conversations
    WHERE channel_type = 'whatsapp'
    AND (
        contact_external_id LIKE '%4223%'
        OR contact_external_id LIKE '%96474223%'
    )
    ORDER BY last_message_at DESC
    LIMIT 5
");
$servproStmt->execute();
$servproConvs = $servproStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($servproConvs)) {
    echo "   ⚠️  Nenhuma conversa do ServPro encontrada!\n";
} else {
    echo "   Encontradas " . count($servproConvs) . " conversas do ServPro:\n";
    foreach ($servproConvs as $i => $conv) {
        $threadId = "whatsapp_{$conv['id']}";
        echo sprintf(
            "   [%d] thread_id=%s, contact=%s, last_message_at=%s, direction=%s, unread_count=%d\n",
            $i + 1,
            $threadId,
            $conv['contact_external_id'] ?? 'NULL',
            $conv['last_message_at'] ?? 'NULL',
            $conv['last_message_direction'] ?? 'N/A',
            $conv['unread_count'] ?? 0
        );
    }
}

// 5. Verifica eventos recentes do ServPro
echo "\n5. Verificando eventos recentes do ServPro (últimas 5):\n";
$eventsStmt = $db->prepare("
    SELECT 
        event_id,
        event_type,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as message_from
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE '%4223%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%4223%'
    )
    ORDER BY created_at DESC
    LIMIT 5
");
$eventsStmt->execute();
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ⚠️  Nenhum evento do ServPro encontrado!\n";
} else {
    echo "   Encontrados " . count($events) . " eventos:\n";
    foreach ($events as $i => $event) {
        echo sprintf(
            "   [%d] event_id=%s, type=%s, created_at=%s, from=%s\n",
            $i + 1,
            $event['event_id'] ?? 'NULL',
            $event['event_type'] ?? 'N/A',
            $event['created_at'] ?? 'NULL',
            $event['from_field'] ?? $event['message_from'] ?? 'NULL'
        );
    }
}

echo "\n=== FIM DO TESTE ===\n";

