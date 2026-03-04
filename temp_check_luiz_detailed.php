<?php
$host = 'r225us.hmservers.net';
$dbname = 'pixel12digital_pixelhub';
$user = 'pixel12digital_pixelhub';
$pass = 'Los@ngo#081081';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO DETALHADA - LUIZ +55 16 98140-4507 ===\n\n";

// 1. Verificar webhooks recebidos hoje
echo "1. WEBHOOKS RECEBIDOS HOJE:\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type,
           SUBSTRING(payload_json, 1, 200) as payload_preview
    FROM webhook_raw_logs
    WHERE DATE(received_at) = CURDATE()
    ORDER BY received_at DESC
    LIMIT 10
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($webhooks as $w) {
    echo "   [{$w['id']}] {$w['received_at']} | {$w['event_type']}\n";
    echo "      Preview: " . substr($w['payload_preview'], 0, 150) . "...\n";
}

// 2. Buscar especificamente por 16981404507 nos webhooks
echo "\n2. BUSCANDO '16981404507' NOS WEBHOOKS (últimas 48h):\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, payload_json
    FROM webhook_raw_logs
    WHERE payload_json LIKE '%16981404507%'
      AND received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ORDER BY received_at DESC
    LIMIT 5
");
$found = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($found)) {
    echo "   ❌ NENHUM webhook encontrado com este número\n";
} else {
    foreach ($found as $f) {
        echo "   ✓ [{$f['id']}] {$f['received_at']} | {$f['event_type']}\n";
        $payload = json_decode($f['payload_json'], true);
        echo "      From: " . ($payload['from'] ?? 'N/A') . "\n";
        echo "      Body: " . ($payload['body'] ?? 'N/A') . "\n";
        echo "      Type: " . ($payload['type'] ?? 'N/A') . "\n";
    }
}

// 3. Buscar nos communication_events
echo "\n3. BUSCANDO '16981404507' NOS COMMUNICATION_EVENTS (últimas 48h):\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system, payload
    FROM communication_events
    WHERE payload LIKE '%16981404507%'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ORDER BY created_at DESC
    LIMIT 5
");
$found = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($found)) {
    echo "   ❌ NENHUM evento encontrado com este número\n";
} else {
    foreach ($found as $f) {
        echo "   ✓ [{$f['id']}] {$f['created_at']} | {$f['event_type']}\n";
        $payload = json_decode($f['payload'], true);
        echo "      From: " . ($payload['from'] ?? 'N/A') . "\n";
        echo "      Body: " . ($payload['body'] ?? 'N/A') . "\n";
    }
}

// 4. Verificar configuração dos canais WhatsApp
echo "\n4. CANAIS WHATSAPP CONFIGURADOS:\n";
$stmt = $pdo->query("
    SELECT c.id, c.tenant_id, c.channel_account_id, c.channel_name, c.is_active,
           t.nome as tenant_name
    FROM tenant_message_channels c
    LEFT JOIN tenants t ON t.id = c.tenant_id
    WHERE c.channel_type = 'whatsapp'
    ORDER BY c.is_active DESC, c.id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($channels as $ch) {
    $status = $ch['is_active'] ? '✓ ATIVO' : '✗ INATIVO';
    echo "   [{$ch['id']}] {$status} | Tenant: {$ch['tenant_name']} | Account: {$ch['channel_account_id']}\n";
}

// 5. Verificar últimos eventos inbound (recebidos)
echo "\n5. ÚLTIMOS 10 EVENTOS INBOUND (recebidos):\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type,
           JSON_EXTRACT(payload, '$.from') as from_number,
           JSON_EXTRACT(payload, '$.body') as message_body,
           conversation_id
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 10
");
$inbound = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($inbound as $msg) {
    echo "   [{$msg['id']}] {$msg['created_at']}\n";
    echo "      From: {$msg['from_number']}\n";
    echo "      Body: " . substr($msg['message_body'], 0, 100) . "\n";
    echo "      Conv: {$msg['conversation_id']}\n";
}

// 6. Verificar se existe conversa com este contato
echo "\n6. BUSCANDO CONVERSAS COM '16981404507':\n";
$stmt = $pdo->query("
    SELECT id, conversation_key, contact_external_id, last_message_at, status
    FROM conversations
    WHERE contact_external_id LIKE '%16981404507%'
    ORDER BY last_message_at DESC
");
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($convs)) {
    echo "   ❌ NENHUMA conversa encontrada\n";
} else {
    foreach ($convs as $c) {
        echo "   ✓ Conv [{$c['id']}] | Key: {$c['conversation_key']}\n";
        echo "      Contact: {$c['contact_external_id']}\n";
        echo "      Última msg: {$c['last_message_at']}\n";
        echo "      Status: {$c['status']}\n";
    }
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
