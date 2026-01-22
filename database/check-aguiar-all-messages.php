<?php

/**
 * Script para verificar TODAS as mensagens do Aguiar e por que não aparecem
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO TODAS AS MENSAGENS DO AGUIAR ===\n\n";

$contactPhone = '556993245042';
$channelId = 'pixel12digital';
$conversationId = 10;

// Busca TODOS os eventos do Aguiar com channel_id pixel12digital
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
    )
    ORDER BY ce.created_at ASC
");
$pattern = "%{$contactPhone}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$allEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtra por channel_id
$eventsWithChannel = [];
foreach ($allEvents as $event) {
    $payload = json_decode($event['payload'], true);
    $metadata = json_decode($event['metadata'] ?? '{}', true);
    
    $eventChannelId = $payload['channel_id'] 
        ?? $payload['channel'] 
        ?? $payload['session']['id'] 
        ?? $payload['session']['session']
        ?? $metadata['channel_id'] ?? null;
    
    if ($eventChannelId === $channelId) {
        $eventsWithChannel[] = $event;
    }
}

echo "Total de eventos encontrados: " . count($eventsWithChannel) . "\n\n";

// Analisa cada evento
$hasContent = 0;
$noContent = 0;
$hasMedia = 0;
$emptyMessages = [];

foreach ($eventsWithChannel as $idx => $event) {
    $payload = json_decode($event['payload'], true);
    $from = $payload['from'] ?? $payload['message']['from'] ?? 'NULL';
    $to = $payload['to'] ?? $payload['message']['to'] ?? 'NULL';
    $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? '';
    $text = trim($text);
    
    // Verifica se tem mídia
    $hasMediaInEvent = false;
    $mediaType = null;
    
    if (isset($payload['type']) || isset($payload['message']['type'])) {
        $mediaType = $payload['type'] ?? $payload['message']['type'] ?? null;
        if (in_array($mediaType, ['image', 'video', 'audio', 'document', 'ptt', 'sticker'])) {
            $hasMediaInEvent = true;
            $hasMedia++;
        }
    }
    
    // Verifica se tem conteúdo de texto
    $hasTextContent = !empty($text);
    
    if ($hasTextContent) {
        $hasContent++;
    } else {
        $noContent++;
        if (!$hasMediaInEvent) {
            $emptyMessages[] = [
                'event_id' => $event['event_id'],
                'created_at' => $event['created_at'],
                'type' => $mediaType ?? 'unknown',
                'direction' => $event['event_type'] === 'whatsapp.inbound.message' ? 'INBOUND' : 'OUTBOUND'
            ];
        }
    }
    
    $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'INBOUND' : 'OUTBOUND';
    $textPreview = mb_substr($text, 0, 80);
    
    echo "Evento #" . ($idx + 1) . " ({$direction}):\n";
    echo "  ID: {$event['event_id']}\n";
    echo "  Created: {$event['created_at']}\n";
    echo "  Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "  Texto: " . ($textPreview ?: '[VAZIO]') . "\n";
    echo "  Mídia: " . ($hasMediaInEvent ? "SIM ({$mediaType})" : "NÃO") . "\n";
    echo "  Será exibido: " . (($hasTextContent || $hasMediaInEvent) ? "SIM" : "NÃO (sem conteúdo nem mídia)") . "\n";
    echo "\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "RESUMO:\n";
echo "  Total de eventos: " . count($eventsWithChannel) . "\n";
echo "  Eventos com conteúdo de texto: {$hasContent}\n";
echo "  Eventos com mídia: {$hasMedia}\n";
echo "  Eventos sem conteúdo nem mídia: {$noContent}\n";
echo "  Eventos que serão exibidos: " . ($hasContent + $hasMedia) . "\n";
echo "  Eventos que NÃO serão exibidos (vazios): " . count($emptyMessages) . "\n";

if (!empty($emptyMessages)) {
    echo "\nEventos vazios (não serão exibidos):\n";
    foreach ($emptyMessages as $empty) {
        echo "  - {$empty['event_id']} ({$empty['created_at']}) - Tipo: {$empty['type']} - {$empty['direction']}\n";
    }
}

echo "\n✅ Análise concluída!\n";

