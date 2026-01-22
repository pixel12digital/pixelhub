<?php

require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';

\PixelHub\Core\Env::load();

echo "=== TESTE: Simular extractChannelInfo para evento 7062 ===\n\n";

// Payload do evento 7062 (mensagem 252525, ImobSites)
$payload = [
    "spec_version" => "1.0",
    "event" => "message",
    "session" => ["id" => "ImobSites", "name" => "ImobSites"],
    "message" => [
        "id" => "false_10523374551225@lid_A57E55334F93D5524A257409E0770274",
        "from" => "10523374551225@lid",
        "to" => "554797146908@c.us",
        "text" => "252525",
        "timestamp" => 1768526651
    ],
    "raw" => [
        "provider" => "wppconnect",
        "payload" => [
            "event" => "onmessage",
            "session" => "ImobSites",
            "from" => "10523374551225@lid",
            "to" => "554797146908@c.us",
            "body" => "252525",
            "notifyName" => "Servpro"
        ]
    ]
];

$metadata = [
    "channel_id" => "ImobSites",
    "raw_event_type" => "message"
];

$eventData = [
    'event_type' => 'whatsapp.inbound.message',
    'source_system' => 'wpp_gateway',
    'tenant_id' => 2,
    'payload' => $payload,
    'metadata' => $metadata
];

echo "Testando resolveConversation...\n";
echo "Event Type: {$eventData['event_type']}\n";
echo "Channel ID: {$metadata['channel_id']}\n";
echo "From: {$payload['message']['from']}\n";
echo "\n";

try {
    $conversation = \PixelHub\Services\ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "✅ CONVERSA RESOLVIDA:\n";
        echo "  ID: {$conversation['id']}\n";
        echo "  Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
        echo "  Session ID: " . ($conversation['session_id'] ?: 'NULL') . "\n";
        echo "  Contact External ID: " . ($conversation['contact_external_id'] ?: 'NULL') . "\n";
        echo "  Remote Key: " . ($conversation['remote_key'] ?: 'NULL') . "\n";
        echo "  Contact Key: " . ($conversation['contact_key'] ?: 'NULL') . "\n";
        echo "  Thread Key: " . ($conversation['thread_key'] ?: 'NULL') . "\n";
    } else {
        echo "❌ CONVERSA NÃO RESOLVIDA (retornou NULL)\n";
        echo "  Isso significa que extractChannelInfo() retornou NULL\n";
    }
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";








