<?php

/**
 * Script para criar conversa para mensagem 494949
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/PhoneNormalizer.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\ConversationService;

Env::load();

$db = DB::getConnection();

echo "=== CRIANDO CONVERSA PARA MENSAGEM 494949 ===\n\n";

// 1. Busca evento
echo "1. Buscando evento com mensagem '494949'...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        created_at,
        payload,
        metadata
    FROM communication_events
    WHERE JSON_EXTRACT(payload, '$.message.text') LIKE '%494949%'
       OR JSON_EXTRACT(payload, '$.text') LIKE '%494949%'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch();

if (!$event) {
    echo "Evento não encontrado!\n";
    exit(1);
}

echo "Evento encontrado: ID {$event['event_id']} (PK: {$event['id']})\n";
echo "Tipo: {$event['event_type']}\n";
echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
echo "\n";

// 2. Prepara dados do evento
$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => 'wpp_gateway',
    'tenant_id' => $event['tenant_id'],
    'payload' => json_decode($event['payload'], true),
    'metadata' => json_decode($event['metadata'] ?? '{}', true)
];

echo "2. Chamando ConversationService::resolveConversation()...\n";

try {
    $conversation = ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "  -> Conversa criada/atualizada com sucesso!\n";
        echo "     - ID: {$conversation['id']}\n";
        echo "     - Key: {$conversation['conversation_key']}\n";
        echo "     - Contact: {$conversation['contact_external_id']}\n";
        echo "     - Remote Key: " . ($conversation['remote_key'] ?: 'NULL') . "\n";
        echo "     - Tenant ID: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
        echo "     - Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
        echo "     - is_incoming_lead: " . ($conversation['is_incoming_lead'] ?? 0) . "\n";
    } else {
        echo "  -> ERRO: resolveConversation retornou NULL\n";
        echo "     Verifique os logs para mais detalhes.\n";
    }
} catch (\Exception $e) {
    echo "  -> ERRO ao criar conversa: " . $e->getMessage() . "\n";
    echo "     Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n";

// 3. Verifica se a conversa foi criada
echo "3. Verificando se conversa foi criada...\n";
$from = $eventData['payload']['message']['from'] ?? $eventData['payload']['from'] ?? null;
if ($from) {
    $normalizedFrom = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $from));
    
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
        echo "  -> NENHUMA CONVERSA ENCONTRADA após tentativa de criação!\n";
    }
}

