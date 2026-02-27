<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO PROFUNDA: COBRANÇA RENATO 27/02/2026 08:50 ===\n\n";

// 1. Buscar a notificação de falha
echo "1. NOTIFICAÇÃO DE COBRANÇA (ID 196)\n";
echo "─────────────────────────────────────────\n";
$stmt = $db->prepare("
    SELECT 
        bn.*,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM billing_notifications bn
    LEFT JOIN tenants t ON bn.tenant_id = t.id
    WHERE bn.id = 196
");
$stmt->execute();
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Tenant: " . $notification['tenant_name'] . " (ID: " . $notification['tenant_id'] . ")\n";
echo "Telefone: " . $notification['tenant_phone'] . "\n";
echo "Invoice ID: " . $notification['invoice_id'] . "\n";
echo "Status: " . $notification['status'] . "\n";
echo "Enviado em: " . $notification['sent_at'] . "\n";
echo "Gateway Message ID: " . ($notification['gateway_message_id'] ?? 'NULL') . "\n";
echo "Erro: " . $notification['last_error'] . "\n";
echo "\n\n";

// 2. Buscar TODOS os eventos no Inbox do Renato próximos ao horário (08:48 a 08:52)
echo "2. EVENTOS NO INBOX (communication_events) - Renato - 08:48 a 08:52\n";
echo "─────────────────────────────────────────\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.conversation_id,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.to') as phone_to,
        JSON_EXTRACT(ce.payload, '$.text') as message_text,
        JSON_EXTRACT(ce.metadata, '$.message_id') as gateway_msg_id,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
        JSON_EXTRACT(ce.metadata, '$.delivery_uncertain') as delivery_uncertain
    FROM communication_events ce
    WHERE ce.tenant_id = 36
      AND DATE(ce.created_at) = '2026-02-27'
      AND TIME(ce.created_at) BETWEEN '08:48:00' AND '08:52:00'
    ORDER BY ce.created_at ASC
");
$stmt2->execute();
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($events) . "\n\n";

if (count($events) > 0) {
    foreach ($events as $e) {
        echo "─────────────────────────────────────────\n";
        echo "Event ID: " . $e['id'] . "\n";
        echo "Tipo: " . $e['event_type'] . "\n";
        echo "Source: " . $e['source_system'] . "\n";
        echo "Criado em: " . $e['created_at'] . "\n";
        echo "Conversa ID: " . ($e['conversation_id'] ?? 'NULL') . "\n";
        echo "Para: " . ($e['phone_to'] ?? 'NULL') . "\n";
        echo "Gateway Msg ID: " . ($e['gateway_msg_id'] ?? 'NULL') . "\n";
        echo "Invoice ID: " . ($e['invoice_id'] ?? 'NULL') . "\n";
        echo "É cobrança automática?: " . ($e['is_billing'] ?? 'NULL') . "\n";
        echo "Enviado por: " . ($e['sent_by'] ?? 'NULL') . "\n";
        echo "Delivery uncertain?: " . ($e['delivery_uncertain'] ?? 'NULL') . "\n";
        
        $text = str_replace(['"', '\n', '\\n'], ['', ' ', ' '], $e['message_text'] ?? '');
        echo "Texto (primeiros 200 chars): " . substr($text, 0, 200) . "...\n";
        echo "\n";
    }
} else {
    echo "❌ NENHUM evento encontrado no Inbox nesse horário!\n\n";
}

// 3. Buscar eventos SEM tenant_id mas com o telefone do Renato
echo "\n3. EVENTOS OUTBOUND SEM TENANT_ID (telefone 53981642320) - 08:48 a 08:52\n";
echo "─────────────────────────────────────────\n";
$stmt3 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.conversation_id,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.to') as phone_to,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.delivery_uncertain') as delivery_uncertain
    FROM communication_events ce
    WHERE ce.event_type LIKE '%outbound%'
      AND DATE(ce.created_at) = '2026-02-27'
      AND TIME(ce.created_at) BETWEEN '08:48:00' AND '08:52:00'
      AND (JSON_EXTRACT(ce.payload, '$.to') LIKE '%53981642320%' 
           OR JSON_EXTRACT(ce.payload, '$.to') LIKE '%5553981642320%')
    ORDER BY ce.created_at ASC
");
$stmt3->execute();
$orphanEvents = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($orphanEvents) . "\n\n";

if (count($orphanEvents) > 0) {
    foreach ($orphanEvents as $e) {
        echo "─────────────────────────────────────────\n";
        echo "Event ID: " . $e['id'] . "\n";
        echo "Tipo: " . $e['event_type'] . "\n";
        echo "Source: " . $e['source_system'] . "\n";
        echo "Criado em: " . $e['created_at'] . "\n";
        echo "Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
        echo "Conversa ID: " . ($e['conversation_id'] ?? 'NULL') . "\n";
        echo "Para: " . ($e['phone_to'] ?? 'NULL') . "\n";
        echo "Invoice ID: " . ($e['invoice_id'] ?? 'NULL') . "\n";
        echo "É cobrança?: " . ($e['is_billing'] ?? 'NULL') . "\n";
        echo "Delivery uncertain?: " . ($e['delivery_uncertain'] ?? 'NULL') . "\n";
        echo "\n";
    }
}

// 4. Verificar webhooks do gateway nesse horário
echo "\n4. WEBHOOKS DO GATEWAY (webhook_raw_logs) - 08:48 a 08:52\n";
echo "─────────────────────────────────────────\n";
$stmt4 = $db->prepare("
    SELECT 
        id,
        event_type,
        received_at,
        processed,
        event_id,
        JSON_EXTRACT(payload_json, '$.message.id') as message_id,
        JSON_EXTRACT(payload_json, '$.message.to') as phone_to,
        JSON_EXTRACT(payload_json, '$.message.from') as phone_from,
        SUBSTRING(payload_json, 1, 300) as payload_preview
    FROM webhook_raw_logs
    WHERE DATE(received_at) = '2026-02-27'
      AND TIME(received_at) BETWEEN '08:48:00' AND '08:52:00'
    ORDER BY received_at ASC
");
$stmt4->execute();
$webhooks = $stmt4->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks encontrados: " . count($webhooks) . "\n\n";

foreach ($webhooks as $w) {
    echo "─────────────────────────────────────────\n";
    echo "Webhook ID: " . $w['id'] . "\n";
    echo "Tipo: " . $w['event_type'] . "\n";
    echo "Recebido em: " . $w['received_at'] . "\n";
    echo "Processado?: " . $w['processed'] . "\n";
    echo "Event ID: " . ($w['event_id'] ?? 'NULL') . "\n";
    echo "Message ID: " . ($w['message_id'] ?? 'NULL') . "\n";
    echo "Para: " . ($w['phone_to'] ?? 'NULL') . "\n";
    echo "De: " . ($w['phone_from'] ?? 'NULL') . "\n";
    echo "\n";
}
