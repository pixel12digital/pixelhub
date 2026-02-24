<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Config\Database;
use PixelHub\Core\Env;

Env::load(__DIR__);
$db = Database::getInstance()->getConnection();

echo "=== INVESTIGAÇÃO: João Marques (6584) vs Douglas (3765) ===\n\n";

// 1. Buscar eventos de João Marques (terminação 6584)
echo "--- JOÃO MARQUES (6584) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        direction,
        channel_type,
        channel_account_id,
        contact_external_id,
        message_content,
        created_at,
        source_system
    FROM communication_events
    WHERE contact_external_id LIKE '%6584%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$joao_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($joao_events) . "\n";
foreach ($joao_events as $event) {
    echo sprintf(
        "[%s] %s | %s | Canal: %s | Contato: %s | Msg: %s\n",
        $event['created_at'],
        $event['event_type'],
        $event['direction'],
        $event['channel_account_id'],
        $event['contact_external_id'],
        substr($event['message_content'] ?? '', 0, 50)
    );
}

// 2. Buscar eventos de Douglas (terminação 3765)
echo "\n--- DOUGLAS (3765) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        direction,
        channel_type,
        channel_account_id,
        contact_external_id,
        message_content,
        created_at,
        source_system
    FROM communication_events
    WHERE contact_external_id LIKE '%3765%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$douglas_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($douglas_events) . "\n";
foreach ($douglas_events as $event) {
    echo sprintf(
        "[%s] %s | %s | Canal: %s | Contato: %s | Msg: %s\n",
        $event['created_at'],
        $event['event_type'],
        $event['direction'],
        $event['channel_account_id'],
        $event['contact_external_id'],
        substr($event['message_content'] ?? '', 0, 50)
    );
}

// 3. Verificar conversas
echo "\n--- CONVERSAS ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        channel_type,
        channel_account_id,
        contact_external_id,
        last_message_at,
        tenant_id
    FROM conversations
    WHERE contact_external_id LIKE '%6584%' OR contact_external_id LIKE '%3765%'
    ORDER BY last_message_at DESC
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de conversas: " . count($conversations) . "\n";
foreach ($conversations as $conv) {
    echo sprintf(
        "ID: %s | Key: %s | Canal: %s | Contato: %s | Última msg: %s\n",
        $conv['id'],
        $conv['conversation_key'],
        $conv['channel_account_id'],
        $conv['contact_external_id'],
        $conv['last_message_at']
    );
}

// 4. Verificar webhooks recentes (últimas 24h)
echo "\n--- WEBHOOKS RECENTES (últimas 24h) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        payload,
        created_at
    FROM webhook_raw_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND (
        JSON_EXTRACT(payload, '$.data.from') LIKE '%6584%'
        OR JSON_EXTRACT(payload, '$.data.from') LIKE '%3765%'
        OR JSON_EXTRACT(payload, '$.data.to') LIKE '%6584%'
        OR JSON_EXTRACT(payload, '$.data.to') LIKE '%3765%'
    )
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks: " . count($webhooks) . "\n";
foreach ($webhooks as $webhook) {
    $payload = json_decode($webhook['payload'], true);
    $from = $payload['data']['from'] ?? 'N/A';
    $to = $payload['data']['to'] ?? 'N/A';
    echo sprintf(
        "[%s] %s | From: %s | To: %s\n",
        $webhook['created_at'],
        $webhook['event_type'],
        $from,
        $to
    );
}

// 5. Verificar canais ativos
echo "\n--- CANAIS WHATSAPP ATIVOS ---\n";
$stmt = $db->query("
    SELECT 
        id,
        tenant_id,
        channel_type,
        channel_account_id,
        is_active
    FROM tenant_message_channels
    WHERE channel_type = 'whatsapp'
    ORDER BY tenant_id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($channels as $channel) {
    echo sprintf(
        "Tenant: %s | Canal: %s | Ativo: %s\n",
        $channel['tenant_id'],
        $channel['channel_account_id'],
        $channel['is_active'] ? 'SIM' : 'NÃO'
    );
}
