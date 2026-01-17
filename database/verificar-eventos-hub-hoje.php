<?php
/**
 * Verificar eventos recebidos no Hub hoje (últimas 2 horas)
 * Foco: validar mapeamento/roteamento (channel_id, sessionId, tenant_id)
 */

// Carrega autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICAÇÃO DE EVENTOS NO HUB (Últimas 2 horas) ===\n\n";

// 1. Contar eventos por tipo e sessão
echo "1. Eventos por tipo e sessão:\n";
$stmt = $db->query("
    SELECT 
        ce.event_type,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) AS payload_session_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.sessionId')) AS payload_sessionId,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channelId')) AS payload_channelId,
        ce.tenant_id,
        COUNT(*) AS total
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    GROUP BY ce.event_type, channel_id, payload_session_id, payload_sessionId, payload_channelId, ce.tenant_id
    ORDER BY ce.created_at DESC
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "❌ Nenhum evento encontrado nas últimas 2 horas.\n\n";
} else {
    foreach ($events as $event) {
        $channelId = $event['channel_id'] ?: $event['payload_session_id'] ?: $event['payload_sessionId'] ?: $event['payload_channelId'] ?: 'NULL';
        $tenantId = $event['tenant_id'] ?? 'NULL';
        echo "  • {$event['event_type']} | channel_id: {$channelId} | tenant_id: {$tenantId} | total: {$event['total']}\n";
    }
}

echo "\n";

// 2. Eventos de mensagem (inbound/outbound) recentes
echo "2. Eventos de mensagem recentes (últimos 20):\n";
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_type,
        ce.tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')) AS channel_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')) AS payload_session_id,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.sessionId')) AS payload_sessionId,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channelId')) AS payload_channelId,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) AS p_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) AS p_to,
        ce.created_at
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
        AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($messages)) {
    echo "❌ Nenhuma mensagem encontrada nas últimas 2 horas.\n\n";
} else {
    foreach ($messages as $msg) {
        $channelId = $msg['channel_id'] ?: $msg['payload_session_id'] ?: $msg['payload_sessionId'] ?: $msg['payload_channelId'] ?: 'NULL';
        $tenantId = $msg['tenant_id'] ?? 'NULL';
        $from = $msg['p_from'] ?: 'N/A';
        $to = $msg['p_to'] ?: 'N/A';
        echo "  • ID: {$msg['id']} | {$msg['event_type']} | channel_id: {$channelId} | tenant_id: {$tenantId}\n";
        echo "    FROM: {$from} | TO: {$to} | {$msg['created_at']}\n";
    }
}

echo "\n";

// 3. Verificar eventos órfãos (tenant_id NULL)
echo "3. Eventos órfãos (tenant_id NULL) nas últimas 2 horas:\n";
$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM communication_events ce
    WHERE ce.source_system = 'wpp_gateway'
        AND ce.tenant_id IS NULL
        AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
");
$orphans = $stmt->fetch(PDO::FETCH_ASSOC);

if ($orphans['total'] > 0) {
    echo "⚠️  {$orphans['total']} eventos órfãos encontrados (tenant_id NULL).\n\n";
} else {
    echo "✅ Nenhum evento órfão encontrado.\n\n";
}

// 4. Verificar mapeamento de channel_id para tenant_id
echo "4. Mapeamento channel_id → tenant_id (tenant_message_channels):\n";
$stmt = $db->query("
    SELECT 
        channel_id,
        tenant_id,
        is_enabled
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    ORDER BY tenant_id, id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($channels)) {
    echo "❌ Nenhum canal configurado.\n\n";
} else {
    foreach ($channels as $channel) {
        $channelId = $channel['channel_id'] ?: 'NULL';
        $enabled = $channel['is_enabled'] ? '✅' : '❌';
        echo "  • {$enabled} channel_id: {$channelId} | tenant_id: {$channel['tenant_id']}\n";
    }
}

echo "\n=== FIM DA VERIFICAÇÃO ===\n";

