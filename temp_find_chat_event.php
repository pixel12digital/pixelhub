<?php
// Carregar .env manualmente
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "=== BUSCAR EVENTO TIPO 'CHAT' DA KELLY ===\n\n";

// Buscar evento com o message_id específico
$messageId = 'false_65803193995481@lid_ACAE2BABE023A6D8AF75356A7E13B61A';

$stmt = $pdo->prepare("
    SELECT id, event_id, event_type, payload, created_at
    FROM communication_events
    WHERE payload LIKE ?
    ORDER BY created_at ASC
");
$stmt->execute(['%' . $messageId . '%']);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos com message_id '{$messageId}': " . count($events) . "\n\n";

foreach ($events as $idx => $event) {
    echo "=== EVENTO #{$idx} - ID: {$event['id']} ===\n";
    echo "Event ID: {$event['event_id']}\n";
    echo "Type: {$event['event_type']}\n";
    echo "Created: {$event['created_at']}\n";
    
    $payload = json_decode($event['payload'], true);
    $messageType = $payload['raw']['payload']['type'] ?? 'N/A';
    $body = $payload['raw']['payload']['body'] ?? $payload['body'] ?? '';
    
    echo "Message Type: {$messageType}\n";
    echo "Body Length: " . strlen($body) . " chars\n";
    
    if (!empty($body)) {
        echo "Body Preview: " . substr($body, 0, 100) . "...\n";
    } else {
        echo "Body: VAZIO\n";
    }
    
    echo "\n" . str_repeat("-", 80) . "\n\n";
}

echo "\n=== BUSCAR NO WEBHOOK_RAW_LOGS ===\n\n";

$stmt = $pdo->prepare("
    SELECT id, created_at, payload_json
    FROM webhook_raw_logs
    WHERE payload_json LIKE ?
    ORDER BY created_at ASC
");
$stmt->execute(['%' . $messageId . '%']);
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks com message_id: " . count($webhooks) . "\n\n";

foreach ($webhooks as $idx => $webhook) {
    echo "Webhook #{$idx} - ID: {$webhook['id']}\n";
    echo "Created: {$webhook['created_at']}\n";
    
    $payload = json_decode($webhook['payload_json'], true);
    $messageType = $payload['raw']['payload']['type'] ?? 'N/A';
    $body = $payload['raw']['payload']['body'] ?? '';
    
    echo "Type: {$messageType}\n";
    echo "Body Length: " . strlen($body) . " chars\n";
    
    if (!empty($body)) {
        echo "✓ TEM TEXTO!\n";
    } else {
        echo "✗ SEM TEXTO\n";
    }
    
    echo "\n";
}

echo "\n=== FIM ===\n";
