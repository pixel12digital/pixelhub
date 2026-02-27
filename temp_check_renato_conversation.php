<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

// Busca a conversa do Renato (tenant_id=36)
$stmt = $db->prepare("
    SELECT 
        c.id as conversation_id,
        c.conversation_key,
        c.channel_type,
        c.contact_external_id,
        c.last_message_at,
        c.tenant_id,
        t.name as tenant_name
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.tenant_id = 36
    ORDER BY c.last_message_at DESC
    LIMIT 5
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== CONVERSAS DO RENATO (tenant_id=36) ===\n\n";
echo "Total encontrado: " . count($conversations) . "\n\n";

foreach ($conversations as $conv) {
    echo "─────────────────────────────────────────\n";
    echo "Conversation ID: " . $conv['conversation_id'] . "\n";
    echo "Key: " . $conv['conversation_key'] . "\n";
    echo "Canal: " . $conv['channel_type'] . "\n";
    echo "Contact External ID: " . $conv['contact_external_id'] . "\n";
    echo "Última mensagem: " . $conv['last_message_at'] . "\n";
    echo "Tenant: " . $conv['tenant_name'] . "\n";
    echo "\n";
    
    // Busca as últimas mensagens dessa conversa
    $conversationId = $conv['conversation_id'];
    $stmt2 = $db->prepare("
        SELECT 
            ce.id,
            ce.event_type,
            ce.created_at,
            JSON_EXTRACT(ce.payload, '$.text') as message_text,
            JSON_EXTRACT(ce.metadata, '$.sent_by_name') as sent_by,
            JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
            JSON_EXTRACT(ce.metadata, '$.delivery_uncertain') as delivery_uncertain
        FROM communication_events ce
        WHERE ce.conversation_id = ?
          AND DATE(ce.created_at) = '2026-02-27'
        ORDER BY ce.created_at DESC
        LIMIT 10
    ");
    $stmt2->execute([$conversationId]);
    $messages = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "  Mensagens em 27/02/2026: " . count($messages) . "\n";
    foreach ($messages as $msg) {
        echo "  ├─ [" . $msg['created_at'] . "] " . $msg['event_type'] . "\n";
        echo "  │  Enviado por: " . ($msg['sent_by'] ?? 'NULL') . "\n";
        echo "  │  É cobrança?: " . ($msg['is_billing'] ?? 'NULL') . "\n";
        echo "  │  Delivery uncertain?: " . ($msg['delivery_uncertain'] ?? 'NULL') . "\n";
        echo "  │  Texto: " . substr(str_replace(['"', '\n'], ['', ' '], $msg['message_text'] ?? ''), 0, 80) . "...\n";
        echo "  │\n";
    }
    echo "\n";
}

// Agora vamos verificar se há mensagens outbound do dia 27/02 que NÃO foram vinculadas a uma conversa
echo "\n=== MENSAGENS OUTBOUND SEM CONVERSA (27/02/2026) ===\n\n";

$stmt3 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_type,
        ce.created_at,
        ce.conversation_id,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.to') as phone_to,
        JSON_EXTRACT(ce.metadata, '$.billing_auto_send') as is_billing,
        JSON_EXTRACT(ce.metadata, '$.delivery_uncertain') as delivery_uncertain
    FROM communication_events ce
    WHERE ce.event_type LIKE '%outbound%'
      AND DATE(ce.created_at) = '2026-02-27'
      AND ce.conversation_id IS NULL
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt3->execute();
$orphanMessages = $stmt3->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($orphanMessages) . "\n\n";

foreach ($orphanMessages as $msg) {
    echo "─────────────────────────────────────────\n";
    echo "Event ID: " . $msg['id'] . "\n";
    echo "Tipo: " . $msg['event_type'] . "\n";
    echo "Criado em: " . $msg['created_at'] . "\n";
    echo "Tenant ID: " . ($msg['tenant_id'] ?? 'NULL') . "\n";
    echo "Para: " . ($msg['phone_to'] ?? 'NULL') . "\n";
    echo "É cobrança?: " . ($msg['is_billing'] ?? 'NULL') . "\n";
    echo "Delivery uncertain?: " . ($msg['delivery_uncertain'] ?? 'NULL') . "\n";
    echo "\n";
}
