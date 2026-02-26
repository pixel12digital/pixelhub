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

echo "=== TODOS OS EVENTOS DA KELLY COSTA (CONVERSA 482) ===\n\n";

// Buscar TODOS os eventos da conversa 482
$stmt = $pdo->prepare("
    SELECT id, event_id, event_type, payload, created_at
    FROM communication_events
    WHERE conversation_id = 482
    ORDER BY created_at ASC
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de eventos: " . count($events) . "\n\n";

foreach ($events as $idx => $event) {
    echo "=== EVENTO #{$idx} - ID: {$event['id']} ===\n";
    echo "Event ID: {$event['event_id']}\n";
    echo "Type: {$event['event_type']}\n";
    echo "Created: {$event['created_at']}\n";
    
    $payload = json_decode($event['payload'], true);
    
    // Extrair informações relevantes
    $messageType = $payload['raw']['payload']['type'] ?? $payload['message']['type'] ?? 'N/A';
    $subtype = $payload['raw']['payload']['subtype'] ?? 'N/A';
    $fromMe = $payload['raw']['payload']['fromMe'] ?? $payload['fromMe'] ?? false;
    
    // Tentar extrair texto de vários campos possíveis
    $text = $payload['body'] 
        ?? $payload['text'] 
        ?? $payload['message']['body'] 
        ?? $payload['message']['text']
        ?? $payload['raw']['payload']['body']
        ?? $payload['raw']['payload']['text']
        ?? $payload['raw']['payload']['content']
        ?? '';
    
    echo "Message Type: {$messageType}\n";
    echo "Subtype: {$subtype}\n";
    echo "From Me: " . ($fromMe ? 'true' : 'false') . "\n";
    echo "Text Length: " . strlen($text) . " chars\n";
    
    if (!empty($text)) {
        echo "Text Preview: " . substr($text, 0, 200) . "\n";
    } else {
        echo "Text: VAZIO\n";
    }
    
    // Verificar se há mídia
    $mediaData = $payload['raw']['payload']['mediaData'] ?? [];
    if (!empty($mediaData)) {
        echo "Mídia: SIM\n";
    }
    
    echo "\n" . str_repeat("-", 80) . "\n\n";
}

echo "=== FIM DA ANÁLISE ===\n";
