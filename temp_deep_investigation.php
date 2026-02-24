<?php
// Investigação profunda dos eventos
$envFile = __DIR__ . '/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $envVars[trim($key)] = trim($value);
    }
}

$host = $envVars['DB_HOST'] ?? 'localhost';
$dbname = $envVars['DB_NAME'] ?? '';
$username = $envVars['DB_USER'] ?? '';
$password = $envVars['DB_PASS'] ?? '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage() . "\n");
}

echo "=== INVESTIGAÇÃO PROFUNDA ===\n\n";

// 1. DOUGLAS - Eventos travados em "processing"
echo "--- DOUGLAS (3765) - EVENTOS TRAVADOS ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        payload,
        metadata,
        status,
        error_message,
        retry_count,
        created_at,
        processed_at
    FROM communication_events
    WHERE payload LIKE '%3765%'
    AND created_at >= '2026-02-23 19:00:00'
    ORDER BY created_at DESC
");
$stmt->execute();
$douglas_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($douglas_events) . " eventos\n\n";
foreach ($douglas_events as $event) {
    $payload = json_decode($event['payload'], true);
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$event['id']} | Event ID: {$event['event_id']}\n";
    echo "Tipo: {$event['event_type']} | Status: {$event['status']}\n";
    echo "Tenant: " . ($event['tenant_id'] ?? 'NULL') . " | Retry: {$event['retry_count']}\n";
    echo "Criado: {$event['created_at']} | Processado: " . ($event['processed_at'] ?? 'NULL') . "\n";
    
    if ($event['error_message']) {
        echo "❌ ERRO: {$event['error_message']}\n";
    }
    
    echo "\nPAYLOAD:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($event['metadata']) {
        echo "\nMETADATA:\n";
        echo json_encode(json_decode($event['metadata'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n";
}

// 2. JOÃO MARQUES - Evento que falhou
echo "\n--- JOÃO MARQUES (6584) - EVENTO FAILED ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        payload,
        metadata,
        status,
        error_message,
        retry_count,
        created_at,
        processed_at
    FROM communication_events
    WHERE payload LIKE '%6584%'
    AND status = 'failed'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$joao_failed = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($joao_failed) . " eventos falhados\n\n";
foreach ($joao_failed as $event) {
    $payload = json_decode($event['payload'], true);
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$event['id']} | Event ID: {$event['event_id']}\n";
    echo "Tipo: {$event['event_type']} | Status: {$event['status']}\n";
    echo "Tenant: " . ($event['tenant_id'] ?? 'NULL') . " | Retry: {$event['retry_count']}\n";
    echo "Criado: {$event['created_at']} | Processado: " . ($event['processed_at'] ?? 'NULL') . "\n";
    
    if ($event['error_message']) {
        echo "❌ ERRO: {$event['error_message']}\n";
    }
    
    echo "\nPAYLOAD:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

// 3. Verificar canais de mensagem
echo "\n--- CANAIS DE MENSAGEM (tenant_message_channels) ---\n";
$stmt = $db->query("
    SELECT 
        id,
        tenant_id,
        wa_session_id,
        wa_phone_number,
        priority,
        is_active,
        created_at
    FROM tenant_message_channels
    ORDER BY tenant_id, priority
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($channels as $channel) {
    echo sprintf(
        "ID: %s | Tenant: %s | Session: %s | Phone: %s | Priority: %s | Ativo: %s\n",
        $channel['id'],
        $channel['tenant_id'],
        $channel['wa_session_id'],
        $channel['wa_phone_number'],
        $channel['priority'],
        $channel['is_active'] ? 'SIM' : 'NÃO'
    );
}

// 4. Verificar webhooks brutos
echo "\n--- WEBHOOKS BRUTOS (últimas 24h) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        payload,
        processed,
        created_at
    FROM webhook_raw_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND (payload LIKE '%3765%' OR payload LIKE '%6584%')
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total: " . count($webhooks) . " webhooks\n\n";
foreach ($webhooks as $webhook) {
    $payload = json_decode($webhook['payload'], true);
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID: {$webhook['id']} | Tipo: {$webhook['event_type']}\n";
    echo "Processado: " . ($webhook['processed'] ? 'SIM' : 'NÃO') . " | Criado: {$webhook['created_at']}\n";
    echo "\nPAYLOAD:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
