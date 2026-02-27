<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== TODAS AS NOTIFICAÇÕES COM STATUS 'failed' (últimas 10) ===\n\n";

$stmt = $db->prepare("
    SELECT 
        bn.id,
        bn.tenant_id,
        bn.invoice_id,
        bn.sent_at,
        bn.last_error,
        bn.gateway_message_id,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM billing_notifications bn
    LEFT JOIN tenants t ON bn.tenant_id = t.id
    WHERE bn.status = 'failed'
    ORDER BY bn.sent_at DESC
    LIMIT 10
");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($notifications as $n) {
    echo "─────────────────────────────────────────\n";
    echo "ID: " . $n['id'] . "\n";
    echo "Tenant: " . $n['tenant_name'] . " (ID: " . $n['tenant_id'] . ")\n";
    echo "Telefone: " . $n['tenant_phone'] . "\n";
    echo "Invoice ID: " . $n['invoice_id'] . "\n";
    echo "Enviado em: " . $n['sent_at'] . "\n";
    echo "Gateway Message ID: " . ($n['gateway_message_id'] ?? 'NULL') . "\n";
    echo "Erro: " . $n['last_error'] . "\n";
    echo "\n";
    
    // Para cada notificação, verificar se há mensagem correspondente no Inbox
    $tenantId = $n['tenant_id'];
    $invoiceId = $n['invoice_id'];
    $sentAt = new DateTime($n['sent_at']);
    $timeStart = clone $sentAt;
    $timeStart->modify('-2 minutes');
    $timeEnd = clone $sentAt;
    $timeEnd->modify('+10 minutes'); // Webhook pode chegar depois
    
    $stmt2 = $db->prepare("
        SELECT 
            ce.id,
            ce.created_at,
            ce.event_type,
            JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
            JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing
        FROM communication_events ce
        WHERE ce.tenant_id = ?
          AND ce.created_at BETWEEN ? AND ?
          AND ce.event_type LIKE '%outbound%'
        LIMIT 5
    ");
    $stmt2->execute([$tenantId, $timeStart->format('Y-m-d H:i:s'), $timeEnd->format('Y-m-d H:i:s')]);
    $events = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($events) > 0) {
        echo "  ✅ ENCONTRADO " . count($events) . " evento(s) no Inbox próximo ao horário:\n";
        foreach ($events as $e) {
            echo "     - Event ID: " . $e['id'] . " | " . $e['created_at'] . " | Invoice: " . ($e['invoice_id'] ?? 'NULL') . " | Billing: " . ($e['is_billing'] ?? 'NULL') . "\n";
        }
    } else {
        echo "  ❌ NENHUM evento encontrado no Inbox próximo ao horário\n";
    }
    echo "\n";
}

// Agora vamos procurar especificamente pela mensagem que você mencionou
echo "\n=== BUSCANDO MENSAGEM ESPECÍFICA NO WHATSAPP WEB ===\n";
echo "Você mencionou que a mensagem está no WhatsApp Web.\n";
echo "Vou buscar todas as mensagens outbound do Renato (tenant_id=36) nos últimos 3 dias:\n\n";

$stmt3 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.text') as message_text,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by
    FROM communication_events ce
    WHERE ce.tenant_id = 36
      AND ce.event_type LIKE '%outbound%'
      AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$stmt3->execute();
$recentMessages = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mensagens outbound do Renato (últimos 3 dias): " . count($recentMessages) . "\n\n";

foreach ($recentMessages as $msg) {
    $text = str_replace(['"', '\\n', '\n'], ['', ' ', ' '], $msg['message_text'] ?? '');
    echo "[" . $msg['created_at'] . "] ";
    echo "Invoice: " . ($msg['invoice_id'] ?? 'NULL') . " | ";
    echo "Billing: " . ($msg['is_billing'] ?? 'NULL') . " | ";
    echo "Por: " . ($msg['sent_by'] ?? 'NULL') . "\n";
    echo "  " . substr($text, 0, 100) . "...\n\n";
}
