<?php

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();

echo "═══════════════════════════════════════════════════════════════\n";
echo "TODOS OS EVENTOS COM CONTATO 554796164699 (com/sem @c.us)\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Busca TODOS os eventos que podem estar relacionados
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        created_at,
        tenant_id,
        payload
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        payload LIKE '%554796164699%' OR
        JSON_EXTRACT(payload, '$.from') LIKE '%554796164699%' OR
        JSON_EXTRACT(payload, '$.to') LIKE '%554796164699%' OR
        JSON_EXTRACT(payload, '$.message.from') LIKE '%554796164699%' OR
        JSON_EXTRACT(payload, '$.message.to') LIKE '%554796164699%'
    )
    ORDER BY created_at ASC
");

$events = $stmt->fetchAll();

echo "Total de eventos encontrados: " . count($events) . "\n\n";

$normalizeContact = function($contact) {
    return preg_replace('/@[^.]+$/', '', $contact);
};

foreach ($events as $event) {
    $payload = json_decode($event['payload'], true);
    $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
    $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
    $content = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? '[mídia/vazio]';
    
    $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
    $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
    
    echo "📱 {$event['created_at']} - {$event['event_type']}\n";
    echo "   Event ID: {$event['event_id']}\n";
    echo "   From original: {$eventFrom}\n";
    echo "   From normalizado: {$normalizedFrom}\n";
    echo "   To original: " . ($eventTo ?? 'N/A') . "\n";
    echo "   To normalizado: " . ($normalizedTo ?? 'N/A') . "\n";
    echo "   Content: " . substr($content, 0, 80) . "\n";
    echo "   Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
    echo "   Match com 554796164699? " . (($normalizedFrom === '554796164699' || $normalizedTo === '554796164699') ? 'SIM ✓' : 'NÃO') . "\n\n";
}

