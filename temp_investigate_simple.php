<?php
// Script simples de investigação - lê .env manualmente
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

echo "=== INVESTIGAÇÃO: João Marques (6584) vs Douglas (3765) ===\n\n";

// 1. Buscar eventos de João Marques (terminação 6584)
echo "--- JOÃO MARQUES (6584) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        payload,
        status,
        created_at
    FROM communication_events
    WHERE payload LIKE '%6584%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$joao_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($joao_events) . "\n\n";
foreach ($joao_events as $event) {
    $payload = json_decode($event['payload'], true);
    $from = $payload['from'] ?? 'N/A';
    $to = $payload['to'] ?? 'N/A';
    $body = $payload['body'] ?? '';
    
    echo sprintf(
        "[%s] %s | Status: %s | Tenant: %s\n",
        $event['created_at'],
        $event['event_type'],
        $event['status'],
        $event['tenant_id'] ?? 'N/A'
    );
    echo "  From: $from | To: $to\n";
    echo "  Body: " . substr($body, 0, 80) . "\n";
    echo "  Source: " . $event['source_system'] . "\n\n";
}

// 2. Buscar eventos de Douglas (terminação 3765)
echo "\n--- DOUGLAS (3765) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        payload,
        status,
        created_at
    FROM communication_events
    WHERE payload LIKE '%3765%'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$douglas_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos encontrados: " . count($douglas_events) . "\n\n";
foreach ($douglas_events as $event) {
    $payload = json_decode($event['payload'], true);
    $from = $payload['from'] ?? 'N/A';
    $to = $payload['to'] ?? 'N/A';
    $body = $payload['body'] ?? '';
    
    echo sprintf(
        "[%s] %s | Status: %s | Tenant: %s\n",
        $event['created_at'],
        $event['event_type'],
        $event['status'],
        $event['tenant_id'] ?? 'N/A'
    );
    echo "  From: $from | To: $to\n";
    echo "  Body: " . substr($body, 0, 80) . "\n";
    echo "  Source: " . $event['source_system'] . "\n\n";
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

echo "Total de conversas: " . count($conversations) . "\n\n";
foreach ($conversations as $conv) {
    echo sprintf(
        "ID: %s | Tenant: %s | Canal: %s | Contato: %s\n",
        $conv['id'],
        $conv['tenant_id'],
        $conv['channel_account_id'],
        $conv['contact_external_id']
    );
    echo "  Key: " . $conv['conversation_key'] . "\n";
    echo "  Última msg: " . $conv['last_message_at'] . "\n\n";
}

// 4. Verificar canais ativos
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

// 5. Buscar webhooks recentes específicos
echo "\n--- WEBHOOKS RECENTES (últimas 48h) ---\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_type,
        payload,
        created_at,
        processed
    FROM webhook_raw_logs
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    AND (
        payload LIKE '%6584%'
        OR payload LIKE '%3765%'
    )
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute();
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks: " . count($webhooks) . "\n\n";
foreach ($webhooks as $webhook) {
    $payload = json_decode($webhook['payload'], true);
    $from = $payload['data']['from'] ?? 'N/A';
    $to = $payload['data']['to'] ?? 'N/A';
    $body = $payload['data']['body'] ?? '';
    
    echo sprintf(
        "[%s] %s | Processado: %s\n",
        $webhook['created_at'],
        $webhook['event_type'],
        $webhook['processed'] ? 'SIM' : 'NÃO'
    );
    echo "  From: $from | To: $to\n";
    echo "  Body: " . substr($body, 0, 60) . "\n\n";
}

echo "\n=== FIM DA INVESTIGAÇÃO ===\n";
