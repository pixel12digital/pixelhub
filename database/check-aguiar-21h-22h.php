<?php

/**
 * Script para verificar eventos de comunicação do Aguiar entre 21:10 e 22:00
 * Canal: pixel12digital
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO EVENTOS DO AGUIAR ENTRE 21:10 E 22:00 ===\n\n";

$contactPhone = '556993245042';
$channelId = 'pixel12digital';
$startTime = '2026-01-19 21:10:00';
$endTime = '2026-01-19 22:00:00';

// 1. Busca conversa do Aguiar
echo "1. Buscando conversa do Aguiar...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        channel_id,
        remote_key,
        message_count,
        last_message_at,
        created_at
    FROM conversations
    WHERE contact_external_id LIKE ? OR contact_name LIKE '%Aguiar%'
    ORDER BY last_message_at DESC
    LIMIT 1
");
$stmt->execute(["%{$contactPhone}%"]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    echo "❌ Conversa não encontrada\n";
    exit(1);
}

echo "   ✅ Conversa encontrada:\n";
echo "      ID: {$conversation['id']}\n";
echo "      Nome: {$conversation['contact_name']}\n";
echo "      Contato: {$conversation['contact_external_id']}\n";
echo "      Tenant ID: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
echo "      Channel ID: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
echo "      Message Count: {$conversation['message_count']}\n";
echo "\n";

// 2. Busca TODOS os eventos do Aguiar (sem filtro de horário primeiro)
echo "2. Buscando TODOS os eventos do Aguiar...\n";
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

echo "   Total de eventos encontrados: " . count($allEvents) . "\n";
echo "\n";

// 3. Filtra eventos por channel_id pixel12digital
echo "3. Filtrando eventos com channel_id = 'pixel12digital'...\n";
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

echo "   Eventos com channel_id = 'pixel12digital': " . count($eventsWithChannel) . "\n";
echo "\n";

// 4. Filtra eventos no período 21:10 - 22:00
echo "4. Filtrando eventos entre {$startTime} e {$endTime}...\n";
$eventsInPeriod = [];
foreach ($eventsWithChannel as $event) {
    $createdAt = $event['created_at'];
    if ($createdAt >= $startTime && $createdAt <= $endTime) {
        $eventsInPeriod[] = $event;
    }
}

echo "   Eventos no período: " . count($eventsInPeriod) . "\n";
echo "\n";

// 5. Exibe detalhes dos eventos no período
if (!empty($eventsInPeriod)) {
    echo "5. Detalhes dos eventos no período:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($eventsInPeriod as $idx => $event) {
        $payload = json_decode($event['payload'], true);
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'NULL';
        $to = $payload['to'] ?? $payload['message']['to'] ?? 'NULL';
        $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? '';
        $textPreview = mb_substr($text, 0, 100);
        $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'INBOUND' : 'OUTBOUND';
        
        echo "   Evento #" . ($idx + 1) . ":\n";
        echo "      ID: {$event['event_id']}\n";
        echo "      Tipo: {$event['event_type']} ({$direction})\n";
        echo "      Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
        echo "      From: {$from}\n";
        echo "      To: {$to}\n";
        echo "      Texto: " . ($textPreview ?: '[vazio ou mídia]') . "\n";
        echo "      Created: {$event['created_at']}\n";
        echo "\n";
    }
} else {
    echo "5. Nenhum evento encontrado no período especificado.\n";
    echo "\n";
}

// 6. Compara com message_count da conversa
echo "6. Comparação:\n";
echo "   Message Count na tabela conversations: {$conversation['message_count']}\n";
echo "   Total de eventos com channel_id 'pixel12digital': " . count($eventsWithChannel) . "\n";
echo "   Eventos no período 21:10-22:00: " . count($eventsInPeriod) . "\n";
echo "\n";

// 7. Verifica se há eventos fora do período que podem estar sendo contados
echo "7. Distribuição de eventos por horário:\n";
$hourlyDistribution = [];
foreach ($eventsWithChannel as $event) {
    $createdAt = $event['created_at'];
    $date = new DateTime($createdAt);
    $hour = $date->format('Y-m-d H:00:00');
    
    if (!isset($hourlyDistribution[$hour])) {
        $hourlyDistribution[$hour] = 0;
    }
    $hourlyDistribution[$hour]++;
}

ksort($hourlyDistribution);
foreach ($hourlyDistribution as $hour => $count) {
    echo "   {$hour}: {$count} evento(s)\n";
}

echo "\n";
echo "✅ Verificação concluída!\n";

