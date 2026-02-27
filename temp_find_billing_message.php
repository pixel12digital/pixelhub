<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== BUSCANDO MENSAGEM DE COBRANÇA NO INBOX ===\n\n";

// Buscar mensagens do Renato no dia 27/02 que contenham palavras-chave de cobrança
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.source_system,
        ce.created_at,
        ce.conversation_id,
        JSON_EXTRACT(ce.payload, '$.text') as message_text,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by
    FROM communication_events ce
    WHERE ce.tenant_id = 36
      AND DATE(ce.created_at) = '2026-02-27'
      AND ce.event_type LIKE '%outbound%'
      AND (
          JSON_EXTRACT(ce.payload, '$.text') LIKE '%fatura%'
          OR JSON_EXTRACT(ce.payload, '$.text') LIKE '%pagamento%'
          OR JSON_EXTRACT(ce.payload, '$.text') LIKE '%vencimento%'
          OR JSON_EXTRACT(ce.payload, '$.text') LIKE '%R$%'
          OR JSON_EXTRACT(ce.payload, '$.text') LIKE '%asaas%'
      )
    ORDER BY ce.created_at ASC
");
$stmt->execute();
$billingMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mensagens com palavras-chave de cobrança: " . count($billingMessages) . "\n\n";

foreach ($billingMessages as $msg) {
    echo "─────────────────────────────────────────\n";
    echo "Event ID: " . $msg['id'] . "\n";
    echo "Tipo: " . $msg['event_type'] . "\n";
    echo "Source: " . $msg['source_system'] . "\n";
    echo "Criado em: " . $msg['created_at'] . "\n";
    echo "Conversa ID: " . ($msg['conversation_id'] ?? 'NULL') . "\n";
    echo "Invoice ID: " . ($msg['invoice_id'] ?? 'NULL') . "\n";
    echo "É cobrança automática?: " . ($msg['is_billing'] ?? 'NULL') . "\n";
    echo "Enviado por: " . ($msg['sent_by'] ?? 'NULL') . "\n";
    
    $text = str_replace(['"', '\\n', '\n'], ['', ' ', ' '], $msg['message_text'] ?? '');
    echo "\nTexto completo:\n";
    echo $text . "\n";
    echo "\n";
}

// Agora vamos buscar webhooks inbound (confirmação de envio) do gateway
echo "\n=== WEBHOOKS INBOUND (confirmação de envio) - 27/02/2026 ===\n\n";

$stmt2 = $db->prepare("
    SELECT 
        id,
        event_type,
        received_at,
        processed,
        JSON_EXTRACT(payload_json, '$.message.id') as message_id,
        JSON_EXTRACT(payload_json, '$.message.to') as phone_to,
        JSON_EXTRACT(payload_json, '$.message.from') as phone_from,
        JSON_EXTRACT(payload_json, '$.message.fromMe') as from_me,
        JSON_EXTRACT(payload_json, '$.message.body') as message_body
    FROM webhook_raw_logs
    WHERE DATE(received_at) = '2026-02-27'
      AND event_type = 'message'
      AND (
          JSON_EXTRACT(payload_json, '$.message.to') LIKE '%53981642320%'
          OR JSON_EXTRACT(payload_json, '$.message.from') LIKE '%53981642320%'
      )
    ORDER BY received_at ASC
    LIMIT 20
");
$stmt2->execute();
$webhooks = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks encontrados: " . count($webhooks) . "\n\n";

foreach ($webhooks as $w) {
    echo "─────────────────────────────────────────\n";
    echo "Webhook ID: " . $w['id'] . "\n";
    echo "Recebido em: " . $w['received_at'] . "\n";
    echo "Processado?: " . $w['processed'] . "\n";
    echo "Message ID: " . ($w['message_id'] ?? 'NULL') . "\n";
    echo "Para: " . ($w['phone_to'] ?? 'NULL') . "\n";
    echo "De: " . ($w['phone_from'] ?? 'NULL') . "\n";
    echo "De mim (fromMe)?: " . ($w['from_me'] ?? 'NULL') . "\n";
    
    $body = str_replace(['"', '\\n'], ['', ' '], $w['message_body'] ?? '');
    echo "Body (primeiros 150 chars): " . substr($body, 0, 150) . "...\n";
    echo "\n";
}
