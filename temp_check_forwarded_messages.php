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

echo "=== ANÁLISE DETALHADA DE MENSAGENS ENCAMINHADAS ===\n\n";

// Buscar os 3 eventos inbound da Kelly Costa
$stmt = $pdo->prepare("
    SELECT id, event_id, event_type, payload
    FROM communication_events
    WHERE conversation_id = 482
      AND event_type = 'whatsapp.inbound.message'
    ORDER BY created_at ASC
    LIMIT 3
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $idx => $event) {
    echo "\n=== EVENTO #{$idx} - ID: {$event['id']} ===\n";
    
    $payload = json_decode($event['payload'], true);
    
    // Verificar todos os campos possíveis que podem conter o texto
    echo "Campos do payload:\n";
    echo "- body: " . ($payload['body'] ?? 'N/A') . "\n";
    echo "- text: " . ($payload['text'] ?? 'N/A') . "\n";
    echo "- message.text: " . ($payload['message']['text'] ?? 'N/A') . "\n";
    echo "- message.body: " . ($payload['message']['body'] ?? 'N/A') . "\n";
    
    // Verificar raw payload
    if (isset($payload['raw']['payload'])) {
        $raw = $payload['raw']['payload'];
        echo "\nCampos do raw payload:\n";
        echo "- body: " . ($raw['body'] ?? 'N/A') . "\n";
        echo "- content: " . ($raw['content'] ?? 'N/A') . "\n";
        echo "- caption: " . ($raw['caption'] ?? 'N/A') . "\n";
        echo "- type: " . ($raw['type'] ?? 'N/A') . "\n";
        echo "- isForwarded: " . ($raw['isForwarded'] ? 'true' : 'false') . "\n";
        echo "- forwardingScore: " . ($raw['forwardingScore'] ?? 'N/A') . "\n";
        
        // Verificar se há quotedMsg (mensagem citada/encaminhada)
        if (isset($raw['quotedMsg'])) {
            echo "\nMensagem citada encontrada:\n";
            echo "- quotedMsg.body: " . ($raw['quotedMsg']['body'] ?? 'N/A') . "\n";
            echo "- quotedMsg.type: " . ($raw['quotedMsg']['type'] ?? 'N/A') . "\n";
        }
        
        // Verificar se há forwardedInfo
        if (isset($raw['forwardedInfo'])) {
            echo "\nForwarded info encontrada:\n";
            print_r($raw['forwardedInfo']);
        }
        
        // Verificar se é mídia
        if (isset($raw['mediaData']) && !empty($raw['mediaData'])) {
            echo "\nMídia encontrada:\n";
            print_r($raw['mediaData']);
        }
        
        // Mostrar todos os campos do raw para debug
        echo "\n\nTODOS OS CAMPOS DO RAW PAYLOAD:\n";
        foreach ($raw as $key => $value) {
            if (is_array($value) || is_object($value)) {
                echo "- {$key}: " . json_encode($value) . "\n";
            } else {
                echo "- {$key}: {$value}\n";
            }
        }
    }
    
    echo "\n" . str_repeat("-", 80) . "\n";
}

echo "\n=== FIM DA ANÁLISE ===\n";
