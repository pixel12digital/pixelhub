<?php
/**
 * Diagnóstico: Verifica se eventos inbound estão atualizando conversas
 * 
 * Objetivo: Confirmar que:
 * 1. Eventos inbound estão sendo criados quando mensagens chegam
 * 2. ConversationService::resolveConversation() está sendo chamado
 * 3. last_message_at está sendo atualizado
 * 4. unread_count está sendo incrementado
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

echo "=== DIAGNÓSTICO: Atualização de Conversas por Inbound ===\n\n";

// 1. Busca eventos inbound recentes (últimas 2 horas)
echo "1. Buscando eventos inbound recentes (últimas 2 horas):\n";
$eventsStmt = $db->prepare("
    SELECT 
        event_id,
        event_type,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.to') as to_field
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$eventsStmt->execute();
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ⚠️  Nenhum evento inbound encontrado nas últimas 2 horas!\n\n";
} else {
    echo "   Encontrados " . count($events) . " eventos:\n";
    foreach ($events as $i => $event) {
        $from = $event['from_field'] ?? $event['message_from'] ?? 'NULL';
        echo sprintf(
            "   [%d] event_id=%s, created_at=%s, from=%s, tenant_id=%s\n",
            $i + 1,
            $event['event_id'] ?? 'NULL',
            $event['created_at'] ?? 'NULL',
            $from,
            $event['tenant_id'] ?? 'NULL'
        );
    }
}

// 2. Busca conversas e compara last_message_at com eventos
echo "\n2. Comparando last_message_at das conversas com eventos recentes:\n";
$conversationsStmt = $db->prepare("
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
        contact_external_id LIKE '%4223%'
        OR contact_external_id LIKE '%4699%'
        OR contact_external_id LIKE '%96474223%'
        OR contact_external_id LIKE '%96164699%'
    )
    ORDER BY last_message_at DESC
    LIMIT 10
");
$conversationsStmt->execute();
$conversations = $conversationsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ⚠️  Nenhuma conversa encontrada para ServPro ou Charles!\n\n";
} else {
    echo "   Encontradas " . count($conversations) . " conversas:\n";
    foreach ($conversations as $i => $conv) {
        $threadId = "whatsapp_{$conv['id']}";
        echo sprintf(
            "   [%d] thread_id=%s, contact=%s, last_message_at=%s, direction=%s, unread_count=%d, message_count=%d, updated_at=%s\n",
            $i + 1,
            $threadId,
            $conv['contact_external_id'] ?? 'NULL',
            $conv['last_message_at'] ?? 'NULL',
            $conv['last_message_direction'] ?? 'N/A',
            $conv['unread_count'] ?? 0,
            $conv['message_count'] ?? 0,
            $conv['updated_at'] ?? 'NULL'
        );
        
        // Busca eventos mais recentes que last_message_at para esta conversa
        $contactId = $conv['contact_external_id'];
        $lastMessageAt = $conv['last_message_at'];
        
        if ($lastMessageAt) {
            $recentEventsStmt = $db->prepare("
                SELECT 
                    event_id,
                    created_at,
                    JSON_EXTRACT(payload, '$.from') as from_field,
                    JSON_EXTRACT(payload, '$.message.from') as message_from
                FROM communication_events
                WHERE event_type = 'whatsapp.inbound.message'
                AND created_at > ?
                AND (
                    JSON_EXTRACT(payload, '$.from') LIKE ?
                    OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
                )
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $contactPattern = "%{$contactId}%";
            $recentEventsStmt->execute([$lastMessageAt, $contactPattern, $contactPattern]);
            $recentEvents = $recentEventsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($recentEvents)) {
                echo "      ⚠️  PROBLEMA: Existem eventos mais recentes que last_message_at!\n";
                foreach ($recentEvents as $recentEvent) {
                    echo sprintf(
                        "         → event_id=%s, created_at=%s (mais recente que last_message_at=%s)\n",
                        $recentEvent['event_id'] ?? 'NULL',
                        $recentEvent['created_at'] ?? 'NULL',
                        $lastMessageAt
                    );
                }
            } else {
                echo "      ✅ Nenhum evento mais recente encontrado\n";
            }
        }
    }
}

// 3. Verifica se há eventos sem conversa correspondente
echo "\n3. Verificando eventos inbound sem conversa correspondente:\n";
$orphanEventsStmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND NOT EXISTS (
        SELECT 1 FROM conversations c
        WHERE c.channel_type = 'whatsapp'
        AND (
            c.contact_external_id LIKE CONCAT('%', REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.from'), '@c.us', ''), '@lid', ''), '%')
            OR c.contact_external_id LIKE CONCAT('%', REPLACE(REPLACE(JSON_EXTRACT(ce.payload, '$.message.from'), '@c.us', ''), '@lid', ''), '%')
        )
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$orphanEventsStmt->execute();
$orphanEvents = $orphanEventsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orphanEvents)) {
    echo "   ✅ Nenhum evento órfão encontrado (todos têm conversa correspondente)\n";
} else {
    echo "   ⚠️  Encontrados " . count($orphanEvents) . " eventos sem conversa correspondente:\n";
    foreach ($orphanEvents as $i => $event) {
        $from = $event['from_field'] ?? $event['message_from'] ?? 'NULL';
        echo sprintf(
            "   [%d] event_id=%s, created_at=%s, from=%s, tenant_id=%s\n",
            $i + 1,
            $event['event_id'] ?? 'NULL',
            $event['created_at'] ?? 'NULL',
            $from,
            $event['tenant_id'] ?? 'NULL'
        );
    }
}

// 4. Verifica logs de ConversationService
echo "\n4. Verificando se há logs de ConversationService::resolveConversation():\n";
echo "   (Verifique os logs do servidor para mensagens com [DIAGNOSTICO] ou [CONVERSATION UPSERT])\n";
echo "   Comando sugerido: tail -n 100 /var/log/php_errors.log | grep -i 'conversation'\n";

// 5. Verifica se há logs de EventIngestionService
echo "\n5. Verificando se há logs de EventIngestionService::ingest():\n";
echo "   (Verifique os logs do servidor para mensagens com [DIAGNOSTICO] ou [EventIngestion])\n";
echo "   Comando sugerido: tail -n 100 /var/log/php_errors.log | grep -i 'eventingestion'\n";

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
echo "\nPróximos passos:\n";
echo "1. Verificar logs do servidor para confirmar se ConversationService está sendo chamado\n";
echo "2. Verificar se webhook está chegando quando mensagens são enviadas\n";
echo "3. Verificar se last_message_at está sendo atualizado no banco\n";
echo "4. Verificar se unread_count está sendo incrementado\n";

