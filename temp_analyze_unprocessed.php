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

echo "=== ANÁLISE DOS WEBHOOKS NÃO PROCESSADOS ===\n\n";

// Pegar 3 webhooks não processados para análise detalhada
echo "1. ANÁLISE DETALHADA DE 3 WEBHOOKS NÃO PROCESSADOS:\n\n";
$stmt = $pdo->query("
    SELECT id, received_at, event_type, payload_json
    FROM webhook_raw_logs
    WHERE processed = 0
      AND received_at >= '2026-03-04 12:00:00'
    ORDER BY received_at DESC
    LIMIT 3
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($webhooks as $w) {
    echo "━━━ WEBHOOK [{$w['id']}] - {$w['received_at']} ━━━\n";
    echo "Event Type: {$w['event_type']}\n\n";
    
    $payload = json_decode($w['payload_json'], true);
    
    if ($payload) {
        // Extrair informações chave
        $event = $payload['event'] ?? $payload['type'] ?? 'N/A';
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'N/A';
        $to = $payload['to'] ?? $payload['message']['to'] ?? 'N/A';
        
        // Tentar extrair session/channel_id de múltiplas formas
        $sessionId = $payload['session']['id'] 
            ?? $payload['session']['session']
            ?? $payload['channelId']
            ?? $payload['channel']
            ?? $payload['data']['session']['id']
            ?? $payload['metadata']['session_id']
            ?? 'N/A';
        
        $messageType = $payload['raw']['payload']['type'] ?? $payload['type'] ?? 'N/A';
        $body = $payload['body'] ?? $payload['message']['text']['body'] ?? 'N/A';
        
        echo "Event: {$event}\n";
        echo "From: {$from}\n";
        echo "To: {$to}\n";
        echo "Session/Channel ID: {$sessionId}\n";
        echo "Message Type: {$messageType}\n";
        echo "Body: " . substr($body, 0, 100) . "\n";
        
        // Mostrar estrutura do payload
        echo "\nEstruturas disponíveis no payload:\n";
        echo "  - Chaves raiz: " . implode(', ', array_keys($payload)) . "\n";
        
        if (isset($payload['session'])) {
            echo "  - payload[session]: " . implode(', ', array_keys($payload['session'])) . "\n";
        }
        if (isset($payload['message'])) {
            echo "  - payload[message]: " . implode(', ', array_keys($payload['message'])) . "\n";
        }
        if (isset($payload['data'])) {
            echo "  - payload[data]: " . implode(', ', array_keys($payload['data'])) . "\n";
        }
        
        echo "\n";
    } else {
        echo "⚠️  Payload JSON inválido\n\n";
    }
}

// Verificar se o problema é específico de um tipo de evento
echo "\n2. DISTRIBUIÇÃO POR TIPO DE EVENTO (não processados, últimas 24h):\n";
$stmt = $pdo->query("
    SELECT event_type, COUNT(*) as total
    FROM webhook_raw_logs
    WHERE processed = 0
      AND received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY event_type
    ORDER BY total DESC
");
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($types as $t) {
    echo "   {$t['event_type']}: {$t['total']} webhooks\n";
}

echo "\n=== FIM DA ANÁLISE ===\n";
