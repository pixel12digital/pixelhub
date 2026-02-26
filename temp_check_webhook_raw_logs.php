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

echo "=== WEBHOOK RAW LOGS - KELLY COSTA ===\n\n";

// Buscar webhook_raw_logs para o horário aproximado (04:02 do dia 26/02/2026)
$stmt = $pdo->prepare("
    SELECT id, payload, created_at
    FROM webhook_raw_logs
    WHERE created_at >= '2026-02-26 04:00:00'
      AND created_at <= '2026-02-26 04:05:00'
      AND payload LIKE '%65803193995481@lid%'
    ORDER BY created_at ASC
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks encontrados: " . count($logs) . "\n\n";

foreach ($logs as $idx => $log) {
    echo "=== WEBHOOK #{$idx} - ID: {$log['id']} ===\n";
    echo "Created: {$log['created_at']}\n\n";
    
    $payload = json_decode($log['payload'], true);
    
    if ($payload) {
        // Extrair tipo de mensagem
        $messageType = $payload['raw']['payload']['type'] ?? $payload['message']['type'] ?? 'N/A';
        $subtype = $payload['raw']['payload']['subtype'] ?? 'N/A';
        
        echo "Message Type: {$messageType}\n";
        echo "Subtype: {$subtype}\n";
        
        // Tentar extrair texto de TODOS os campos possíveis
        $possibleTextFields = [
            'body' => $payload['body'] ?? null,
            'text' => $payload['text'] ?? null,
            'message.body' => $payload['message']['body'] ?? null,
            'message.text' => $payload['message']['text'] ?? null,
            'raw.payload.body' => $payload['raw']['payload']['body'] ?? null,
            'raw.payload.text' => $payload['raw']['payload']['text'] ?? null,
            'raw.payload.content' => $payload['raw']['payload']['content'] ?? null,
            'raw.payload.caption' => $payload['raw']['payload']['caption'] ?? null,
        ];
        
        echo "\nCampos de texto:\n";
        $foundText = false;
        foreach ($possibleTextFields as $field => $value) {
            if ($value !== null && $value !== '') {
                echo "  ✓ {$field}: " . substr($value, 0, 100) . "\n";
                $foundText = true;
            }
        }
        
        if (!$foundText) {
            echo "  ❌ Nenhum campo de texto encontrado\n";
        }
        
        // Mostrar payload completo se for ciphertext
        if ($messageType === 'ciphertext') {
            echo "\n--- PAYLOAD COMPLETO (CIPHERTEXT) ---\n";
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo "ERRO: Payload inválido\n";
        echo "Raw: " . substr($log['payload'], 0, 500) . "\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

echo "=== FIM DA ANÁLISE ===\n";
