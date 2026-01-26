<?php

/**
 * Script para diagnosticar por que o webhook parou de receber mensagens
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Diagnóstico: Webhook parou de receber mensagens ===\n\n";

// 1. Verifica eventos recentes do Charles Dietrich
echo "1. Eventos recentes do Charles Dietrich (últimas 2 horas):\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.status,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as from_field,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) as channel_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO encontrado nas últimas 2 horas\n";
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s):\n";
    foreach ($events as $event) {
        $time = date('H:i:s', strtotime($event['created_at']));
        $text = substr(($event['text'] ?: 'NULL'), 0, 50);
        $status = $event['status'] ?: 'NULL';
        echo "   - {$time} | Status: {$status} | Text: {$text}\n";
    }
    
    // Identifica o último evento recebido
    $lastEvent = $events[0];
    $lastEventTime = strtotime($lastEvent['created_at']);
    $now = time();
    $minutesAgo = round(($now - $lastEventTime) / 60);
    
    echo "\n   ⏰ Último evento recebido: {$minutesAgo} minuto(s) atrás\n";
    
    if ($minutesAgo > 5) {
        echo "   ⚠️  ATENÇÃO: Nenhum evento recebido há mais de 5 minutos\n";
    }
}

// 2. Verifica status dos eventos (pode indicar problemas de processamento)
echo "\n2. Status dos eventos recentes:\n";
$stmt2 = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    GROUP BY status
    ORDER BY count DESC
");
$stmt2->execute();
$statusCounts = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($statusCounts)) {
    echo "   ❌ Nenhum evento encontrado\n";
} else {
    foreach ($statusCounts as $status) {
        echo "   - Status '{$status['status']}': {$status['count']} evento(s)\n";
        
        if ($status['status'] === 'queued' && $status['count'] > 10) {
            echo "     ⚠️  ATENÇÃO: Muitos eventos em 'queued' - pode indicar problema de processamento\n";
        }
        if ($status['status'] === 'failed') {
            echo "     ❌ ERRO: Eventos com status 'failed' - verifique logs\n";
        }
    }
}

// 3. Verifica se há eventos duplicados (pode indicar retry do webhook)
echo "\n3. Verificando eventos duplicados (mesmo idempotency_key):\n";
$stmt3 = $db->prepare("
    SELECT 
        idempotency_key,
        COUNT(*) as count,
        GROUP_CONCAT(event_id ORDER BY created_at DESC SEPARATOR ', ') as event_ids,
        MIN(created_at) as first_created,
        MAX(created_at) as last_created
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    GROUP BY idempotency_key
    HAVING count > 1
    ORDER BY count DESC
    LIMIT 10
");
$stmt3->execute();
$duplicates = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "   ✅ Nenhum evento duplicado encontrado\n";
} else {
    echo "   ⚠️  Encontrados " . count($duplicates) . " idempotency_key(s) duplicado(s):\n";
    foreach ($duplicates as $dup) {
        echo "   - Idempotency Key: {$dup['idempotency_key']}\n";
        echo "     Count: {$dup['count']}\n";
        echo "     Event IDs: {$dup['event_ids']}\n";
        echo "     Primeiro: {$dup['first_created']}\n";
        echo "     Último: {$dup['last_created']}\n";
        echo "\n";
    }
}

// 4. Verifica conversas atualizadas recentemente
echo "4. Conversas atualizadas recentemente (últimas 2 horas):\n";
$stmt4 = $db->prepare("
    SELECT 
        id,
        contact_external_id,
        tenant_id,
        channel_id,
        unread_count,
        last_message_at,
        updated_at
    FROM conversations
    WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND channel_type = 'whatsapp'
    ORDER BY updated_at DESC
    LIMIT 10
");
$stmt4->execute();
$conversations = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ❌ Nenhuma conversa atualizada nas últimas 2 horas\n";
} else {
    echo "   ✅ Encontradas " . count($conversations) . " conversa(s) atualizada(s):\n";
    foreach ($conversations as $conv) {
        $time = date('H:i:s', strtotime($conv['updated_at']));
        echo "   - Conversation ID: {$conv['id']}\n";
        echo "     Contact: " . ($conv['contact_external_id'] ?: 'NULL') . "\n";
        echo "     Last Message At: " . ($conv['last_message_at'] ?: 'NULL') . "\n";
        echo "     Updated At: {$conv['updated_at']} ({$time})\n";
        echo "     Unread Count: {$conv['unread_count']}\n";
        echo "\n";
    }
}

// 5. Verifica se há erros no processamento (eventos com status 'failed' ou 'error')
echo "5. Verificando eventos com problemas:\n";
$stmt5 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.status,
        ce.created_at,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    AND (
        ce.status = 'failed'
        OR ce.status = 'error'
        OR ce.status LIKE '%error%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt5->execute();
$failedEvents = $stmt5->fetchAll(PDO::FETCH_ASSOC);

if (empty($failedEvents)) {
    echo "   ✅ Nenhum evento com status de erro encontrado\n";
} else {
    echo "   ❌ Encontrados " . count($failedEvents) . " evento(s) com problemas:\n";
    foreach ($failedEvents as $event) {
        echo "   - Event ID: {$event['event_id']}\n";
        echo "     Status: {$event['status']}\n";
        echo "     Created At: {$event['created_at']}\n";
        echo "     Metadata: " . ($event['metadata'] ?: 'NULL') . "\n";
        echo "\n";
    }
}

// 6. Verifica taxa de eventos (pode indicar throttling)
echo "6. Taxa de eventos por minuto (últimas 2 horas):\n";
$stmt6 = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as minute,
        COUNT(*) as count
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    GROUP BY minute
    ORDER BY minute DESC
    LIMIT 20
");
$stmt6->execute();
$rateEvents = $stmt6->fetchAll(PDO::FETCH_ASSOC);

if (empty($rateEvents)) {
    echo "   ❌ Nenhum evento encontrado\n";
} else {
    echo "   Taxa de eventos:\n";
    foreach ($rateEvents as $rate) {
        echo "   - {$rate['minute']}: {$rate['count']} evento(s)\n";
        
        if ($rate['count'] > 10) {
            echo "     ⚠️  ATENÇÃO: Taxa alta de eventos neste minuto\n";
        }
    }
}

echo "\n=== Conclusão ===\n";
echo "Se o webhook parou de receber mensagens após funcionar inicialmente:\n";
echo "1. Verifique se há eventos com status 'failed' ou 'error'\n";
echo "2. Verifique se há muitos eventos em 'queued' (pode indicar lentidão)\n";
echo "3. Verifique os logs do servidor para erros após as mensagens recebidas\n";
echo "4. Verifique se há algum limite de taxa sendo atingido\n";
echo "5. Verifique se o gateway está enviando webhooks (pode ter parado)\n";

echo "\n=== Fim do diagnóstico ===\n";

