<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

echo "=== EVENTOS DAS 09:45-09:48 ===\n";
$stmt = $db->query("
    SELECT id, event_type, conversation_id, status, created_at, SUBSTRING(payload, 1, 300) as payload_preview
    FROM communication_events 
    WHERE created_at >= '2026-01-29 09:45:00' AND created_at <= '2026-01-29 09:48:00'
    AND event_type LIKE '%message%'
    ORDER BY created_at ASC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']} | Tipo: {$row['event_type']} | Conv: " . ($row['conversation_id'] ?? 'NULL') . " | Status: {$row['status']} | {$row['created_at']}\n";
    echo "Payload: {$row['payload_preview']}\n\n";
}

echo "\n=== CONVERSA 121 (Charles) ===\n";
$stmt = $db->query("SELECT id, conversation_key, contact_external_id, contact_name, tenant_id, channel_id, last_message_at, message_count, status FROM conversations WHERE id = 121");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($conv);

echo "\n=== MENSAGENS DA CONVERSA 121 (últimas 10) ===\n";
$stmt = $db->query("SELECT id, event_type, status, created_at FROM communication_events WHERE conversation_id = 121 ORDER BY created_at DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) {
    echo "Nenhuma mensagem encontrada na conversa 121!\n";
} else {
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | Tipo: {$row['event_type']} | Status: {$row['status']} | {$row['created_at']}\n";
    }
}

echo "\n=== EVENTOS COM '0945' NO PAYLOAD ===\n";
$stmt = $db->query("SELECT id, event_type, conversation_id, created_at FROM communication_events WHERE payload LIKE '%0945%' ORDER BY created_at DESC LIMIT 5");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']} | Tipo: {$row['event_type']} | Conv: " . ($row['conversation_id'] ?? 'NULL') . " | {$row['created_at']}\n";
}
