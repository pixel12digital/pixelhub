<?php
/**
 * Investigação de duplicidade do Luiz (7530)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO DUPLICIDADE LUIZ (7530) ===\n\n";

// 1. Busca todas as conversas com nome Luiz ou número 7530
echo "1. CONVERSAS COM NOME 'LUIZ' OU NÚMERO '7530'\n";
echo str_repeat("-", 70) . "\n\n";

$stmt = $db->query("
    SELECT 
        id, 
        conversation_key, 
        contact_external_id,
        contact_name,
        remote_key,
        tenant_id,
        channel_id,
        status,
        message_count,
        last_message_at,
        created_at
    FROM conversations 
    WHERE contact_name LIKE '%Luiz%' 
       OR contact_name LIKE '%Gerente%Ecommerce%'
       OR contact_external_id LIKE '%7530%'
       OR contact_external_id LIKE '%103066917425370%'
    ORDER BY created_at DESC
");

$convs = $stmt->fetchAll();
echo "Total encontrado: " . count($convs) . " conversas\n\n";

foreach ($convs as $c) {
    echo "ID: {$c['id']} | Created: {$c['created_at']}\n";
    echo "  Nome: {$c['contact_name']}\n";
    echo "  contact_external_id: {$c['contact_external_id']}\n";
    echo "  remote_key: " . ($c['remote_key'] ?? 'NULL') . "\n";
    echo "  conversation_key: {$c['conversation_key']}\n";
    echo "  tenant_id: " . ($c['tenant_id'] ?? 'NULL') . "\n";
    echo "  channel_id: " . ($c['channel_id'] ?? 'NULL') . "\n";
    echo "  message_count: {$c['message_count']}\n";
    echo "  last_message_at: {$c['last_message_at']}\n";
    echo "\n";
}

// 2. Verifica cache wa_pnlid_cache para o @lid do Luiz
echo "\n2. CACHE WA_PNLID_CACHE PARA LUIZ\n";
echo str_repeat("-", 70) . "\n\n";

$stmt = $db->query("
    SELECT * FROM wa_pnlid_cache 
    WHERE pnlid LIKE '%103066917425370%' 
       OR phone_e164 LIKE '%7530%'
    ORDER BY updated_at DESC
");

$cache = $stmt->fetchAll();
echo "Entradas encontradas: " . count($cache) . "\n\n";

foreach ($cache as $c) {
    echo json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// 3. Verifica eventos recentes do Luiz
echo "\n3. EVENTOS RECENTES DO LUIZ (últimos 10)\n";
echo str_repeat("-", 70) . "\n\n";

$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        conversation_id,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as msg_to,
        SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.content')), 1, 50) as content
    FROM communication_events 
    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%103066917425370%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%103066917425370%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%7530%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%7530%'
    ORDER BY created_at DESC
    LIMIT 10
");

$events = $stmt->fetchAll();
echo "Eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $ev) {
    echo "Event: " . substr($ev['event_id'], 0, 8) . "... | {$ev['event_type']}\n";
    echo "  conversation_id: " . ($ev['conversation_id'] ?? 'NULL') . "\n";
    echo "  from: " . ($ev['msg_from'] ?? 'NULL') . "\n";
    echo "  to: " . ($ev['msg_to'] ?? 'NULL') . "\n";
    echo "  created_at: {$ev['created_at']}\n";
    echo "  content: " . ($ev['content'] ?? '(vazio)') . "\n\n";
}

// 4. Verifica timezone do PHP e MySQL
echo "\n4. TIMEZONE ATUAL\n";
echo str_repeat("-", 70) . "\n\n";

echo "PHP date_default_timezone_get(): " . date_default_timezone_get() . "\n";
echo "PHP date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "\n";
echo "PHP gmdate('Y-m-d H:i:s'): " . gmdate('Y-m-d H:i:s') . " (UTC)\n";

$stmt = $db->query("SELECT NOW() as db_now, @@session.time_zone as tz");
$row = $stmt->fetch();
echo "MySQL NOW(): " . $row['db_now'] . "\n";
echo "MySQL time_zone: " . $row['tz'] . "\n";
