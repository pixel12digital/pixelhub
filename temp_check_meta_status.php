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

echo "=== DIAGNÓSTICO META OFFICIAL API ===\n\n";

// 1. Verificar canais Meta configurados
echo "1. CANAIS META OFFICIAL API:\n";
$stmt = $pdo->query("
    SELECT c.*, t.name as tenant_name
    FROM tenant_message_channels c
    LEFT JOIN tenants t ON t.id = c.tenant_id
    WHERE c.provider_type = 'meta_official'
    ORDER BY c.is_enabled DESC, c.id
");
$metaChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($metaChannels)) {
    echo "   ❌ NENHUM canal Meta configurado\n";
} else {
    foreach ($metaChannels as $ch) {
        $status = $ch['is_enabled'] ? '✓ ATIVO' : '✗ INATIVO';
        echo "   [{$ch['id']}] {$status} | Tenant: {$ch['tenant_name']} (ID: {$ch['tenant_id']})\n";
        echo "      Channel ID: {$ch['channel_id']}\n";
        echo "      Webhook Configured: " . ($ch['webhook_configured'] ? 'SIM' : 'NÃO') . "\n";
        echo "      Created: {$ch['created_at']}\n";
        echo "      Updated: {$ch['updated_at']}\n";
        echo "\n";
    }
}

// 2. Verificar configurações Meta (whatsapp_provider_configs)
echo "\n2. CONFIGURAÇÕES META (whatsapp_provider_configs):\n";
$stmt = $pdo->query("
    SELECT id, tenant_id, provider_type, meta_phone_number_id, 
           meta_business_account_id, is_active, created_at, updated_at
    FROM whatsapp_provider_configs
    WHERE provider_type = 'meta_official'
    ORDER BY is_active DESC, id
");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($configs)) {
    echo "   ❌ NENHUMA configuração Meta encontrada\n";
} else {
    foreach ($configs as $cfg) {
        $status = $cfg['is_active'] ? '✓ ATIVO' : '✗ INATIVO';
        echo "   [{$cfg['id']}] {$status} | Tenant ID: {$cfg['tenant_id']}\n";
        echo "      Phone Number ID: {$cfg['meta_phone_number_id']}\n";
        echo "      Business Account ID: {$cfg['meta_business_account_id']}\n";
        echo "      Created: {$cfg['created_at']}\n";
        echo "      Updated: {$cfg['updated_at']}\n";
        echo "\n";
    }
}

// 3. Últimos webhooks recebidos (últimas 2 horas)
echo "\n3. ÚLTIMOS WEBHOOKS RECEBIDOS (últimas 2 horas):\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, processed,
           SUBSTRING(payload_json, 1, 150) as payload_preview
    FROM webhook_raw_logs
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY received_at DESC
    LIMIT 20
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($webhooks)) {
    echo "   ❌ NENHUM webhook recebido nas últimas 2 horas\n";
} else {
    echo "   Total: " . count($webhooks) . " webhooks\n\n";
    foreach ($webhooks as $w) {
        $proc = $w['processed'] ? '✓' : '✗';
        echo "   [{$w['id']}] {$proc} {$w['received_at']} | {$w['event_type']}\n";
    }
}

// 4. Últimos eventos processados (últimas 2 horas)
echo "\n\n4. ÚLTIMOS EVENTOS PROCESSADOS (últimas 2 horas):\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system, status,
           JSON_EXTRACT(payload, '$.from') as from_number,
           JSON_EXTRACT(payload, '$.body') as message_body
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 20
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($events)) {
    echo "   ❌ NENHUM evento processado nas últimas 2 horas\n";
} else {
    echo "   Total: " . count($events) . " eventos\n\n";
    foreach ($events as $e) {
        echo "   [{$e['id']}] {$e['created_at']} | {$e['event_type']}\n";
        echo "      Source: {$e['source_system']} | Status: {$e['status']}\n";
        echo "      From: {$e['from_number']}\n";
        if (!empty($e['message_body'])) {
            $body = substr($e['message_body'], 0, 100);
            echo "      Body: {$body}\n";
        }
        echo "\n";
    }
}

// 5. Verificar quando foi a última mensagem recebida
echo "\n5. ÚLTIMA MENSAGEM RECEBIDA:\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system,
           JSON_EXTRACT(payload, '$.from') as from_number
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    ORDER BY created_at DESC
    LIMIT 1
");
$last = $stmt->fetch(PDO::FETCH_ASSOC);
if ($last) {
    echo "   Última mensagem inbound:\n";
    echo "   ID: {$last['id']}\n";
    echo "   Timestamp: {$last['created_at']}\n";
    echo "   Source: {$last['source_system']}\n";
    echo "   From: {$last['from_number']}\n";
    
    // Calcular há quanto tempo
    $lastTime = new DateTime($last['created_at']);
    $now = new DateTime();
    $diff = $now->diff($lastTime);
    echo "   Há: {$diff->h}h {$diff->i}min {$diff->s}s atrás\n";
} else {
    echo "   ❌ NENHUMA mensagem inbound encontrada\n";
}

// 6. Verificar erros recentes no processamento
echo "\n\n6. ERROS RECENTES NO PROCESSAMENTO:\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, source_system, error_message
    FROM communication_events
    WHERE status = 'failed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($errors)) {
    echo "   ✓ Nenhum erro nas últimas 2 horas\n";
} else {
    foreach ($errors as $err) {
        echo "   [{$err['id']}] {$err['created_at']} | {$err['event_type']}\n";
        echo "      Source: {$err['source_system']}\n";
        echo "      Erro: {$err['error_message']}\n";
        echo "\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
