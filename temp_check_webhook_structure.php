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

echo "=== ESTRUTURA webhook_raw_logs ===\n\n";
$stmt = $pdo->query("SHOW COLUMNS FROM webhook_raw_logs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== BUSCAR WEBHOOKS KELLY COSTA ===\n\n";

// Buscar usando o nome correto da coluna
$stmt = $pdo->prepare("
    SELECT *
    FROM webhook_raw_logs
    WHERE created_at >= '2026-02-26 04:00:00'
      AND created_at <= '2026-02-26 04:05:00'
    ORDER BY created_at ASC
    LIMIT 20
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de webhooks: " . count($logs) . "\n\n";

foreach ($logs as $idx => $log) {
    echo "Webhook #{$idx} - ID: {$log['id']}\n";
    echo "Created: {$log['created_at']}\n";
    
    // Identificar qual coluna tem o payload
    foreach ($log as $key => $value) {
        if (is_string($value) && strlen($value) > 100 && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
            echo "Coluna com JSON: {$key}\n";
            
            $payload = json_decode($value, true);
            if ($payload) {
                $from = $payload['message']['from'] ?? $payload['from'] ?? 'N/A';
                echo "From: {$from}\n";
                
                if (strpos($from, '65803193995481') !== false) {
                    echo "✓ KELLY COSTA ENCONTRADA!\n";
                    echo "\nPayload completo:\n";
                    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
            }
        }
    }
    
    echo "\n" . str_repeat("-", 80) . "\n\n";
}
