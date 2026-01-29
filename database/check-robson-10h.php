<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();

echo "=== EVENTOS ROBSON (9988-4234) DAS 10:00-10:30 ===\n";
$stmt = $db->query("
    SELECT id, event_type, conversation_id, tenant_id, status, created_at, 
           SUBSTRING(payload, 1, 400) as payload_preview
    FROM communication_events 
    WHERE created_at >= '2026-01-29 10:00:00' AND created_at <= '2026-01-29 10:30:00'
    AND event_type LIKE '%message%'
    AND payload LIKE '%9988%'
    ORDER BY created_at ASC
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "\nID: {$row['id']} | Tipo: {$row['event_type']} | Conv: " . ($row['conversation_id'] ?? 'NULL') . " | tenant: " . ($row['tenant_id'] ?? 'NULL') . "\n";
    echo "Created: {$row['created_at']} | Status: {$row['status']}\n";
    echo "Payload: {$row['payload_preview']}\n";
    echo str_repeat("-", 80);
}

echo "\n\n=== CONVERSA ROBSON (8) ===\n";
$stmt = $db->query("SELECT * FROM conversations WHERE id = 8");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($conv);

echo "\n=== ÚLTIMOS 5 EVENTOS ROBSON ===\n";
$stmt = $db->query("
    SELECT id, event_type, created_at, tenant_id, conversation_id
    FROM communication_events 
    WHERE conversation_id = 8
    ORDER BY created_at DESC
    LIMIT 5
");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: {$row['id']} | {$row['event_type']} | tenant: " . ($row['tenant_id'] ?? 'NULL') . " | {$row['created_at']}\n";
}

echo "\n=== TIMEZONE CONFIG ===\n";
echo "PHP default_timezone: " . date_default_timezone_get() . "\n";
echo "PHP date now: " . date('Y-m-d H:i:s') . "\n";
$stmt = $db->query("SELECT NOW() as mysql_now, @@global.time_zone as global_tz, @@session.time_zone as session_tz");
$tz = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($tz);
