<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO MENSAGENS DO LUIZ CARLOS NO INBOX ===\n\n";

// Busca a conversa do Luiz Carlos
$conversationId = 459;

echo "Conversa ID: {$conversationId}\n\n";

// Busca eventos da conversa (mesma query que o Inbox usa)
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        JSON_EXTRACT(ce.payload, '$.message.body') as message_body,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text,
        JSON_EXTRACT(ce.payload, '$.message.type') as message_type,
        JSON_EXTRACT(ce.payload, '$.message.media') as message_media,
        JSON_EXTRACT(ce.payload, '$.raw.payload.type') as raw_type
    FROM communication_events ce
    INNER JOIN conversations c ON (
        (ce.event_type = 'whatsapp.inbound.message' AND JSON_EXTRACT(ce.payload, '$.from') = c.contact_external_id)
        OR (ce.event_type = 'whatsapp.inbound.message' AND JSON_EXTRACT(ce.payload, '$.message.from') = c.contact_external_id)
        OR (ce.event_type = 'whatsapp.outbound.message' AND JSON_EXTRACT(ce.payload, '$.to') = c.contact_external_id)
    )
    WHERE c.id = ?
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mensagens encontradas: " . count($messages) . "\n\n";

foreach ($messages as $msg) {
    echo "─────────────────────────────────────────\n";
    echo "Event ID: {$msg['event_id']}\n";
    echo "DB ID: {$msg['id']}\n";
    echo "Tipo: {$msg['event_type']}\n";
    echo "Criado: {$msg['created_at']}\n";
    echo "Message Type: " . ($msg['message_type'] ?: 'NULL') . "\n";
    echo "Raw Type: " . ($msg['raw_type'] ?: 'NULL') . "\n";
    
    // Verifica se tem mídia
    if ($msg['message_media']) {
        $media = json_decode($msg['message_media'], true);
        echo "Mídia:\n";
        echo "  - Type: " . ($media['type'] ?? 'NULL') . "\n";
        echo "  - Mimetype: " . ($media['mimetype'] ?? 'NULL') . "\n";
        echo "  - Size: " . ($media['size'] ?? 'NULL') . " bytes\n";
        
        // Verifica se tem URL de mídia processada
        $payload = json_decode($msg['payload'], true);
        if (isset($payload['message']['mediaUrl'])) {
            echo "  - MediaUrl: {$payload['message']['mediaUrl']}\n";
        } else {
            echo "  - MediaUrl: NÃO PROCESSADA\n";
        }
    }
    
    // Mostra texto se houver
    $text = $msg['message_text'] ?: $msg['message_body'];
    if ($text && $text !== '""' && $text !== 'null') {
        echo "Texto: " . substr($text, 0, 100) . "\n";
    }
    
    echo "\n";
}

// Verifica se a query do Inbox está correta
echo "\n=== TESTANDO QUERY ALTERNATIVA (POR CONVERSATION_ID) ===\n\n";

$stmt2 = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.message.media.type') as media_type,
        JSON_EXTRACT(ce.payload, '$.raw.payload.type') as raw_type
    FROM communication_events ce
    WHERE ce.conversation_id = ?
    ORDER BY ce.created_at DESC
    LIMIT 20
");

$stmt2->execute([$conversationId]);
$messages2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "Mensagens encontradas via conversation_id: " . count($messages2) . "\n\n";

if (count($messages2) > 0) {
    foreach ($messages2 as $msg) {
        echo "Event ID: {$msg['event_id']} | Tipo: {$msg['event_type']} | Media: " . ($msg['media_type'] ?: 'NULL') . " | Raw: " . ($msg['raw_type'] ?: 'NULL') . " | Criado: {$msg['created_at']}\n";
    }
} else {
    echo "⚠️ PROBLEMA: Campo conversation_id não está sendo preenchido!\n";
}
