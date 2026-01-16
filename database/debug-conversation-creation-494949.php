<?php

/**
 * Script para debugar por que a conversa não está sendo criada
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/PhoneNormalizer.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== DEBUG: POR QUE CONVERSA NÃO É CRIADA ===\n\n";

// Busca evento
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        payload,
        metadata
    FROM communication_events
    WHERE JSON_EXTRACT(payload, '$.message.text') LIKE '%494949%'
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch();

if (!$event) {
    echo "Evento não encontrado!\n";
    exit(1);
}

$payload = json_decode($event['payload'], true);
$metadata = json_decode($event['metadata'] ?? '{}', true);

echo "Evento ID: {$event['event_id']}\n";
echo "Tipo: {$event['event_type']}\n";
echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
echo "\n";

echo "Payload keys: " . implode(', ', array_keys($payload)) . "\n";
echo "Payload.message keys: " . (isset($payload['message']) ? implode(', ', array_keys($payload['message'])) : 'N/A') . "\n";
echo "\n";

// Extrai informações manualmente
$from = $payload['message']['from'] ?? $payload['from'] ?? null;
$to = $payload['message']['to'] ?? $payload['to'] ?? null;
$text = $payload['message']['text'] ?? $payload['text'] ?? null;

echo "from: " . ($from ?: 'NULL') . "\n";
echo "to: " . ($to ?: 'NULL') . "\n";
echo "text: " . ($text ?: 'NULL') . "\n";
echo "\n";

// Simula o que o ConversationService faz
echo "=== SIMULANDO extractChannelInfo ===\n";

$eventType = $event['event_type'];
$channelType = null;
if (strpos($eventType, 'whatsapp.') === 0) {
    $channelType = 'whatsapp';
}

echo "channelType detectado: " . ($channelType ?: 'NULL') . "\n";

if ($channelType === 'whatsapp') {
    $direction = strpos($eventType, 'inbound') !== false ? 'inbound' : 'outbound';
    echo "direction: {$direction}\n";
    
    if ($direction === 'inbound') {
        $rawFrom = $payload['message']['from'] 
            ?? $payload['from'] 
            ?? null;
        
        echo "rawFrom: " . ($rawFrom ?: 'NULL') . "\n";
        
        if ($rawFrom) {
            // Verifica se é JID numérico
            if (strpos($rawFrom, '@c.us') !== false || strpos($rawFrom, '@s.whatsapp.net') !== false) {
                $digitsOnly = preg_replace('/@.*$/', '', $rawFrom);
                $digitsOnly = preg_replace('/[^0-9]/', '', $digitsOnly);
                
                echo "digitsOnly: {$digitsOnly}\n";
                
                if (strlen($digitsOnly) >= 10) {
                    $normalized = \PixelHub\Services\PhoneNormalizer::toE164OrNull($digitsOnly);
                    echo "normalized (E.164): " . ($normalized ?: 'NULL') . "\n";
                }
            }
        }
    }
}

echo "\n";

// Tenta criar conversa manualmente
echo "=== TENTANDO CRIAR CONVERSA MANUALMENTE ===\n";

$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => 'wpp_gateway',
    'tenant_id' => $event['tenant_id'],
    'payload' => $payload,
    'metadata' => $metadata
];

try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "SUCESSO: Conversa criada!\n";
        echo "ID: {$conversation['id']}\n";
        echo "Key: {$conversation['conversation_key']}\n";
    } else {
        echo "FALHA: resolveConversation retornou NULL\n";
        echo "Verifique os logs de erro acima.\n";
    }
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

