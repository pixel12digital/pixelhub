<?php
/**
 * Diagnóstico: Por que mensagens do Robson (4234) não aparecem corretamente no Inbox?
 *
 * Sintomas:
 * - Mensagem enviada 13:28 ("testa novamente Robson...") não aparece
 * - Imagem recebida 13:29 não visualiza
 * - Áudio recebido 13:29: placeholder mas não dá play
 *
 * Execução: php database/diagnostico-inbox-robson-4234.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

$sufixo = '4234'; // Final do telefone
$padroes = ['%4234', '%4234@%', '%8799884234%', '%558799884234%'];

echo "=== DIAGNÓSTICO INBOX: Robson (tel. final 4234) ===\n\n";
echo "Buscando conversas com contato terminando em 4234...\n\n";

// 1. CONVERSAS
echo "1. CONVERSAS (tabela conversations):\n";
$placeholders = implode(' OR ', array_fill(0, count($padroes), 'contact_external_id LIKE ?'));
$stmt = $db->prepare("
    SELECT id, conversation_key, contact_external_id, contact_name, tenant_id, 
           channel_id, status, COALESCE(is_incoming_lead, 0) as is_incoming_lead, last_message_at, message_count, created_at
    FROM conversations 
    WHERE channel_type = 'whatsapp' 
    AND ({$placeholders})
    ORDER BY last_message_at DESC
");
$stmt->execute($padroes);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "   ❌ NENHUMA conversa encontrada para 4234.\n";
    echo "   → Verificar se o número está em outro formato (ex: 558799884234@c.us)\n\n";
    exit(1);
}

$conv = $conversations[0];
$convId = (int) $conv['id'];
$threadId = "whatsapp_{$convId}";

echo "   ✓ Conversa encontrada:\n";
echo "   - id={$conv['id']} | thread_id={$threadId}\n";
echo "   - contact_external_id={$conv['contact_external_id']}\n";
echo "   - contact_name=" . ($conv['contact_name'] ?: 'NULL') . "\n";
echo "   - tenant_id=" . ($conv['tenant_id'] ?: 'NULL') . " | channel_id=" . ($conv['channel_id'] ?: 'NULL') . "\n";
echo "   - last_message_at=" . ($conv['last_message_at'] ?: 'NULL') . "\n";
echo "   - is_incoming_lead=" . ($conv['is_incoming_lead'] ?: '0') . "\n\n";

// 2. EVENTOS (últimas 24h) desta conversa
echo "2. EVENTOS (communication_events) da conversa {$convId} (últimas 24h):\n\n";

$stmt = $db->prepare("
    SELECT id, event_id, event_type, conversation_id, status, created_at,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_field,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_field,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as msg_type,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text_preview,
           JSON_EXTRACT(payload, '$.message') as message_preview
    FROM communication_events 
    WHERE conversation_id = ?
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at ASC
");
$stmt->execute([$convId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM evento nas últimas 24h.\n";
    echo "   → A conversa pode estar vazia ou os eventos estão em outra conversation_id.\n\n";
} else {
    echo "   ✓ " . count($events) . " evento(s) encontrado(s):\n\n";

    foreach ($events as $e) {
        $dir = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
        $type = $e['msg_type'] ?: 'text';
        $text = $e['text_preview'] ? substr($e['text_preview'], 0, 50) : '-';
        echo "   - id={$e['id']} | event_id={$e['event_id']}\n";
        echo "     {$e['created_at']} | {$dir} | type={$type} | from={$e['from_field']} | to={$e['to_field']}\n";
        echo "     text_preview: {$text}\n";

        // Verifica mídia
        $stmtMedia = $db->prepare("SELECT id, media_type, mime_type, stored_path, file_name, file_size FROM communication_media WHERE event_id = ?");
        $stmtMedia->execute([$e['event_id']]);
        $medias = $stmtMedia->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($medias)) {
            $storageRoot = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
            foreach ($medias as $m) {
                $fullPath = $storageRoot . '/' . $m['stored_path'];
                $exists = file_exists($fullPath) ? '✓' : '❌';
                echo "     MEDIA: {$m['media_type']} | {$m['stored_path']} | file_exists={$exists}\n";
            }
        } else {
            if (in_array($type, ['image', 'audio', 'ptt', 'voice'])) {
                echo "     MEDIA: ❌ Nenhum registro em communication_media (mídia não baixada/armazenada?)\n";
            }
        }
        echo "\n";
    }
}

// 3. Busca por evento de texto outbound ~13:28
echo "3. EVENTO OUTBOUND (mensagem enviada 13:28) - 'testa novamente Robson':\n";
echo "   (buscando por conversation_id E por payload.to contendo 4234, pois outbound pode não ter conversation_id)\n\n";

$stmt = $db->prepare("
    SELECT event_id, event_type, conversation_id, created_at, 
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text,
           JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_field
    FROM communication_events 
    WHERE event_type = 'whatsapp.outbound.message'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    AND (
        conversation_id = ?
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
        OR JSON_EXTRACT(payload, '$.to') LIKE ?
    )
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$convId, '%4234%', '%558799884234%']);
$outbounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($outbounds)) {
    echo "   ❌ Nenhum evento outbound encontrado.\n";
    echo "   → A mensagem enviada pelo usuário NÃO foi persistida no banco.\n";
    echo "   → Verificar: EventIngestionService::ingest() após send; logs [CommunicationHub::send]\n\n";
} else {
    echo "   ✓ " . count($outbounds) . " outbound(s) encontrado(s):\n";
    foreach ($outbounds as $o) {
        $convIdNote = $o['conversation_id'] ? "conv_id={$o['conversation_id']}" : "conv_id=NULL (pode não aparecer no Inbox!)";
        echo "   - {$o['created_at']} | {$convIdNote} | to={$o['to_field']} | " . substr($o['text'] ?? '', 0, 50) . "\n";
    }
    echo "\n";
}

// 4. Eventos de mídia inbound (imagem, áudio)
echo "4. EVENTOS INBOUND com mídia (imagem/áudio) ~13:29:\n";
$stmt = $db->prepare("
    SELECT e.event_id, e.event_type, e.created_at,
           JSON_UNQUOTE(JSON_EXTRACT(e.payload, '$.type')) as msg_type,
           m.id as media_id, m.media_type, m.stored_path, m.file_name
    FROM communication_events e
    LEFT JOIN communication_media m ON m.event_id = e.event_id
    WHERE e.conversation_id = ?
    AND e.event_type = 'whatsapp.inbound.message'
    AND e.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(e.payload, '$.type')) IN ('image', 'sticker', 'audio', 'ptt', 'voice')
        OR m.id IS NOT NULL
    )
    ORDER BY e.created_at ASC
");
$stmt->execute([$convId]);
$mediaEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($mediaEvents)) {
    echo "   ❌ Nenhum evento inbound de mídia encontrado.\n";
    echo "   → A imagem e o áudio podem não ter chegado via webhook.\n";
    echo "   → Ou o tipo no payload é diferente (ex: imageMessage em message.*)\n\n";
} else {
    echo "   ✓ " . count($mediaEvents) . " evento(s) de mídia:\n";
    $storageRoot = realpath(__DIR__ . '/../storage') ?: __DIR__ . '/../storage';
    foreach ($mediaEvents as $me) {
        $path = $storageRoot . '/' . ($me['stored_path'] ?? '');
        $exists = $me['stored_path'] && file_exists($path) ? '✓' : '❌';
        echo "   - {$me['created_at']} | type={$me['msg_type']} | media_type={$me['media_type']} | path={$me['stored_path']} | file_exists={$exists}\n";
    }
    echo "\n";
}

// 5. Resumo
echo "=== RESUMO ===\n";
echo "thread_id para usar: {$threadId}\n";
echo "conversation_id: {$convId}\n";
echo "Próximo passo: verificar resposta de GET /communication-hub/thread-data?thread_id={$threadId}&channel=whatsapp\n";
echo "E testar media.url no Network (F12) ou com curl.\n";
