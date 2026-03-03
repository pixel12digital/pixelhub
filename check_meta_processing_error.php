<?php
/**
 * Script para verificar erros no processamento do webhook Meta
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

echo "=== VERIFICAR ERROS DE PROCESSAMENTO META ===\n\n";

$db = DB::getConnection();

// Pega o webhook Meta mais recente não processado
$stmt = $db->query("
    SELECT * FROM webhook_raw_logs
    WHERE event_type = 'meta_message'
    AND processed = 0
    ORDER BY created_at DESC
    LIMIT 1
");

$webhook = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$webhook) {
    echo "❌ Nenhum webhook Meta não processado encontrado\n";
    exit;
}

echo "1. Webhook encontrado:\n";
echo "   ID: {$webhook['id']}\n";
echo "   Data: {$webhook['created_at']}\n\n";

$payload = json_decode($webhook['payload_json'], true);

echo "2. Payload completo:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Tenta processar manualmente para ver o erro
echo "3. Tentando processar manualmente...\n\n";

try {
    // Simula o processamento do MetaWebhookController
    $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
    $value = $payload['entry'][0]['changes'][0]['value'] ?? [];
    
    if (!$message) {
        echo "❌ Nenhuma mensagem encontrada no payload\n";
        exit;
    }
    
    echo "Mensagem extraída:\n";
    echo "   From: {$message['from']}\n";
    echo "   Text: " . ($message['text']['body'] ?? 'N/A') . "\n";
    echo "   ID: {$message['id']}\n\n";
    
    // Verifica se EventIngestionService existe
    if (!class_exists('PixelHub\Services\EventIngestionService')) {
        echo "❌ EventIngestionService não encontrado\n";
        exit;
    }
    
    echo "✅ EventIngestionService encontrado\n";
    
    // Tenta ingerir
    $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
    echo "   Phone Number ID: {$phoneNumberId}\n\n";
    
    // Normaliza payload
    $normalized = [
        'id' => $message['id'],
        'from' => $message['from'],
        'timestamp' => $message['timestamp'] ?? time(),
        'type' => $message['type'] ?? 'text',
        'text' => $message['text']['body'] ?? '',
        'message' => [
            'from' => $message['from'],
            'timestamp' => $message['timestamp'] ?? time(),
            'id' => $message['id'],
            'body' => $message['text']['body'] ?? ''
        ],
        '_meta' => [
            'phone_number_id' => $phoneNumberId,
            'display_phone_number' => $value['metadata']['display_phone_number'] ?? null,
            'profile_name' => $value['contacts'][0]['profile']['name'] ?? null
        ]
    ];
    
    echo "Payload normalizado:\n";
    echo json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "Chamando EventIngestionService::ingest()...\n";
    
    $eventId = \PixelHub\Services\EventIngestionService::ingest([
        'event_type' => 'whatsapp.inbound.message',
        'source_system' => 'meta_official',
        'payload' => $normalized,
        'tenant_id' => null,
        'process_media_sync' => false,
        'metadata' => [
            'phone_number_id' => $phoneNumberId,
            'provider_type' => 'meta_official',
            'raw_message_id' => $message['id']
        ]
    ]);
    
    echo "✅ Evento ingerido! Event ID: {$eventId}\n\n";
    
    // Marca webhook como processado
    $db->prepare("UPDATE webhook_raw_logs SET processed = 1 WHERE id = ?")->execute([$webhook['id']]);
    
    echo "✅ Webhook marcado como processado\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO ao processar:\n";
    echo "   Mensagem: {$e->getMessage()}\n";
    echo "   Arquivo: {$e->getFile()}:{$e->getLine()}\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== FIM ===\n";
