<?php

/**
 * Script para testar a busca de mensagens da conversa
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== TESTE DE BUSCA DE MENSAGENS ===\n\n";

// 1. Busca conversa
$stmt = $db->prepare("
    SELECT id, contact_external_id, remote_key, tenant_id, channel_id
    FROM conversations
    WHERE id = 1
");
$stmt->execute();
$conversation = $stmt->fetch();

if (!$conversation) {
    echo "Conversa não encontrada!\n";
    exit(1);
}

echo "Conversa ID: {$conversation['id']}\n";
echo "contact_external_id: {$conversation['contact_external_id']}\n";
echo "remote_key: " . ($conversation['remote_key'] ?: 'NULL') . "\n";
echo "tenant_id: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
echo "channel_id: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
echo "\n";

// 2. Busca @lid mapeado
$contactExternalId = $conversation['contact_external_id'];
$lidBusinessIds = [];
if (!empty($contactExternalId) && preg_match('/^[0-9]+$/', $contactExternalId)) {
    $lidStmt = $db->prepare("
        SELECT business_id 
        FROM whatsapp_business_ids 
        WHERE phone_number = ?
    ");
    $lidStmt->execute([$contactExternalId]);
    $lidMappings = $lidStmt->fetchAll(PDO::FETCH_COLUMN);
    $lidBusinessIds = $lidMappings ?: [];
    
    echo "LID mapeados encontrados: " . count($lidBusinessIds) . "\n";
    foreach ($lidBusinessIds as $lid) {
        echo "  - {$lid}\n";
    }
    echo "\n";
}

// 3. Monta padrões de busca
$normalizedContactExternalId = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $contactExternalId));
$contactPatterns = ["%{$normalizedContactExternalId}%"];

if (!empty($lidBusinessIds)) {
    foreach ($lidBusinessIds as $lid) {
        $contactPatterns[] = "%{$lid}%";
    }
}

echo "Padrões de busca:\n";
foreach ($contactPatterns as $pattern) {
    echo "  - {$pattern}\n";
}
echo "\n";

// 4. Busca eventos com esses padrões
$where = ["ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"];
$params = [];

$contactConditions = [];
foreach ($contactPatterns as $pattern) {
    $contactConditions[] = "(
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
        OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
    )";
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
    $params[] = $pattern;
}
$where[] = "(" . implode(" OR ", $contactConditions) . ")";

if ($conversation['tenant_id']) {
    $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
    $params[] = $conversation['tenant_id'];
}

$whereClause = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        JSON_EXTRACT(ce.payload, '$.from') as from_raw,
        JSON_EXTRACT(ce.payload, '$.to') as to_raw,
        JSON_EXTRACT(ce.payload, '$.message.from') as message_from,
        JSON_EXTRACT(ce.payload, '$.message.to') as message_to,
        JSON_EXTRACT(ce.payload, '$.message.text') as message_text
    FROM communication_events ce
    {$whereClause}
    ORDER BY ce.created_at ASC
    LIMIT 500
";

echo "SQL Query:\n";
echo $sql . "\n";
echo "\nParâmetros:\n";
foreach ($params as $i => $p) {
    echo "  [{$i}] = {$p}\n";
}
echo "\n";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

echo "Eventos encontrados: " . count($events) . "\n\n";

foreach ($events as $event) {
    echo "--- Evento ID: {$event['event_id']} ---\n";
    echo "Tipo: {$event['event_type']}\n";
    echo "tenant_id: " . ($event['tenant_id'] ?: 'NULL') . "\n";
    echo "from_raw: " . ($event['from_raw'] ?: 'NULL') . "\n";
    echo "to_raw: " . ($event['to_raw'] ?: 'NULL') . "\n";
    echo "message_from: " . ($event['message_from'] ?: 'NULL') . "\n";
    echo "message_to: " . ($event['message_to'] ?: 'NULL') . "\n";
    echo "message_text: " . ($event['message_text'] ?: 'NULL') . "\n";
    echo "created_at: {$event['created_at']}\n";
    echo "\n";
}

