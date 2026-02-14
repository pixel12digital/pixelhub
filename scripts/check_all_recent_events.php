<?php
/**
 * Script para verificar TODOS os eventos recentes (últimos 30 min)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== Verificando TODOS os eventos recentes (últimos 30 min) ===\n\n";

$db = DB::getConnection();

$sql = "
    SELECT id, event_type, event_id, conversation_id, tenant_id, status, created_at,
           JSON_EXTRACT(payload, '$.message.from') as msg_from,
           JSON_EXTRACT(payload, '$.message.text') as msg_text,
           JSON_EXTRACT(metadata, '$.channel_id') as channel_id
    FROM communication_events 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY created_at DESC
    LIMIT 20
";

$stmt = $db->query($sql);
$events = $stmt->fetchAll();

if (empty($events)) {
    echo "❌ Nenhum evento encontrado nos últimos 30 minutos.\n";
} else {
    echo "✅ Eventos encontrados:\n\n";
    foreach ($events as $event) {
        $from = trim($event['msg_from'] ?? '', '"');
        $text = trim($event['msg_text'] ?? '', '"');
        $channelId = trim($event['channel_id'] ?? '', '"');
        
        echo "ID: {$event['id']} | {$event['event_type']} | Status: {$event['status']}\n";
        echo "Created: {$event['created_at']}\n";
        echo "From: {$from}\n";
        echo "Text: " . substr($text, 0, 50) . "\n";
        echo "Channel: {$channelId}\n";
        echo "Conv ID: " . ($event['conversation_id'] ?? 'NULL') . " | Tenant: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo str_repeat("-", 80) . "\n";
    }
}

echo "\n=== Fim da verificação ===\n";
