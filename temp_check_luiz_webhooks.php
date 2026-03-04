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

echo "=== WEBHOOKS DO LUIZ ENCONTRADOS ===\n\n";

$stmt = $pdo->query("
    SELECT id, received_at, event_type, processed, event_id, error_message, payload_json
    FROM webhook_raw_logs
    WHERE payload_json LIKE '%98140-4507%'
      AND received_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ORDER BY received_at DESC
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($webhooks) . "\n\n";

foreach ($webhooks as $w) {
    $proc = $w['processed'] ? '✓ PROCESSADO' : '✗ NÃO PROCESSADO';
    echo "━━━ WEBHOOK [{$w['id']}] {$proc} ━━━\n";
    echo "Recebido: {$w['received_at']}\n";
    echo "Event Type: {$w['event_type']}\n";
    echo "Event ID: " . ($w['event_id'] ?: 'NULL') . "\n";
    
    if ($w['error_message']) {
        echo "Erro: {$w['error_message']}\n";
    }
    
    $payload = json_decode($w['payload_json'], true);
    
    if ($payload) {
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        $to = $payload['to'] ?? $payload['message']['to'] ?? 'N/A';
        $sessionId = $payload['session']['id'] ?? $payload['channelId'] ?? 'N/A';
        $messageType = $payload['raw']['payload']['type'] ?? $payload['type'] ?? 'N/A';
        $body = $payload['body'] ?? $payload['message']['text']['body'] ?? $payload['raw']['payload']['body'] ?? 'N/A';
        
        echo "From: {$from}\n";
        echo "To: {$to}\n";
        echo "Session: {$sessionId}\n";
        echo "Message Type: {$messageType}\n";
        echo "Body: " . substr($body, 0, 150) . "\n";
    }
    
    echo "\n";
}

echo "=== FIM ===\n";
