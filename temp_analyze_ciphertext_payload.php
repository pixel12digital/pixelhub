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

echo "=== ANÁLISE DETALHADA DO PAYLOAD CIPHERTEXT ===\n\n";

// Buscar o evento ciphertext (ID 191084)
$stmt = $pdo->prepare("
    SELECT id, event_id, event_type, payload, created_at
    FROM communication_events
    WHERE id = 191084
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento 191084 não encontrado\n";
    exit;
}

echo "Evento ID: {$event['id']}\n";
echo "Event ID: {$event['event_id']}\n";
echo "Type: {$event['event_type']}\n";
echo "Created: {$event['created_at']}\n\n";

$payload = json_decode($event['payload'], true);

echo "=== ESTRUTURA COMPLETA DO PAYLOAD ===\n\n";

// Função recursiva para mostrar estrutura
function printStructure($data, $prefix = '') {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                echo "{$prefix}{$key}:\n";
                printStructure($value, $prefix . '  ');
            } else {
                $displayValue = is_string($value) ? substr($value, 0, 100) : $value;
                echo "{$prefix}{$key}: {$displayValue}\n";
            }
        }
    }
}

printStructure($payload);

echo "\n\n=== CAMPOS ESPECÍFICOS DE INTERESSE ===\n\n";

// Verificar todos os possíveis locais do texto
$possibleTextFields = [
    'body' => $payload['body'] ?? null,
    'text' => $payload['text'] ?? null,
    'message.body' => $payload['message']['body'] ?? null,
    'message.text' => $payload['message']['text'] ?? null,
    'raw.payload.body' => $payload['raw']['payload']['body'] ?? null,
    'raw.payload.text' => $payload['raw']['payload']['text'] ?? null,
    'raw.payload.content' => $payload['raw']['payload']['content'] ?? null,
    'raw.payload.caption' => $payload['raw']['payload']['caption'] ?? null,
    'data.body' => $payload['data']['body'] ?? null,
    'data.text' => $payload['data']['text'] ?? null,
];

foreach ($possibleTextFields as $field => $value) {
    if ($value !== null) {
        echo "✓ {$field}: {$value}\n";
    } else {
        echo "✗ {$field}: NULL\n";
    }
}

echo "\n\n=== RAW PAYLOAD COMPLETO ===\n\n";
if (isset($payload['raw']['payload'])) {
    echo json_encode($payload['raw']['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

echo "\n\n=== FIM DA ANÁLISE ===\n";
