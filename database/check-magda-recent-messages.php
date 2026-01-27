<?php

/**
 * Script para verificar mensagens recentes da Magda (17:48)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO MENSAGENS RECENTES DA MAGDA (17:48) ===\n\n";

$magdaPhone = '5511940863773';

// Busca eventos após 17:45
echo "1. Buscando eventos após 17:45...\n";
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.to') as to_field,
        JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
        JSON_EXTRACT(ce.payload, '$.text') as text,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text,
        JSON_EXTRACT(ce.payload, '$.body') as body,
        JSON_EXTRACT(ce.payload, '$.message.body') as message_body
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND ce.created_at >= '2026-01-16 17:45:00'
      AND (
          JSON_EXTRACT(ce.payload, '$.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
          OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
      )
    ORDER BY ce.created_at ASC
");
$pattern = "%{$magdaPhone}%";
$stmt->execute([$pattern, $pattern, $pattern, $pattern]);
$events = $stmt->fetchAll();

echo "   Encontrados " . count($events) . " eventos após 17:45:\n\n";
foreach ($events as $event) {
    $from = trim($event['from_field'] ?? $event['message_from'] ?? '', '"');
    $to = trim($event['to_field'] ?? $event['message_to'] ?? '', '"');
    $text = trim($event['text'] ?? $event['message_text'] ?? $event['body'] ?? $event['message_body'] ?? '', '"');
    $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'INBOUND' : 'OUTBOUND';
    
    echo "   - {$direction} | {$event['created_at']}\n";
    echo "     From: {$from}\n";
    echo "     To: {$to}\n";
    echo "     Text: " . substr($text, 0, 150) . (strlen($text) > 150 ? '...' : '') . "\n";
    echo "     Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "     Event ID: {$event['event_id']}\n";
    echo "\n";
}

echo "\n";

// Busca TODOS os eventos do dia 16/01 após 17:45 (para ver se há mensagens que não estão sendo associadas)
echo "2. Buscando TODOS os eventos INBOUND após 17:45 (qualquer número)...\n";
$stmt = $db->query("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.from') as from_field,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.text') as text,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text
    FROM communication_events ce
    WHERE ce.event_type = 'whatsapp.inbound.message'
      AND ce.created_at >= '2026-01-16 17:45:00'
      AND ce.created_at <= '2026-01-16 18:00:00'
    ORDER BY ce.created_at ASC
    LIMIT 20
");
$allInbound = $stmt->fetchAll();

echo "   Encontrados " . count($allInbound) . " eventos INBOUND no período:\n\n";
foreach ($allInbound as $event) {
    $from = trim($event['from_field'] ?? $event['message_from'] ?? '', '"');
    $text = trim($event['text'] ?? $event['message_text'] ?? '', '"');
    
    echo "   - {$event['created_at']}\n";
    echo "     From: {$from}\n";
    echo "     Text: " . substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '') . "\n";
    echo "     Tenant: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "\n";
}

echo "\n";










