<?php

/**
 * Script para localizar e criar conversa para mensagem 494949
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== LOCALIZANDO MENSAGEM 494949 ===\n\n";

// 1. Busca evento com mensagem 494949
echo "1. Buscando evento com mensagem '494949'...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        created_at,
        JSON_EXTRACT(metadata, '$.channel_id') as channel_id,
        JSON_EXTRACT(payload, '$.from') as from_raw,
        JSON_EXTRACT(payload, '$.to') as to_raw,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.message.to') as message_to,
        JSON_EXTRACT(payload, '$.message.text') as message_text,
        payload,
        metadata
    FROM communication_events
    WHERE JSON_EXTRACT(payload, '$.message.text') LIKE '%494949%'
       OR JSON_EXTRACT(payload, '$.text') LIKE '%494949%'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$events = $stmt->fetchAll();

if (count($events) === 0) {
    echo "Nenhum evento encontrado com mensagem '494949'!\n";
    exit(1);
}

echo "Eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    $from = trim($event['from_raw'] ?: $event['message_from'] ?: '', '"');
    $to = trim($event['to_raw'] ?: $event['message_to'] ?: '', '"');
    $text = trim($event['message_text'] ?: '', '"');
    $channelId = trim($event['channel_id'] ?: '', '"');
    
    echo "--- Evento ID: {$event['event_id']} (PK: {$event['id']}) ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "channel_id: " . ($channelId ?: 'NULL') . "\n";
    echo "from: " . ($from ?: 'NULL') . "\n";
    echo "to: " . ($to ?: 'NULL') . "\n";
    echo "text: {$text}\n";
    echo "created_at: {$event['created_at']}\n";
    
    // Normaliza número
    $normalizedFrom = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $from));
    echo "from normalizado: " . ($normalizedFrom ?: 'NULL') . "\n";
    
    // Verifica se existe conversa
    $convStmt = $db->prepare("
        SELECT id, conversation_key, contact_external_id, remote_key, message_count
        FROM conversations
        WHERE contact_external_id LIKE ?
           OR remote_key LIKE ?
        LIMIT 5
    ");
    $pattern1 = "%{$normalizedFrom}%";
    $pattern2 = "%tel:{$normalizedFrom}%";
    $convStmt->execute([$pattern1, $pattern2]);
    $conversations = $convStmt->fetchAll();
    
    if (count($conversations) > 0) {
        echo "  -> Conversas encontradas: " . count($conversations) . "\n";
        foreach ($conversations as $conv) {
            echo "     - ID: {$conv['id']}, key: {$conv['conversation_key']}, contact: {$conv['contact_external_id']}, messages: {$conv['message_count']}\n";
        }
    } else {
        echo "  -> NENHUMA CONVERSA ENCONTRADA!\n";
        echo "  -> Vou criar a conversa agora...\n";
        
        // Cria conversa usando ConversationService
        require_once __DIR__ . '/../src/Services/ConversationService.php';
        
        $eventData = [
            'event_type' => $event['event_type'],
            'source_system' => 'wpp_gateway',
            'tenant_id' => $event['tenant_id'],
            'payload' => json_decode($event['payload'], true),
            'metadata' => json_decode($event['metadata'] ?? '{}', true)
        ];
        
        $conversation = \PixelHub\Services\ConversationService::resolveConversation($eventData);
        
        if ($conversation) {
            echo "  -> Conversa criada com sucesso!\n";
            echo "     - ID: {$conversation['id']}\n";
            echo "     - Key: {$conversation['conversation_key']}\n";
            echo "     - Contact: {$conversation['contact_external_id']}\n";
        } else {
            echo "  -> ERRO: Não foi possível criar a conversa!\n";
        }
    }
    echo "\n";
}

