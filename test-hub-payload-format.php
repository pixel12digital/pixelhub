<?php
/**
 * Testa o formato exato do payload que o gateway envia
 * Baseado no buildHubPayload do gateway
 */

// Simula o payload que o gateway constrói
$sessionId = "Pixel12 Digital";
$eventType = "message";
$correlationId = "9858a507-cc4c-4632-8f92-462535eab504";

$rawEvent = [
    "event" => "message",
    "sessionId" => "Pixel12 Digital",
    "message" => [
        "id" => "gwtest-123",
        "from" => "5599999999999",
        "body" => "TESTE",
        "timestamp" => 1234567890
    ]
];

// Payload que o gateway constrói (baseado no código do gateway)
$hubPayload = [
    "spec_version" => "1.0",
    "event" => $eventType,
    "session" => [
        "id" => $sessionId,
        "name" => $sessionId
    ],
    "event_id" => "90c9089f-520b-4617-b72a-ce880c75739c",
    "correlation_id" => $correlationId,
    "timestamp" => date('c', 1234567890),
    "raw" => [
        "provider" => "wppconnect",
        "payload" => $rawEvent
    ],
    "message" => [
        "id" => $rawEvent["message"]["id"],
        "from" => $rawEvent["message"]["from"],
        "to" => $sessionId,
        "text" => $rawEvent["message"]["body"],
        "timestamp" => date('c', $rawEvent["message"]["timestamp"])
    ]
];

echo "=== Payload que o Gateway Envia ===\n";
echo json_encode($hubPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Agora testa se o Hub consegue processar
require 'src/Core/DB.php';
require 'src/Core/Env.php';
require 'src/Services/EventIngestionService.php';

\PixelHub\Core\Env::load();

echo "=== Testando Ingestão ===\n";

try {
    $eventId = \PixelHub\Services\EventIngestionService::ingest([
        'event_type' => 'whatsapp.inbound.message',
        'source_system' => 'wpp_gateway',
        'payload' => $hubPayload,
        'tenant_id' => null, // Será resolvido pelo channel
        'metadata' => [
            'channel_id' => $sessionId
        ],
        'correlation_id' => $correlationId
    ]);
    
    echo "✅ Evento ingerido com sucesso!\n";
    echo "event_id: $eventId\n";
    
    // Verifica se foi salvo
    $db = \PixelHub\Core\DB::getConnection();
    $stmt = $db->prepare("SELECT id, event_id, status FROM communication_events WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($saved) {
        echo "✅ Evento salvo no banco:\n";
        echo json_encode($saved, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ Evento NÃO foi salvo no banco\n";
    }
    
} catch (\Exception $e) {
    echo "❌ ERRO na ingestão: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

