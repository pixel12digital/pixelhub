<?php
/**
 * Analisa o mapeamento do payload do gateway para o Hub
 */

// Payload que o gateway envia (baseado no buildHubPayload)
$gatewayPayload = [
    "spec_version" => "1.0",
    "event" => "message",  // <-- O gateway envia "message"
    "session" => [
        "id" => "Pixel12 Digital",
        "name" => "Pixel12 Digital"
    ],
    "event_id" => "90c9089f-520b-4617-b72a-ce880c75739c",
    "correlation_id" => "9858a507-cc4c-4632-8f92-462535eab504",
    "timestamp" => "2026-01-14T21:35:39Z",
    "raw" => [
        "provider" => "wppconnect",
        "payload" => [
            "event" => "message",
            "sessionId" => "Pixel12 Digital",
            "message" => [
                "id" => "gwtest-123",
                "from" => "5599999999999",
                "body" => "TESTE",
                "timestamp" => 1234567890
            ]
        ]
    ],
    "message" => [
        "id" => "gwtest-123",
        "from" => "5599999999999",
        "to" => "Pixel12 Digital",
        "text" => "TESTE",
        "timestamp" => "2026-01-14T21:35:39Z"
    ]
];

echo "=== Análise do Mapeamento ===\n\n";

// Simula o que o WhatsAppWebhookController faz
$eventType = $gatewayPayload['event'] ?? $gatewayPayload['type'] ?? null;
echo "1. eventType extraído: " . ($eventType ?: 'NULL') . "\n";

// Mapeamento (do código do controller)
$mapping = [
    'message' => 'whatsapp.inbound.message',
    'message.ack' => 'whatsapp.delivery.ack',
    'connection.update' => 'whatsapp.connection.update',
    'message.sent' => 'whatsapp.outbound.message',
    'message_sent' => 'whatsapp.outbound.message',
    'sent' => 'whatsapp.outbound.message',
    'status' => 'whatsapp.delivery.status',
];

$internalEventType = $mapping[$eventType] ?? null;
echo "2. internalEventType mapeado: " . ($internalEventType ?: 'NULL') . "\n";

// Extração de channel_id
$channelId = $gatewayPayload['channel'] 
    ?? $gatewayPayload['channelId'] 
    ?? $gatewayPayload['session']['id'] 
    ?? $gatewayPayload['session']['session']
    ?? $gatewayPayload['data']['session']['id'] ?? null
    ?? $gatewayPayload['data']['session']['session'] ?? null
    ?? $gatewayPayload['data']['channel'] ?? null
    ?? null;
echo "3. channel_id extraído: " . ($channelId ?: 'NULL') . "\n";

// Extração de from
$from = $gatewayPayload['from'] 
    ?? $gatewayPayload['message']['from'] 
    ?? $gatewayPayload['data']['from'] ?? null;
echo "4. from extraído: " . ($from ?: 'NULL') . "\n";

// Extração de message_id
$messageId = $gatewayPayload['id'] 
    ?? $gatewayPayload['messageId'] 
    ?? $gatewayPayload['message_id'] 
    ?? $gatewayPayload['message']['id'] ?? null;
echo "5. message_id extraído: " . ($messageId ?: 'NULL') . "\n";

// Extração de correlation_id
$correlationId = $gatewayPayload['correlation_id'] 
    ?? $gatewayPayload['correlationId'] 
    ?? $gatewayPayload['trace_id'] 
    ?? $gatewayPayload['traceId'] ?? null;
echo "6. correlation_id extraído: " . ($correlationId ?: 'NULL') . "\n";

echo "\n=== Conclusão ===\n";
if ($internalEventType && $channelId && $from && $messageId) {
    echo "✅ Payload está no formato correto - todos os campos foram extraídos\n";
} else {
    echo "❌ Payload tem problemas:\n";
    if (!$internalEventType) echo "  - eventType não foi mapeado\n";
    if (!$channelId) echo "  - channel_id não foi extraído\n";
    if (!$from) echo "  - from não foi extraído\n";
    if (!$messageId) echo "  - message_id não foi extraído\n";
}

