<?php
/**
 * Script para verificar eventos de grupo do Ponto Do Golfe
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO EVENTOS DE GRUPO: PONTO DO GOLFE ===\n\n";

$lidId = '130894027333804';
$conversationId = 6;

// Busca eventos de grupo recentes
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        ce.payload,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as event_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_EXTRACT(ce.payload, '$.raw.payload.author') as author,
        JSON_EXTRACT(ce.payload, '$.raw.payload.participant') as participant,
        JSON_EXTRACT(ce.payload, '$.raw.payload.chatId') as chatId
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(ce.metadata, '$.channel_id') = 'pixel12digital'
        OR JSON_EXTRACT(ce.payload, '$.session.id') = 'pixel12digital'
    )
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%@g.us%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%@g.us%'
    )
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$stmt->execute();
$groupEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos de grupo encontrados: " . count($groupEvents) . "\n\n";

foreach ($groupEvents as $idx => $event) {
    $eventNum = $idx + 1;
    $payload = json_decode($event['payload'], true);
    $rawPayload = $payload['raw']['payload'] ?? [];
    
    $from = $event['event_from'] ?: $event['message_from'] ?: 'NULL';
    $author = $rawPayload['author'] ?? null;
    $participant = $rawPayload['participant'] ?? null;
    $chatId = $rawPayload['chatId'] ?? null;
    
    echo "Evento #{$eventNum}:\n";
    echo "  ID: {$event['event_id']}\n";
    echo "  Created: {$event['created_at']}\n";
    echo "  From (grupo): {$from}\n";
    echo "  Author: " . ($author ?: 'NULL') . "\n";
    echo "  Participant: " . ($participant ?: 'NULL') . "\n";
    echo "  Chat ID: " . ($chatId ?: 'NULL') . "\n";
    
    // Verifica se o author/participant contém o LID
    $authorStr = $author ? trim($author, '"') : '';
    $participantStr = $participant ? trim($participant, '"') : '';
    
    if (strpos($authorStr, $lidId) !== false || strpos($participantStr, $lidId) !== false) {
        echo "  ✅ CONTÉM O LID no author/participant!\n";
    }
    
    // Verifica se o chatId corresponde
    $chatIdStr = $chatId ? trim($chatId, '"') : '';
    if (strpos($chatIdStr, $lidId) !== false) {
        echo "  ✅ CONTÉM O LID no chatId!\n";
    }
    
    // Extrai texto se houver
    $text = $payload['text'] 
        ?? $payload['body'] 
        ?? $payload['message']['text'] ?? null
        ?? $payload['message']['body'] ?? null;
    
    if ($text) {
        $textPreview = mb_substr($text, 0, 100);
        echo "  Texto: {$textPreview}\n";
    } else {
        echo "  Texto: [vazio ou mídia]\n";
    }
    
    echo "\n";
}

// Verifica a conversa
echo "\n=== INFORMAÇÕES DA CONVERSA ===\n";
$stmt = $db->prepare("
    SELECT 
        id,
        contact_external_id,
        contact_name,
        channel_id,
        remote_key
    FROM conversations
    WHERE id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "Conversa ID: {$conversation['id']}\n";
    echo "Contact External ID: {$conversation['contact_external_id']}\n";
    echo "Contact Name: {$conversation['contact_name']}\n";
    echo "Channel ID: {$conversation['channel_id']}\n";
    echo "Remote Key: {$conversation['remote_key']}\n";
    
    echo "\n⚠️  PROBLEMA IDENTIFICADO:\n";
    echo "   A conversa foi criada com contact_external_id = '{$conversation['contact_external_id']}'\n";
    echo "   Mas os eventos são de grupos (@g.us), não de contatos individuais\n";
    echo "   Os eventos têm author/participant que podem corresponder ao @lid\n";
    echo "   Mas a busca atual não verifica author/participant em grupos\n";
}

echo "\n✅ Investigação concluída!\n";

