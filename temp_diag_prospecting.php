<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
use PixelHub\Core\Env;
use PixelHub\Core\DB;
Env::load();
$db = DB::getConnection();

echo "=== 1. Conversas is_incoming_lead=1 com source e last_message_direction exatos ===\n";
$stmt = $db->query("
    SELECT id, source, last_message_direction, contact_name
    FROM conversations
    WHERE is_incoming_lead = 1
      AND (status IS NULL OR status NOT IN ('closed','archived','ignored'))
    ORDER BY last_message_at DESC
    LIMIT 30
");
foreach ($stmt->fetchAll() as $r) {
    $src = $r['source'];
    $dir = $r['last_message_direction'];
    $isFiltered = ($src === 'prospecting' && $dir === 'outbound') ? '>>> SERIA OCULTADO' : '-- visivel';
    echo "  id={$r['id']} source=[{$src}] dir=[{$dir}] name={$r['contact_name']} {$isFiltered}\n";
}

echo "\n=== 2. communication_events: conversation_id preenchido? ===\n";
$stmt = $db->query("
    SELECT 
        SUM(conversation_id IS NULL) as sem_conv_id,
        SUM(conversation_id IS NOT NULL) as com_conv_id,
        COUNT(*) as total
    FROM communication_events
    LIMIT 1
");
$r = $stmt->fetch();
echo "  com conversation_id: {$r['com_conv_id']} | sem: {$r['sem_conv_id']} | total: {$r['total']}\n";

echo "\n=== 3. direction values em communication_events ===\n";
$stmt = $db->query("SELECT direction, COUNT(*) as total FROM communication_events GROUP BY direction");
foreach ($stmt->fetchAll() as $r) {
    echo "  direction={$r['direction']} | total={$r['total']}\n";
}

echo "\n=== 4. Amostra de conversas is_incoming_lead=1 ===\n";
$stmt = $db->query("
    SELECT id, source, last_message_direction, unread_count, message_count, is_incoming_lead
    FROM conversations
    WHERE is_incoming_lead = 1
    LIMIT 10
");
foreach ($stmt->fetchAll() as $r) {
    echo "  id={$r['id']} source={$r['source']} last_dir={$r['last_message_direction']} unread={$r['unread_count']} msgs={$r['message_count']}\n";
}
