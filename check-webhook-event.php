<?php
require 'src/Core/DB.php';
require 'src/Core/Env.php';

\PixelHub\Core\Env::load();
$db = \PixelHub\Core\DB::getConnection();

// Busca por correlation_id ou event_id
$correlationId = '9858a507-cc4c-4632-8f92-462535eab504';
$eventId = '90c9089f-520b-4617-b72a-ce880c75739c';

$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        correlation_id,
        event_type,
        source_system,
        tenant_id,
        status,
        created_at,
        JSON_EXTRACT(payload, '$.message.id') as message_id,
        JSON_EXTRACT(payload, '$.message.from') as message_from
    FROM communication_events 
    WHERE correlation_id = ? OR event_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");

$stmt->execute([$correlationId, $eventId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== Eventos Encontrados ===\n";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Busca também na tabela conversations
$stmt2 = $db->prepare("
    SELECT 
        id,
        conversation_key,
        channel_type,
        channel_id,
        contact_external_id,
        tenant_id,
        last_message_at,
        message_count
    FROM conversations
    WHERE contact_external_id LIKE ? OR contact_external_id LIKE ?
    ORDER BY last_message_at DESC
    LIMIT 5
");

$stmt2->execute(['%5599999999999%', '%5599999999999%']);
$conversations = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "=== Conversas Encontradas ===\n";
echo json_encode($conversations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

