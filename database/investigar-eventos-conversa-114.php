<?php
/**
 * Investiga eventos da conversa 114 (Luiz)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== EVENTOS DA CONVERSA 114 (Luiz) ===\n\n";

// Eventos vinculados à conversa 114
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        conversation_id,
        created_at,
        payload,
        metadata
    FROM communication_events 
    WHERE conversation_id = 114
    ORDER BY created_at DESC
");

$events = $stmt->fetchAll();
echo "Eventos com conversation_id=114: " . count($events) . "\n\n";

foreach ($events as $ev) {
    echo "Event: {$ev['event_id']}\n";
    echo "  Type: {$ev['event_type']}\n";
    echo "  Created: {$ev['created_at']}\n";
    
    $payload = json_decode($ev['payload'], true);
    echo "  from: " . ($payload['from'] ?? $payload['message']['from'] ?? 'NULL') . "\n";
    echo "  to: " . ($payload['to'] ?? $payload['message']['to'] ?? 'NULL') . "\n";
    echo "  content: " . substr($payload['content'] ?? $payload['message']['content'] ?? '(vazio)', 0, 80) . "\n";
    
    // Verifica se há phone_number no payload
    if (isset($payload['phone_number'])) {
        echo "  phone_number: {$payload['phone_number']}\n";
    }
    if (isset($payload['message']['participant'])) {
        echo "  participant: {$payload['message']['participant']}\n";
    }
    if (isset($payload['raw']['payload']['participant'])) {
        echo "  raw.participant: {$payload['raw']['payload']['participant']}\n";
    }
    
    echo "\n";
}

// Também busca eventos pelo @lid do Luiz
echo "\n=== EVENTOS COM @LID DO LUIZ (103066917425370@lid) ===\n\n";

$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        conversation_id,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as msg_to,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from2,
        SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.content')), 1, 50) as content
    FROM communication_events 
    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%103066917425370%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%103066917425370%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE '%103066917425370%'
    ORDER BY created_at DESC
");

$lidEvents = $stmt->fetchAll();
echo "Eventos com @lid do Luiz: " . count($lidEvents) . "\n\n";

foreach ($lidEvents as $ev) {
    echo "Event: " . substr($ev['event_id'], 0, 8) . "... | {$ev['event_type']}\n";
    echo "  conversation_id: " . ($ev['conversation_id'] ?? 'NULL') . "\n";
    echo "  from: " . ($ev['msg_from'] ?? $ev['msg_from2'] ?? 'NULL') . "\n";
    echo "  to: " . ($ev['msg_to'] ?? 'NULL') . "\n";
    echo "  created_at: {$ev['created_at']}\n";
    echo "  content: " . ($ev['content'] ?? '(vazio)') . "\n\n";
}

// Verifica onde o número 2320 aparece no código
echo "\n=== VERIFICAR CACHE pnLid ===\n\n";

$stmt = $db->query("SELECT * FROM wa_pnlid_cache WHERE pnlid LIKE '%103066917425370%' LIMIT 5");
$cache = $stmt->fetchAll();

if (empty($cache)) {
    echo "Nenhum cache encontrado para este @lid\n";
} else {
    foreach ($cache as $c) {
        echo json_encode($c, JSON_PRETTY_PRINT) . "\n";
    }
}

// Verifica wa_contact_names_cache
echo "\n=== VERIFICAR wa_contact_names_cache ===\n\n";

$stmt = $db->query("SHOW TABLES LIKE 'wa_contact_names_cache'");
if ($stmt->rowCount() > 0) {
    $stmt = $db->query("SELECT * FROM wa_contact_names_cache WHERE contact_id LIKE '%103066917425370%' OR contact_id LIKE '%7530%' LIMIT 10");
    $names = $stmt->fetchAll();
    
    if (empty($names)) {
        echo "Nenhum cache de nomes encontrado\n";
    } else {
        foreach ($names as $n) {
            echo json_encode($n, JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "Tabela wa_contact_names_cache não existe\n";
}
