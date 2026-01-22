<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PixelHub\Core\Env;
use PixelHub\Core\DB;
Env::load();

$db = DB::getConnection();

echo "=== VERIFICAÇÃO RÁPIDA: FORMATO INBOUND ===\n\n";

// Busca últimos 5 eventos inbound
$stmt = $db->query("
    SELECT id, created_at, 
           JSON_EXTRACT(payload, '$.from') as from1,
           JSON_EXTRACT(payload, '$.message.from') as from2,
           JSON_EXTRACT(payload, '$.message.key.remoteJid') as from3,
           JSON_EXTRACT(payload, '$.data.from') as from4,
           JSON_EXTRACT(metadata, '$.channel_id') as channel
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 5
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Últimos 5 eventos inbound:\n\n";

foreach ($events as $e) {
    $from1 = trim($e['from1'] ?? 'NULL', '"');
    $from2 = trim($e['from2'] ?? 'NULL', '"');
    $from3 = trim($e['from3'] ?? 'NULL', '"');
    $from4 = trim($e['from4'] ?? 'NULL', '"');
    
    echo "ID: {$e['id']} | {$e['created_at']}\n";
    echo "  payload.from: {$from1}\n";
    echo "  payload.message.from: {$from2}\n";
    echo "  payload.message.key.remoteJid: {$from3}\n";
    echo "  payload.data.from: {$from4}\n";
    echo "  Channel: " . trim($e['channel'] ?? 'NULL', '"') . "\n\n";
}

echo "=== FIM ===\n";








