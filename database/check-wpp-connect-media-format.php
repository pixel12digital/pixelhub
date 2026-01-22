<?php
/**
 * Script para verificar formato de payload de mídias do WPP Connect
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== Verificação de Formato de Mídia - WPP Connect ===\n\n";

// Busca eventos recentes que podem ter mídia
echo "1. Buscando eventos recentes com possível mídia...\n\n";

$stmt = $db->query("
    SELECT event_id, event_type, created_at, payload, metadata
    FROM communication_events 
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC 
    LIMIT 10
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ⚠️  Nenhum evento encontrado\n";
    exit(0);
}

echo "   ✅ Encontrados " . count($events) . " eventos\n\n";

$mediaFound = false;

foreach ($events as $i => $event) {
    $payload = json_decode($event['payload'], true);
    if (!$payload) continue;
    
    // Verifica diferentes formatos para detectar mídia
    $type = $payload['type'] ?? $payload['message']['type'] ?? $payload['message']['message']['type'] ?? 'text';
    
    // Lista de tipos que indicam mídia
    $mediaTypes = ['audio', 'ptt', 'voice', 'image', 'video', 'document', 'sticker'];
    
    if (!in_array(strtolower($type), $mediaTypes)) {
        continue; // Pula mensagens de texto
    }
    
    $mediaFound = true;
    
    echo "   📎 Evento com mídia encontrado:\n";
    echo "      - Event ID: {$event['event_id']}\n";
    echo "      - Data: {$event['created_at']}\n";
    echo "      - Tipo: {$type}\n\n";
    
    // Extrai informações do payload
    echo "   📋 Estrutura do Payload:\n";
    echo "      - Keys principais: " . implode(', ', array_keys($payload)) . "\n";
    
    if (isset($payload['message'])) {
        echo "      - message.keys: " . implode(', ', array_keys($payload['message'])) . "\n";
        
        if (isset($payload['message']['message'])) {
            echo "      - message.message.keys: " . implode(', ', array_keys($payload['message']['message'])) . "\n";
        }
    }
    
    // Verifica onde está o mediaId/URL
    echo "\n   🔍 Buscando mediaId/URL:\n";
    
    $possibleMediaIds = [
        'payload.mediaId' => $payload['mediaId'] ?? null,
        'payload.media_id' => $payload['media_id'] ?? null,
        'payload.mediaUrl' => $payload['mediaUrl'] ?? null,
        'payload.media_url' => $payload['media_url'] ?? null,
        'payload.id' => $payload['id'] ?? null,
        'payload.messageId' => $payload['messageId'] ?? null,
        'message.mediaId' => $payload['message']['mediaId'] ?? null,
        'message.media_id' => $payload['message']['media_id'] ?? null,
        'message.mediaUrl' => $payload['message']['mediaUrl'] ?? null,
        'message.media_url' => $payload['message']['media_url'] ?? null,
        'message.id' => $payload['message']['id'] ?? null,
        'message.key.id' => $payload['message']['key']['id'] ?? null,
        'message.message.mediaKey' => $payload['message']['message']['mediaKey'] ?? null,
        'message.message.url' => $payload['message']['message']['url'] ?? null,
    ];
    
    foreach ($possibleMediaIds as $path => $value) {
        if ($value) {
            echo "      ✅ {$path}: {$value}\n";
        }
    }
    
    // Verifica mimetype
    echo "\n   📄 Buscando mimetype:\n";
    $possibleMimeTypes = [
        'payload.mimetype' => $payload['mimetype'] ?? null,
        'payload.mimeType' => $payload['mimeType'] ?? null,
        'message.mimetype' => $payload['message']['mimetype'] ?? null,
        'message.mimeType' => $payload['message']['mimeType'] ?? null,
    ];
    
    $foundMimeType = false;
    foreach ($possibleMimeTypes as $path => $value) {
        if ($value) {
            echo "      ✅ {$path}: {$value}\n";
            $foundMimeType = true;
        }
    }
    
    if (!$foundMimeType) {
        echo "      ⚠️  MimeType não encontrado\n";
    }
    
    // Verifica se já foi processado
    echo "\n   📦 Status de processamento:\n";
    $stmt = $db->prepare("SELECT * FROM communication_media WHERE event_id = ? LIMIT 1");
    $stmt->execute([$event['event_id']]);
    $media = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($media) {
        echo "      ✅ Mídia já processada:\n";
        echo "         - Tipo: {$media['media_type']}\n";
        echo "         - MIME: {$media['mime_type']}\n";
        echo "         - Caminho: " . ($media['stored_path'] ?? 'N/A') . "\n";
    } else {
        echo "      ❌ Mídia NÃO foi processada ainda\n";
        echo "         - Verifique logs do WhatsAppMediaService\n";
    }
    
    // Mostra um trecho do payload para debug
    echo "\n   📄 Payload (primeiros 500 chars):\n";
    $payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "      " . str_replace("\n", "\n      ", substr($payloadJson, 0, 500)) . "...\n";
    
    echo "\n   " . str_repeat("━", 70) . "\n\n";
    
    // Mostra apenas o primeiro evento com mídia para não poluir
    break;
}

if (!$mediaFound) {
    echo "   ⚠️  Nenhum evento com mídia encontrado nos últimos 10 eventos\n";
    echo "   💡 Dica: Envie uma mídia pelo WhatsApp e verifique novamente\n";
}

echo "\n=== Fim da Verificação ===\n";







