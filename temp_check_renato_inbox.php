<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

// Busca eventos do Renato (tenant_id=36) próximos ao horário 27/02/2026 08:50
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.to') as phone_to,
        JSON_EXTRACT(ce.payload, '$.from') as phone_from,
        JSON_EXTRACT(ce.payload, '$.text') as message_text,
        JSON_EXTRACT(ce.metadata, '$.message_id') as gateway_msg_id,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by
    FROM communication_events ce
    WHERE ce.tenant_id = 36
      AND DATE(ce.created_at) = '2026-02-27'
      AND TIME(ce.created_at) BETWEEN '08:49:00' AND '08:52:00'
    ORDER BY ce.created_at DESC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== EVENTOS DO RENATO (tenant_id=36) em 27/02/2026 ~08:50 ===\n\n";
echo "Total encontrado: " . count($events) . "\n\n";

foreach ($events as $e) {
    echo "─────────────────────────────────────────\n";
    echo "Event ID: " . $e['id'] . "\n";
    echo "Tipo: " . $e['event_type'] . "\n";
    echo "Source: " . $e['source_system'] . "\n";
    echo "Criado em: " . $e['created_at'] . "\n";
    echo "Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
    echo "Para: " . ($e['phone_to'] ?? 'NULL') . "\n";
    echo "De: " . ($e['phone_from'] ?? 'NULL') . "\n";
    echo "Gateway Msg ID: " . ($e['gateway_msg_id'] ?? 'NULL') . "\n";
    echo "Invoice ID: " . ($e['invoice_id'] ?? 'NULL') . "\n";
    echo "É cobrança?: " . ($e['is_billing'] ?? 'NULL') . "\n";
    echo "Enviado por: " . ($e['sent_by'] ?? 'NULL') . "\n";
    echo "Texto (primeiros 150 chars): " . substr(str_replace(['"', '\n'], ['', ' '], $e['message_text'] ?? ''), 0, 150) . "...\n";
    echo "\n";
}

// Agora vamos verificar se há algum evento outbound SEM tenant_id mas com o telefone do Renato
echo "\n=== EVENTOS OUTBOUND SEM TENANT_ID (telefone 53981642320) ===\n\n";

$stmt2 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.to') as phone_to,
        JSON_EXTRACT(ce.metadata, '$.message_id') as gateway_msg_id,
        JSON_EXTRACT(ce.metadata, '$.delivery_uncertain') as delivery_uncertain
    FROM communication_events ce
    WHERE ce.event_type LIKE '%outbound%'
      AND DATE(ce.created_at) = '2026-02-27'
      AND TIME(ce.created_at) BETWEEN '08:49:00' AND '08:52:00'
      AND (JSON_EXTRACT(ce.payload, '$.to') LIKE '%53981642320%' 
           OR JSON_EXTRACT(ce.payload, '$.to') LIKE '%5553981642320%')
    ORDER BY ce.created_at DESC
");
$stmt2->execute();
$events2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($events2) . "\n\n";

foreach ($events2 as $e) {
    echo "─────────────────────────────────────────\n";
    echo "Event ID: " . $e['id'] . "\n";
    echo "Tipo: " . $e['event_type'] . "\n";
    echo "Source: " . $e['source_system'] . "\n";
    echo "Criado em: " . $e['created_at'] . "\n";
    echo "Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
    echo "Para: " . ($e['phone_to'] ?? 'NULL') . "\n";
    echo "Gateway Msg ID: " . ($e['gateway_msg_id'] ?? 'NULL') . "\n";
    echo "Delivery Uncertain?: " . ($e['delivery_uncertain'] ?? 'NULL') . "\n";
    echo "\n";
}
