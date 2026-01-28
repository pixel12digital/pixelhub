<?php
/**
 * Verifica timestamps no banco e como são exibidos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

// Simula o index.php
date_default_timezone_set('America/Sao_Paulo');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== CONFIGURAÇÃO DE TIMEZONE ===\n\n";

echo "PHP timezone: " . date_default_timezone_get() . "\n";
echo "PHP NOW: " . date('Y-m-d H:i:s') . "\n";

$stmt = $db->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz, NOW() as db_now");
$tz = $stmt->fetch();
echo "MySQL global timezone: {$tz['global_tz']}\n";
echo "MySQL session timezone: {$tz['session_tz']}\n";
echo "MySQL NOW(): {$tz['db_now']}\n";

echo "\n=== ÚLTIMAS CONVERSAS COM TIMESTAMPS ===\n\n";

$stmt = $db->query("
    SELECT 
        id,
        contact_name,
        last_message_at,
        created_at,
        updated_at
    FROM conversations 
    WHERE last_message_at IS NOT NULL
    ORDER BY last_message_at DESC 
    LIMIT 5
");

$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo "ID: {$row['id']} - {$row['contact_name']}\n";
    echo "  last_message_at (banco): {$row['last_message_at']}\n";
    
    // Testa interpretação
    $dt = new DateTime($row['last_message_at']);
    echo "  Interpretado como: " . $dt->format('d/m/Y H:i:s') . "\n";
    
    // Com timezone explícito
    $dtUtc = new DateTime($row['last_message_at'], new DateTimeZone('UTC'));
    $dtUtc->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    echo "  Se UTC -> Brasília: " . $dtUtc->format('d/m/Y H:i:s') . "\n";
    
    echo "\n";
}

echo "=== ÚLTIMOS EVENTOS COM TIMESTAMPS ===\n\n";

$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.timestamp')) as payload_ts,
        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.received_at')) as received_at
    FROM communication_events 
    ORDER BY created_at DESC 
    LIMIT 3
");

$events = $stmt->fetchAll();
foreach ($events as $ev) {
    echo "Event: " . substr($ev['event_id'], 0, 8) . "... ({$ev['event_type']})\n";
    echo "  created_at (banco): {$ev['created_at']}\n";
    echo "  payload.timestamp: " . ($ev['payload_ts'] ?? 'NULL') . "\n";
    echo "  metadata.received_at: " . ($ev['received_at'] ?? 'NULL') . "\n";
    echo "\n";
}
