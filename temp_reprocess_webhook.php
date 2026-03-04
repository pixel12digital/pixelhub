<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Services\EventIngestionService;

echo "=== REPROCESSAMENTO MANUAL DE WEBHOOKS ===\n\n";

// Pegar 1 webhook não processado do tipo chat (mensagem real)
$db = DB::getConnection();
$stmt = $db->query("
    SELECT id, received_at, event_type, payload_json
    FROM webhook_raw_logs
    WHERE processed = 0
      AND event_type = 'message'
      AND payload_json LIKE '%\"type\":\"chat\"%'
      AND received_at >= '2026-03-04 12:00:00'
    ORDER BY received_at DESC
    LIMIT 1
");
$webhook = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$webhook) {
    echo "❌ Nenhum webhook tipo 'chat' não processado encontrado\n";
    exit;
}

echo "Webhook ID: {$webhook['id']}\n";
echo "Recebido em: {$webhook['received_at']}\n\n";

$payload = json_decode($webhook['payload_json'], true);

if (!$payload) {
    echo "❌ Erro ao decodificar JSON\n";
    exit;
}

echo "Payload decodificado com sucesso\n";
echo "Event: " . ($payload['event'] ?? 'N/A') . "\n";
echo "From: " . ($payload['from'] ?? 'N/A') . "\n";
echo "Session ID: " . ($payload['session']['id'] ?? 'N/A') . "\n";
echo "Message Type: " . ($payload['raw']['payload']['type'] ?? 'N/A') . "\n\n";

// Tentar processar
echo "Tentando processar via EventIngestionService...\n\n";

try {
    $eventId = EventIngestionService::ingest([
        'event_type' => 'whatsapp.inbound.message',
        'source_system' => 'wpp_gateway',
        'payload' => $payload,
        'tenant_id' => null, // Será resolvido automaticamente
        'process_media_sync' => false,
        'metadata' => [
            'channel_id' => $payload['session']['id'] ?? null,
            'raw_event_type' => 'message',
            'manual_reprocess' => true
        ]
    ]);
    
    if ($eventId) {
        echo "✓ SUCESSO! Event ID: {$eventId}\n\n";
        
        // Marcar como processado
        $updateStmt = $db->prepare("
            UPDATE webhook_raw_logs 
            SET processed = 1, event_id = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$eventId, $webhook['id']]);
        
        echo "✓ Webhook marcado como processado\n";
        
        // Verificar se criou conversa
        $convStmt = $db->prepare("
            SELECT conversation_id FROM communication_events WHERE event_id = ?
        ");
        $convStmt->execute([$eventId]);
        $event = $convStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($event && $event['conversation_id']) {
            echo "✓ Conversa criada/atualizada: ID {$event['conversation_id']}\n";
        } else {
            echo "⚠️  Evento criado mas sem conversation_id\n";
        }
    } else {
        echo "❌ EventIngestionService retornou NULL\n";
    }
    
} catch (\Throwable $e) {
    echo "❌ ERRO ao processar:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM DO REPROCESSAMENTO ===\n";
