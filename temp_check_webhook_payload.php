<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== ANALISANDO PAYLOAD DOS ÚLTIMOS WEBHOOKS ===\n\n";

$stmt = $pdo->query("
    SELECT id, event_type, received_at, processed, error_message, payload_json
    FROM webhook_raw_logs
    ORDER BY received_at DESC
    LIMIT 5
");
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($webhooks as $wh) {
    echo "=== WEBHOOK ID: {$wh['id']} ===\n";
    echo "Event Type: {$wh['event_type']}\n";
    echo "Received: {$wh['received_at']}\n";
    echo "Processed: {$wh['processed']}\n";
    echo "Error: {$wh['error_message']}\n\n";
    
    $payload = json_decode($wh['payload_json'], true);
    echo "Payload Structure:\n";
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "\n" . str_repeat("=", 80) . "\n\n";
}

// Verificar se há erros no processamento
echo "\n=== WEBHOOKS COM ERRO ===\n\n";

$stmt = $pdo->query("
    SELECT id, event_type, received_at, error_message
    FROM webhook_raw_logs
    WHERE error_message IS NOT NULL
    ORDER BY received_at DESC
    LIMIT 10
");
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Webhooks com erro: " . count($errors) . "\n\n";
foreach ($errors as $err) {
    echo "ID: {$err['id']}\n";
    echo "Event: {$err['event_type']}\n";
    echo "Received: {$err['received_at']}\n";
    echo "Error: {$err['error_message']}\n";
    echo "---\n\n";
}
