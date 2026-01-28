<?php
/**
 * Testa a query de mensagens de uma conversa
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$conversationId = $_GET['conv'] ?? $argv[1] ?? 8;

$db = DB::getConnection();

// Busca dados da conversa
$stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
$stmt->execute([$conversationId]);
$conv = $stmt->fetch();

if (!$conv) {
    die("Conversa {$conversationId} não encontrada\n");
}

$sessionId = $conv['channel_id'] ?? '';
$contactExternalId = $conv['contact_external_id'] ?? '';
$tenantId = $conv['tenant_id'];

echo "=== TESTE DA QUERY DE MENSAGENS ===\n";
echo "Conversa ID: {$conversationId}\n";
echo "channel_id (sessionId): {$sessionId}\n";
echo "contact_external_id: {$contactExternalId}\n";
echo "tenant_id: " . ($tenantId ?: 'NULL') . "\n";
echo str_repeat("=", 80) . "\n\n";

// Normaliza channel_id como a correção faz
$normalizedSessionId = strtolower(str_replace(' ', '', $sessionId));
echo "channel_id normalizado: {$normalizedSessionId}\n\n";

// Simula a query com a correção (incluindo payload.channel_id)
$sql = "
SELECT 
    ce.event_id,
    ce.event_type,
    ce.created_at,
    JSON_EXTRACT(ce.payload, '$.type') as msg_type,
    JSON_EXTRACT(ce.metadata, '$.channel_id') as meta_channel_id,
    JSON_EXTRACT(ce.payload, '$.channel_id') as payload_channel_id,
    JSON_EXTRACT(ce.payload, '$.session.id') as session_id
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
AND ce.conversation_id = ?
AND (
    LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = ?
    OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channel_id')), ' ', ''))) = ?
    OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')), ' ', ''))) = ?
)
ORDER BY ce.created_at DESC
LIMIT 20
";

echo "1. COM FILTRO DE CHANNEL_ID (query completa):\n";
echo str_repeat("-", 80) . "\n";

$stmt = $db->prepare($sql);
$stmt->execute([$conversationId, $normalizedSessionId, $normalizedSessionId, $normalizedSessionId]);
$events = $stmt->fetchAll();

echo "Encontrados: " . count($events) . " evento(s)\n\n";
foreach ($events as $e) {
    $direction = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
    echo "  [{$direction}] {$e['event_id']}\n";
    echo "       type: {$e['msg_type']}\n";
    echo "       meta.channel_id: {$e['meta_channel_id']}\n";
    echo "       payload.channel_id: {$e['payload_channel_id']}\n";
    echo "       session.id: {$e['session_id']}\n";
    echo "       created_at: {$e['created_at']}\n\n";
}

// Simula query SEM filtro de channel_id (só conversation_id)
echo "\n2. SEM FILTRO DE CHANNEL_ID (apenas conversation_id):\n";
echo str_repeat("-", 80) . "\n";

$sql2 = "
SELECT 
    ce.event_id,
    ce.event_type,
    ce.created_at,
    JSON_EXTRACT(ce.payload, '$.type') as msg_type,
    JSON_EXTRACT(ce.metadata, '$.channel_id') as meta_channel_id,
    JSON_EXTRACT(ce.payload, '$.channel_id') as payload_channel_id
FROM communication_events ce
WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
AND ce.conversation_id = ?
ORDER BY ce.created_at DESC
LIMIT 20
";

$stmt = $db->prepare($sql2);
$stmt->execute([$conversationId]);
$events = $stmt->fetchAll();

echo "Encontrados: " . count($events) . " evento(s)\n\n";
foreach ($events as $e) {
    $direction = strpos($e['event_type'], 'inbound') !== false ? 'IN' : 'OUT';
    echo "  [{$direction}] {$e['event_id']}\n";
    echo "       meta.channel_id: {$e['meta_channel_id']}\n";
    echo "       payload.channel_id: {$e['payload_channel_id']}\n\n";
}

echo "\n=== FIM DO TESTE ===\n";
