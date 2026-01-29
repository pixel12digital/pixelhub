<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

echo "=== EVENTOS DA CONVERSA 121 ===\n";
$stmt = $db->query("SELECT id, event_type, tenant_id, conversation_id FROM communication_events WHERE conversation_id = 121 ORDER BY created_at ASC");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID: {$r['id']} | tenant_id: " . ($r['tenant_id'] ?? 'NULL') . " | type: {$r['event_type']}\n";
}

echo "\n=== TENANT MESSAGE CHANNELS (pixel12digital) ===\n";
$stmt = $db->query("SELECT * FROM tenant_message_channels WHERE channel_id LIKE '%pixel12%' OR channel_id LIKE '%Pixel12%'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "\n=== CONVERSA 121 DETALHES ===\n";
$stmt = $db->query("SELECT * FROM conversations WHERE id = 121");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($conv);
