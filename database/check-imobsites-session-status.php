<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== INVESTIGAÇÃO DA SESSÃO IMOBSSITES ===\n\n";

$db = DB::getConnection();

// 1. Verificar canais configurados para ImobSites
echo "1. CANAIS CONFIGURADOS PARA IMOBSSITES:\n";
$stmt = $db->prepare("
    SELECT tmc.*, t.name as tenant_name
    FROM tenant_message_channels tmc
    JOIN tenants t ON tmc.tenant_id = t.id
    WHERE t.name LIKE '%ImobSites%' OR t.name LIKE '%imobsites%'
    ORDER BY tmc.id
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($channels as $channel) {
    echo sprintf(
        "  - Tenant: %s (ID: %d)\n    Channel ID: %s\n    Provider: %s\n    Enabled: %s\n    Created: %s\n\n",
        $channel['tenant_name'],
        $channel['tenant_id'],
        $channel['channel_id'],
        $channel['provider'],
        $channel['is_enabled'] ? 'YES' : 'NO',
        $channel['created_at']
    );
}

// 2. Verificar eventos recentes da sessão imobsites
echo "2. EVENTOS RECENTES DA SESSÃO IMOBSSITES (últimas 24h):\n";
$stmt = $db->prepare("
    SELECT 
        event_type,
        source_system,
        created_at,
        JSON_EXTRACT(payload, '$.session.id') as session_id,
        JSON_EXTRACT(payload, '$.sessionId') as session_id_alt,
        JSON_EXTRACT(payload, '$.event') as wpp_event,
        JSON_EXTRACT(payload, '$.state') as wpp_state
    FROM communication_events
    WHERE (JSON_EXTRACT(payload, '$.session.id') = 'imobsites' 
           OR JSON_EXTRACT(payload, '$.sessionId') = 'imobsites'
           OR JSON_EXTRACT(payload, '$.session.session') = 'imobsites')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "  Nenhum evento encontrado nas últimas 24h\n\n";
} else {
    foreach ($events as $event) {
        echo sprintf(
            "  - %s | %s | %s\n    Session: %s/%s\n    Event: %s | State: %s\n\n",
            $event['created_at'],
            $event['event_type'],
            $event['source_system'],
            $event['session_id'] ?: 'NULL',
            $event['session_id_alt'] ?: 'NULL',
            $event['wpp_event'] ?: 'NULL',
            $event['wpp_state'] ?: 'NULL'
        );
    }
}

// 3. Verificar logs brutos de webhooks para imobsites
echo "3. WEBHOOKS BRUTOS RECENTES (imobsites):\n";
$stmt = $db->prepare("
    SELECT 
        created_at,
        event_type,
        JSON_EXTRACT(body, '$.session.id') as session_id,
        JSON_EXTRACT(body, '$.sessionId') as session_id_alt,
        JSON_EXTRACT(body, '$.event') as wpp_event,
        JSON_EXTRACT(body, '$.state') as wpp_state,
        SUBSTRING(body, 1, 200) as body_preview
    FROM webhook_raw_logs
    WHERE (JSON_EXTRACT(body, '$.session.id') = 'imobsites' 
           OR JSON_EXTRACT(body, '$.sessionId') = 'imobsites'
           OR JSON_EXTRACT(body, '$.session.session') = 'imobsites')
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($webhooks)) {
    echo "  Nenhum webhook bruto encontrado nas últimas 24h\n\n";
} else {
    foreach ($webhooks as $webhook) {
        echo sprintf(
            "  - %s | %s\n    Session: %s/%s\n    Event: %s | State: %s\n    Preview: %s...\n\n",
            $webhook['created_at'],
            $webhook['event_type'],
            $webhook['session_id'] ?: 'NULL',
            $webhook['session_id_alt'] ?: 'NULL',
            $webhook['wpp_event'] ?: 'NULL',
            $webhook['wpp_state'] ?: 'NULL',
            $webhook['body_preview']
        );
    }
}

// 4. Verificar tentativas de conexão/desconexão
echo "4. TENTATIVAS DE CONEXÃO/DISCONECTION:\n";
$stmt = $db->prepare("
    SELECT 
        created_at,
        event_type,
        source_system,
        JSON_EXTRACT(payload, '$.event') as wpp_event,
        JSON_EXTRACT(payload, '$.state') as wpp_state,
        JSON_EXTRACT(payload, '$.reason') as reason
    FROM communication_events
    WHERE (JSON_EXTRACT(payload, '$.session.id') = 'imobsites' 
           OR JSON_EXTRACT(payload, '$.sessionId') = 'imobsites'
           OR JSON_EXTRACT(payload, '$.session.session') = 'imobsites')
    AND event_type IN ('connection.update', 'connection.status', 'connection.state')
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($connections)) {
    echo "  Nenhum evento de conexão encontrado\n\n";
} else {
    foreach ($connections as $conn) {
        echo sprintf(
            "  - %s | %s | %s\n    Event: %s | State: %s\n    Reason: %s\n\n",
            $conn['created_at'],
            $conn['event_type'],
            $conn['source_system'],
            $conn['wpp_event'] ?: 'NULL',
            $conn['wpp_state'] ?: 'NULL',
            $conn['reason'] ?: 'NULL'
        );
    }
}

// 5. Verificar se há erros recentes
echo "5. ERROS RECENTES RELACIONADOS À IMOBSSITES:\n";
$stmt = $db->prepare("
    SELECT 
        created_at,
        event_type,
        source_system,
        JSON_EXTRACT(payload, '$.error') as error,
        JSON_EXTRACT(payload, '$.message') as error_message,
        JSON_EXTRACT(payload, '$.code') as error_code
    FROM communication_events
    WHERE (JSON_EXTRACT(payload, '$.session.id') = 'imobsites' 
           OR JSON_EXTRACT(payload, '$.sessionId') = 'imobsites'
           OR JSON_EXTRACT(payload, '$.session.session') = 'imobsites')
    AND (JSON_EXTRACT(payload, '$.error') IS NOT NULL 
         OR JSON_EXTRACT(payload, '$.message') LIKE '%error%'
         OR JSON_EXTRACT(payload, '$.message') LIKE '%Error%'
         OR JSON_EXTRACT(payload, '$.message') LIKE '%ERROR%')
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($errors)) {
    echo "  Nenhum erro encontrado\n\n";
} else {
    foreach ($errors as $error) {
        echo sprintf(
            "  - %s | %s | %s\n    Error: %s\n    Message: %s\n    Code: %s\n\n",
            $error['created_at'],
            $error['event_type'],
            $error['source_system'],
            $error['error'] ?: 'NULL',
            $error['error_message'] ?: 'NULL',
            $error['error_code'] ?: 'NULL'
        );
    }
}

echo "=== FIM DA INVESTIGAÇÃO ===\n";
