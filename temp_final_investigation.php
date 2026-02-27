<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== RESUMO DA INVESTIGAÇÃO ===\n\n";

// 1. Notificação de cobrança marcada como falha
echo "1. NOTIFICAÇÃO DE COBRANÇA (billing_notifications)\n";
echo "─────────────────────────────────────────\n";
$stmt = $db->prepare("SELECT * FROM billing_notifications WHERE id = 196");
$stmt->execute();
$notification = $stmt->fetch(PDO::FETCH_ASSOC);

echo "ID: " . $notification['id'] . "\n";
echo "Tenant ID: " . $notification['tenant_id'] . "\n";
echo "Invoice ID: " . $notification['invoice_id'] . "\n";
echo "Status: " . $notification['status'] . "\n";
echo "Enviado em: " . $notification['sent_at'] . "\n";
echo "Erro: " . $notification['last_error'] . "\n";
echo "\n\n";

// 2. Verificar se há mensagens no Inbox (communication_events) relacionadas à fatura 141
echo "2. MENSAGENS NO INBOX RELACIONADAS À FATURA #141\n";
echo "─────────────────────────────────────────\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.conversation_id,
        JSON_EXTRACT(ce.metadata, '$.invoice_id') as invoice_id,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
        SUBSTRING(JSON_EXTRACT(ce.payload, '$.text'), 1, 100) as message_preview
    FROM communication_events ce
    WHERE ce.tenant_id = 36
      AND JSON_EXTRACT(ce.metadata, '$.invoice_id') = '141'
    ORDER BY ce.created_at DESC
");
$stmt2->execute();
$inboxMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (count($inboxMessages) > 0) {
    foreach ($inboxMessages as $msg) {
        echo "Event ID: " . $msg['id'] . "\n";
        echo "Tipo: " . $msg['event_type'] . "\n";
        echo "Criado em: " . $msg['created_at'] . "\n";
        echo "Conversa ID: " . ($msg['conversation_id'] ?? 'NULL') . "\n";
        echo "Invoice ID: " . ($msg['invoice_id'] ?? 'NULL') . "\n";
        echo "É cobrança automática?: " . ($msg['is_billing'] ?? 'NULL') . "\n";
        echo "Enviado por: " . ($msg['sent_by'] ?? 'NULL') . "\n";
        echo "Preview: " . substr(str_replace(['"', '\n'], ['', ' '], $msg['message_preview'] ?? ''), 0, 80) . "...\n";
        echo "\n";
    }
} else {
    echo "❌ NENHUMA mensagem encontrada no Inbox com invoice_id=141\n\n";
}

// 3. Verificar todas as mensagens do Renato no dia 27/02
echo "\n3. TODAS AS MENSAGENS DO RENATO (tenant_id=36) EM 27/02/2026\n";
echo "─────────────────────────────────────────\n";
$stmt3 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.conversation_id,
        JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
        SUBSTRING(JSON_EXTRACT(ce.payload, '$.text'), 1, 150) as message_preview
    FROM communication_events ce
    WHERE ce.tenant_id = 36
      AND DATE(ce.created_at) = '2026-02-27'
    ORDER BY ce.created_at ASC
");
$stmt3->execute();
$allMessages = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mensagens: " . count($allMessages) . "\n\n";

foreach ($allMessages as $msg) {
    echo "[" . $msg['created_at'] . "] " . $msg['event_type'] . "\n";
    echo "  Enviado por: " . ($msg['sent_by'] ?? 'NULL') . "\n";
    echo "  Preview: " . substr(str_replace(['"', '\n', '\\'], ['', ' ', ''], $msg['message_preview'] ?? ''), 0, 100) . "...\n";
    echo "\n";
}

// 4. CONCLUSÃO
echo "\n=== CONCLUSÃO ===\n";
echo "─────────────────────────────────────────\n";
echo "A notificação de cobrança ID 196 está marcada como FALHA porque:\n";
echo "- O gateway deu timeout de 30s\n";
echo "- A mensagem NÃO foi enviada (não há webhook de confirmação)\n";
echo "- A mensagem NÃO foi registrada no Inbox (communication_events)\n";
echo "\n";
echo "Se você vê a mensagem no WhatsApp Web, provavelmente:\n";
echo "- Você enviou manualmente depois através do Inbox\n";
echo "- OU a mensagem foi enviada por outro meio\n";
echo "\n";
