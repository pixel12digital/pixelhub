<?php
/**
 * Verifica duplicidade de conversas para Robson Vieira
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== CONVERSAS COM NUMERO 99884234 (Robson) ===\n\n";

$stmt = $db->query("
    SELECT 
        id, 
        conversation_key, 
        contact_external_id,
        contact_name,
        remote_key,
        tenant_id,
        is_incoming_lead,
        status,
        message_count,
        created_at,
        updated_at,
        last_message_at
    FROM conversations 
    WHERE contact_external_id LIKE '%99884234%' 
       OR contact_name LIKE '%Robson%'
    ORDER BY created_at DESC
");

$rows = $stmt->fetchAll();
echo "Total encontrado: " . count($rows) . " conversas\n\n";

foreach ($rows as $row) {
    echo "ID: {$row['id']}\n";
    echo "  conversation_key: {$row['conversation_key']}\n";
    echo "  contact_external_id: {$row['contact_external_id']}\n";
    echo "  contact_name: {$row['contact_name']}\n";
    echo "  remote_key: " . ($row['remote_key'] ?? 'NULL') . "\n";
    echo "  tenant_id: " . ($row['tenant_id'] ?? 'NULL') . "\n";
    echo "  is_incoming_lead: {$row['is_incoming_lead']}\n";
    echo "  status: {$row['status']}\n";
    echo "  message_count: {$row['message_count']}\n";
    echo "  created_at: {$row['created_at']}\n";
    echo "  last_message_at: " . ($row['last_message_at'] ?? 'NULL') . "\n";
    echo "\n";
}

echo "=== VERIFICAR EVENTOS RECENTES PARA ESTE NUMERO ===\n\n";

$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        conversation_id,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as msg_to
    FROM communication_events 
    WHERE (
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%99884234%'
        OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%99884234%'
    )
    ORDER BY created_at DESC
    LIMIT 10
");

$events = $stmt->fetchAll();
echo "Últimos 10 eventos:\n\n";

foreach ($events as $ev) {
    echo "Event ID: " . substr($ev['event_id'], 0, 8) . "...\n";
    echo "  Type: {$ev['event_type']}\n";
    echo "  conversation_id: " . ($ev['conversation_id'] ?? 'NULL') . "\n";
    echo "  from: " . ($ev['msg_from'] ?? 'NULL') . "\n";
    echo "  to: " . ($ev['msg_to'] ?? 'NULL') . "\n";
    echo "  created_at: {$ev['created_at']}\n";
    echo "\n";
}

echo "=== FUSO HORARIO DO SERVIDOR ===\n\n";
$stmt = $db->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz, NOW() as server_now");
$tz = $stmt->fetch();
echo "Global timezone: {$tz['global_tz']}\n";
echo "Session timezone: {$tz['session_tz']}\n";
echo "NOW() no servidor: {$tz['server_now']}\n";
echo "PHP date(): " . date('Y-m-d H:i:s') . "\n";
echo "PHP timezone: " . date_default_timezone_get() . "\n";
