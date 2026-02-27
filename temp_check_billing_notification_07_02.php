<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

// Busca notificações de 27/02/2026 (data atual) com status 'failed'
$stmt = $db->prepare("
    SELECT 
        bn.*,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM billing_notifications bn
    LEFT JOIN tenants t ON bn.tenant_id = t.id
    WHERE bn.status = 'failed'
    ORDER BY bn.sent_at DESC
    LIMIT 20
");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== NOTIFICAÇÕES DE 07/02/2026 ~08:50 ===\n\n";
echo "Total encontrado: " . count($notifications) . "\n\n";

foreach ($notifications as $n) {
    echo "─────────────────────────────────────────\n";
    echo "ID: " . $n['id'] . "\n";
    echo "Tenant: " . $n['tenant_name'] . " (ID: " . $n['tenant_id'] . ")\n";
    echo "Telefone: " . $n['tenant_phone'] . "\n";
    echo "Fatura ID: #" . $n['invoice_id'] . "\n";
    echo "\n";
    echo "Canal: " . $n['channel'] . "\n";
    echo "Status Notificação: " . $n['status'] . "\n";
    echo "Enviado em: " . $n['sent_at'] . "\n";
    echo "Triggered by: " . ($n['triggered_by'] ?? 'NULL') . "\n";
    echo "Gateway Message ID: " . ($n['gateway_message_id'] ?? 'NULL') . "\n";
    echo "\n";
    echo "Último erro: " . ($n['last_error'] ?? 'NULL') . "\n";
    echo "Mensagem enviada:\n" . substr($n['message'] ?? '', 0, 200) . "...\n";
    echo "\n";
}

// Agora vamos buscar no Inbox se essa mensagem realmente foi enviada
echo "\n=== VERIFICANDO NO INBOX (communication_events) ===\n\n";

// Busca eventos de saída (outbound) próximos ao horário
$stmt2 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.to') as phone_to,
        JSON_EXTRACT(ce.payload, '$.text') as message_text,
        JSON_EXTRACT(ce.metadata, '$.message_id') as gateway_msg_id,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing
    FROM communication_events ce
    WHERE ce.event_type LIKE '%outbound%'
      AND DATE(ce.created_at) = '2026-02-07'
      AND TIME(ce.created_at) BETWEEN '08:49:00' AND '08:51:00'
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt2->execute();
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos outbound encontrados: " . count($events) . "\n\n";

foreach ($events as $e) {
    echo "─────────────────────────────────────────\n";
    echo "Event ID: " . $e['id'] . "\n";
    echo "Tipo: " . $e['event_type'] . "\n";
    echo "Source: " . $e['source_system'] . "\n";
    echo "Criado em: " . $e['created_at'] . "\n";
    echo "Tenant ID: " . ($e['tenant_id'] ?? 'NULL') . "\n";
    echo "Para: " . ($e['phone_to'] ?? 'NULL') . "\n";
    echo "Gateway Msg ID: " . ($e['gateway_msg_id'] ?? 'NULL') . "\n";
    echo "Invoice ID: " . ($e['invoice_id'] ?? 'NULL') . "\n";
    echo "É cobrança?: " . ($e['is_billing'] ?? 'NULL') . "\n";
    echo "Texto: " . substr($e['message_text'] ?? '', 0, 100) . "...\n";
    echo "\n";
}
