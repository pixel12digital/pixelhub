<?php
/**
 * Verifica se conversas estão sendo atualizadas quando eventos inbound chegam
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== VERIFICAÇÃO: Atualização de Conversas ===\n\n";

// 1. Busca conversa do Charles (whatsapp_34 ou whatsapp_35)
echo "1. Buscando conversas do Charles e ServPro:\n";
$charlesStmt = $db->prepare("
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
    AND (
        contact_external_id LIKE '%4699%'
        OR contact_external_id LIKE '%96164699%'
        OR contact_external_id LIKE '%96474223%'
        OR contact_external_id LIKE '%4223%'
    )
    ORDER BY last_message_at DESC
");
$charlesStmt->execute();
$conversations = $charlesStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    $threadId = "whatsapp_{$conv['id']}";
    echo sprintf(
        "   thread_id=%s, contact=%s, last_message_at=%s, direction=%s, unread_count=%d, message_count=%d\n",
        $threadId,
        $conv['contact_external_id'],
        $conv['last_message_at'],
        $conv['last_message_direction'],
        $conv['unread_count'],
        $conv['message_count']
    );
    
    // Busca eventos mais recentes para este contato
    $contactId = $conv['contact_external_id'];
    $normalizedContact = preg_replace('/[^0-9]/', '', $contactId);
    
    echo "      Normalizado: {$normalizedContact}\n";
    
    // Busca eventos com este contato (normalizando o from do evento também)
    $eventsStmt = $db->prepare("
        SELECT 
            event_id,
            event_type,
            created_at,
            JSON_EXTRACT(payload, '$.from') as from_raw,
            JSON_EXTRACT(payload, '$.message.from') as message_from_raw
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
            OR REPLACE(REPLACE(JSON_EXTRACT(payload, '$.from'), '@c.us', ''), '@lid', '') LIKE ?
            OR REPLACE(REPLACE(JSON_EXTRACT(payload, '$.message.from'), '@c.us', ''), '@lid', '') LIKE ?
        )
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $pattern1 = "%{$contactId}%";
    $pattern2 = "%{$normalizedContact}%";
    $eventsStmt->execute([$pattern1, $pattern1, $pattern2, $pattern2]);
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($events)) {
        echo "      Eventos encontrados: " . count($events) . "\n";
        foreach ($events as $event) {
            $fromRaw = $event['from_raw'] ?? $event['message_from_raw'] ?? 'NULL';
            $eventTime = strtotime($event['created_at']);
            $convTime = strtotime($conv['last_message_at']);
            
            if ($eventTime > $convTime) {
                echo sprintf(
                    "         ⚠️  EVENTO MAIS RECENTE: event_id=%s, created_at=%s (conversa last_message_at=%s, diferença: %d segundos)\n",
                    $event['event_id'],
                    $event['created_at'],
                    $conv['last_message_at'],
                    $eventTime - $convTime
                );
            } else {
                echo sprintf(
                    "         ✅ Evento processado: event_id=%s, created_at=%s\n",
                    $event['event_id'],
                    $event['created_at']
                );
            }
        }
    } else {
        echo "      ⚠️  Nenhum evento encontrado para este contato!\n";
    }
    
    echo "\n";
}

// 2. Verifica eventos do Charles mais recentes que last_message_at
echo "2. Verificando eventos do Charles (554796164699) mais recentes que last_message_at:\n";
$charlesConv = null;
foreach ($conversations as $conv) {
    if (strpos($conv['contact_external_id'], '4699') !== false || strpos($conv['contact_external_id'], '96164699') !== false) {
        $charlesConv = $conv;
        break;
    }
}

if ($charlesConv) {
    $lastMessageAt = $charlesConv['last_message_at'];
    echo "   Conversa do Charles: thread_id=whatsapp_{$charlesConv['id']}, last_message_at={$lastMessageAt}\n";
    
    $recentEventsStmt = $db->prepare("
        SELECT 
            event_id,
            created_at,
            JSON_EXTRACT(payload, '$.from') as from_raw,
            JSON_EXTRACT(payload, '$.message.from') as message_from_raw
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND created_at > ?
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE '%4699%'
            OR JSON_EXTRACT(payload, '$.message.from') LIKE '%4699%'
            OR JSON_EXTRACT(payload, '$.from') LIKE '%96164699%'
            OR JSON_EXTRACT(payload, '$.message.from') LIKE '%96164699%'
        )
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentEventsStmt->execute([$lastMessageAt]);
    $recentEvents = $recentEventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recentEvents)) {
        echo "   ⚠️  PROBLEMA: Existem " . count($recentEvents) . " eventos mais recentes que last_message_at!\n";
        foreach ($recentEvents as $event) {
            $fromRaw = $event['from_raw'] ?? $event['message_from_raw'] ?? 'NULL';
            echo sprintf(
                "      → event_id=%s, created_at=%s, from=%s (last_message_at=%s)\n",
                $event['event_id'],
                $event['created_at'],
                $fromRaw,
                $lastMessageAt
            );
        }
        echo "\n   CONCLUSÃO: ConversationService::resolveConversation() NÃO está atualizando last_message_at!\n";
    } else {
        echo "   ✅ Nenhum evento mais recente encontrado (conversa está atualizada)\n";
    }
} else {
    echo "   ⚠️  Conversa do Charles não encontrada!\n";
}

// 3. Verifica eventos do ServPro
echo "\n3. Verificando eventos do ServPro (4223) mais recentes que last_message_at:\n";
$servproConv = null;
foreach ($conversations as $conv) {
    if (strpos($conv['contact_external_id'], '4223') !== false || strpos($conv['contact_external_id'], '96474223') !== false) {
        $servproConv = $conv;
        break;
    }
}

if ($servproConv) {
    $lastMessageAt = $servproConv['last_message_at'];
    echo "   Conversa do ServPro: thread_id=whatsapp_{$servproConv['id']}, last_message_at={$lastMessageAt}\n";
    
    $recentEventsStmt = $db->prepare("
        SELECT 
            event_id,
            created_at,
            JSON_EXTRACT(payload, '$.from') as from_raw,
            JSON_EXTRACT(payload, '$.message.from') as message_from_raw
        FROM communication_events
        WHERE event_type = 'whatsapp.inbound.message'
        AND created_at > ?
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE '%4223%'
            OR JSON_EXTRACT(payload, '$.message.from') LIKE '%4223%'
            OR JSON_EXTRACT(payload, '$.from') LIKE '%96474223%'
            OR JSON_EXTRACT(payload, '$.message.from') LIKE '%96474223%'
        )
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recentEventsStmt->execute([$lastMessageAt]);
    $recentEvents = $recentEventsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recentEvents)) {
        echo "   ⚠️  PROBLEMA: Existem " . count($recentEvents) . " eventos mais recentes que last_message_at!\n";
        foreach ($recentEvents as $event) {
            $fromRaw = $event['from_raw'] ?? $event['message_from_raw'] ?? 'NULL';
            echo sprintf(
                "      → event_id=%s, created_at=%s, from=%s (last_message_at=%s)\n",
                $event['event_id'],
                $event['created_at'],
                $fromRaw,
                $lastMessageAt
            );
        }
        echo "\n   CONCLUSÃO: ConversationService::resolveConversation() NÃO está atualizando last_message_at!\n";
    } else {
        echo "   ✅ Nenhum evento mais recente encontrado (conversa está atualizada)\n";
    }
} else {
    echo "   ⚠️  Conversa do ServPro não encontrada!\n";
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

