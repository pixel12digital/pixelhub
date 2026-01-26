<?php

/**
 * Script para verificar o payload completo dos eventos recentes
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== Verificando payload completo dos eventos recentes ===\n\n";

// Busca os 5 eventos mais recentes do Charles Dietrich
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.payload,
        ce.metadata
    FROM communication_events ce
    WHERE (
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE '%554796164699%'
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE '%554796164699%'
    )
    AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado\n";
} else {
    foreach ($events as $idx => $event) {
        echo "Evento " . ($idx + 1) . ":\n";
        echo "  Event ID: {$event['event_id']}\n";
        echo "  Event Type: {$event['event_type']}\n";
        echo "  Created At: {$event['created_at']}\n";
        
        $payload = json_decode($event['payload'], true);
        $metadata = json_decode($event['metadata'] ?? '{}', true);
        
        echo "\n  Payload (estrutura):\n";
        echo "    Keys: " . implode(', ', array_keys($payload)) . "\n";
        
        // Extrai campos importantes
        $from = $payload['from'] ?? $payload['message']['from'] ?? $payload['data']['from'] ?? null;
        $to = $payload['to'] ?? $payload['message']['to'] ?? $payload['data']['to'] ?? null;
        $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? $payload['data']['text'] ?? null;
        $type = $payload['type'] ?? $payload['message']['type'] ?? $payload['data']['type'] ?? null;
        
        echo "\n  Campos extraídos:\n";
        echo "    From: " . ($from ?: 'NULL') . "\n";
        echo "    To: " . ($to ?: 'NULL') . "\n";
        echo "    Text: " . ($text ? substr($text, 0, 100) : 'NULL') . "\n";
        echo "    Type: " . ($type ?: 'NULL') . "\n";
        
        echo "\n  Metadata:\n";
        echo "    Channel ID: " . ($metadata['channel_id'] ?? 'NULL') . "\n";
        
        // Se o texto contém "teste-1459", destaca
        if ($text && stripos($text, 'teste-1459') !== false) {
            echo "\n  ⚠️  ENCONTRADO 'teste-1459' neste evento!\n";
        }
        
        // Mostra payload completo (primeiros 500 chars)
        echo "\n  Payload completo (primeiros 500 chars):\n";
        $payloadStr = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        echo "    " . substr($payloadStr, 0, 500) . "...\n";
        
        echo "\n" . str_repeat("-", 80) . "\n\n";
    }
}

// Busca especificamente por "teste-1459" no payload completo
echo "\n=== Buscando 'teste-1459' no payload completo ===\n";
$stmt2 = $db->prepare("
    SELECT 
        ce.event_id,
        ce.created_at,
        ce.payload
    FROM communication_events ce
    WHERE ce.payload LIKE '%teste-1459%'
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$stmt2->execute();
$teste1459Events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($teste1459Events)) {
    echo "❌ Nenhum evento encontrado com 'teste-1459' no payload\n";
} else {
    echo "✅ Encontrados " . count($teste1459Events) . " evento(s) com 'teste-1459':\n";
    foreach ($teste1459Events as $event) {
        echo "  - Event ID: {$event['event_id']}\n";
        echo "    Created At: {$event['created_at']}\n";
        $payload = json_decode($event['payload'], true);
        $text = $payload['text'] ?? $payload['body'] ?? $payload['message']['text'] ?? $payload['message']['body'] ?? null;
        echo "    Text: " . ($text ?: 'NULL') . "\n";
        echo "\n";
    }
}

echo "\n=== Fim da verificação ===\n";

