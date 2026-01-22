<?php

/**
 * Script para verificar por que extractChannelInfo retorna NULL
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/PhoneNormalizer.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO extractChannelInfo ===\n\n";

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

$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => 'wpp_gateway',
    'tenant_id' => $event['tenant_id'],
    'payload' => json_decode($event['payload'], true),
    'metadata' => json_decode($event['metadata'] ?? '{}', true)
];

echo "Evento ID: {$event['event_id']}\n";
echo "Tipo: {$event['event_type']}\n";
echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
echo "\n";

// Usa reflection para chamar extractChannelInfo
$reflection = new ReflectionClass(\PixelHub\Services\ConversationService::class);
$method = $reflection->getMethod('extractChannelInfo');
$method->setAccessible(true);

try {
    $channelInfo = $method->invoke(null, $eventData);
    
    if ($channelInfo) {
        echo "SUCESSO: extractChannelInfo retornou dados:\n";
        echo json_encode($channelInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "FALHA: extractChannelInfo retornou NULL\n";
        echo "Verifique os logs de erro acima.\n";
    }
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

