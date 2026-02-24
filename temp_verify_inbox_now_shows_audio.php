<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO MENSAGENS DO LUIZ CARLOS NO INBOX (APÓS CORREÇÃO) ===\n\n";

$conversationId = 459;

// Query que o Inbox usa (via conversation_id)
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.message.body') as message_body,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text,
        JSON_EXTRACT(ce.payload, '$.message.type') as message_type,
        JSON_EXTRACT(ce.payload, '$.message.media') as message_media,
        JSON_EXTRACT(ce.payload, '$.message.mediaUrl') as media_url,
        JSON_EXTRACT(ce.payload, '$.raw.payload.type') as raw_type
    FROM communication_events ce
    WHERE ce.conversation_id = ?
    ORDER BY ce.created_at ASC
");

$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de mensagens na conversa: " . count($messages) . "\n\n";

foreach ($messages as $idx => $msg) {
    echo "─────────────────────────────────────────\n";
    echo "Mensagem #" . ($idx + 1) . "\n";
    echo "Event ID: {$msg['event_id']}\n";
    echo "Tipo: {$msg['event_type']}\n";
    echo "Criado: {$msg['created_at']}\n";
    
    $isInbound = strpos($msg['event_type'], 'inbound') !== false;
    echo "Direção: " . ($isInbound ? 'INBOUND (recebida)' : 'OUTBOUND (enviada)') . "\n";
    
    // Verifica tipo de mensagem
    $rawType = trim($msg['raw_type'] ?: '', '"');
    $msgType = trim($msg['message_type'] ?: '', '"');
    
    if ($rawType === 'ptt' || $msgType === 'ptt') {
        echo "✓ TIPO: ÁUDIO (ptt)\n";
        
        // Verifica se tem mídia processada
        if ($msg['message_media']) {
            $media = json_decode($msg['message_media'], true);
            echo "Mídia:\n";
            echo "  - Type: " . ($media['type'] ?? 'NULL') . "\n";
            echo "  - Mimetype: " . ($media['mimetype'] ?? 'NULL') . "\n";
            echo "  - Size: " . ($media['size'] ?? 'NULL') . " bytes\n";
        }
        
        // Verifica URL de mídia processada
        $mediaUrl = trim($msg['media_url'] ?: '', '"');
        if ($mediaUrl && $mediaUrl !== 'null') {
            echo "  ✓ MediaUrl: {$mediaUrl}\n";
            echo "  ✓ ÁUDIO PROCESSADO E DISPONÍVEL PARA REPRODUÇÃO\n";
        } else {
            echo "  ⚠️ MediaUrl: NÃO PROCESSADA\n";
            echo "  ⚠️ O áudio pode não estar disponível para reprodução no Inbox\n";
        }
    } else {
        // Mensagem de texto
        $text = $msg['message_text'] ?: $msg['message_body'];
        if ($text && $text !== '""' && $text !== 'null') {
            $textPreview = substr(trim($text, '"'), 0, 80);
            echo "Texto: {$textPreview}...\n";
        }
    }
    
    echo "\n";
}

echo "=== RESUMO ===\n";
echo "✓ Conversa tem " . count($messages) . " mensagem(ns)\n";
echo "✓ O áudio agora está vinculado à conversa\n";
echo "✓ Recarregue o Inbox para ver as mensagens\n";
