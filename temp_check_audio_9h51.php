<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

// Horário aproximado: 9h51 de hoje (2026-02-24)
$targetTime = '2026-02-24 09:51:00';
$startTime = '2026-02-24 09:45:00';
$endTime = '2026-02-24 09:55:00';

echo "=== VERIFICANDO WEBHOOKS RECEBIDOS ENTRE 09:45 E 09:55 ===\n\n";

// Busca webhooks brutos recebidos nesse período
$stmt = $db->prepare("
    SELECT id, event_type, received_at, processed, error_message,
           LEFT(payload_json, 200) as payload_preview
    FROM webhook_raw_logs 
    WHERE received_at BETWEEN ? AND ?
    ORDER BY received_at DESC
    LIMIT 20
");
$stmt->execute([$startTime, $endTime]);
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks: " . count($webhooks) . "\n\n";
foreach ($webhooks as $wh) {
    echo sprintf("ID: %d | Tipo: %s | Processado: %s | Erro: %s | Hora: %s\n",
        $wh['id'],
        $wh['event_type'] ?: 'NULL',
        $wh['processed'] ? 'SIM' : 'NÃO',
        $wh['error_message'] ?: 'NULL',
        $wh['received_at']
    );
    echo "Preview: " . substr($wh['payload_preview'], 0, 150) . "...\n\n";
}

echo "\n=== EVENTOS DE COMUNICAÇÃO NO MESMO PERÍODO ===\n\n";

// Busca eventos processados
$stmt = $db->prepare("
    SELECT id, event_type, tenant_id, source_system, created_at,
           JSON_EXTRACT(payload, '$.from') as msg_from,
           JSON_EXTRACT(payload, '$.message.from') as msg_from2,
           JSON_EXTRACT(payload, '$.message.type') as msg_type,
           JSON_EXTRACT(payload, '$.message.body') as msg_body
    FROM communication_events 
    WHERE created_at BETWEEN ? AND ?
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute([$startTime, $endTime]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos: " . count($events) . "\n\n";
foreach ($events as $event) {
    $from = $event['msg_from'] ?: $event['msg_from2'];
    echo sprintf("ID: %d | Tipo: %s | Tenant: %s | From: %s | MsgType: %s | Hora: %s\n",
        $event['id'],
        $event['event_type'],
        $event['tenant_id'] ?: 'NULL',
        $from ?: 'NULL',
        $event['msg_type'] ?: 'NULL',
        $event['created_at']
    );
}

echo "\n=== CONVERSAS ATUALIZADAS NO PERÍODO ===\n\n";

$stmt = $db->prepare("
    SELECT id, contact_external_id, contact_name, lead_id, tenant_id, 
           channel_id, status, last_message_at, updated_at
    FROM conversations 
    WHERE updated_at BETWEEN ? AND ?
    ORDER BY updated_at DESC
    LIMIT 10
");
$stmt->execute([$startTime, $endTime]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de conversas: " . count($conversations) . "\n\n";
foreach ($conversations as $conv) {
    echo sprintf("ID: %d | External ID: %s | Nome: %s | Lead: %s | Tenant: %s | Channel: %s | Atualizado: %s\n",
        $conv['id'],
        $conv['contact_external_id'],
        $conv['contact_name'] ?: 'NULL',
        $conv['lead_id'] ?: 'NULL',
        $conv['tenant_id'] ?: 'NULL',
        $conv['channel_id'] ?: 'NULL',
        $conv['updated_at']
    );
}

// Busca especificamente por telefone terminando em 5045
echo "\n=== EVENTOS DO LUIZ CARLOS (5045) HOJE ===\n\n";

$stmt = $db->prepare("
    SELECT id, event_type, tenant_id, created_at,
           JSON_EXTRACT(payload, '$.from') as msg_from,
           JSON_EXTRACT(payload, '$.message.from') as msg_from2,
           JSON_EXTRACT(payload, '$.message.type') as msg_type
    FROM communication_events 
    WHERE DATE(created_at) = '2026-02-24'
    AND (
        JSON_EXTRACT(payload, '$.from') LIKE '%5045%'
        OR JSON_EXTRACT(payload, '$.message.from') LIKE '%5045%'
    )
    ORDER BY created_at DESC
");
$stmt->execute();
$luizEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos do Luiz Carlos hoje: " . count($luizEvents) . "\n\n";
foreach ($luizEvents as $event) {
    $from = $event['msg_from'] ?: $event['msg_from2'];
    echo sprintf("ID: %d | Tipo: %s | MsgType: %s | Tenant: %s | From: %s | Hora: %s\n",
        $event['id'],
        $event['event_type'],
        $event['msg_type'] ?: 'NULL',
        $event['tenant_id'] ?: 'NULL',
        $from ?: 'NULL',
        $event['created_at']
    );
}
