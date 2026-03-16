<?php

// Carrega autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

use PixelHub\Core\Env;
use PDO;
use PDOException;

try {
    // Carrega variáveis do .env
    Env::load();
    
    // Obtém configurações
    $host = Env::get('DB_HOST', 'localhost');
    $port = Env::get('DB_PORT', '3306');
    $database = Env::get('DB_NAME', 'pixel_hub');
    $username = Env::get('DB_USER', 'root');
    $password = Env::get('DB_PASS', '');
    $charset = Env::get('DB_CHARSET', 'utf8mb4');
    
    // Monta DSN
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $database,
        $charset
    );
    
    // Tenta conectar
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch (PDOException $e) {
    echo "Erro de conexão: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== VERIFICANDO WEBHOOKS RECENTES ===\n\n";

// 1. Verificar estrutura da tabela webhook_raw_logs
echo "1. Estrutura da tabela webhook_raw_logs:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM webhook_raw_logs");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n2. Últimos 10 webhooks recebidos:\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, processed, 
           SUBSTRING(payload_json, 1, 150) as payload_preview
    FROM webhook_raw_logs
    ORDER BY created_at DESC
    LIMIT 10
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as $log) {
    echo "ID: {$log['id']}\n";
    echo "Created: {$log['created_at']}\n";
    echo "Event: {$log['event_type']}\n";
    echo "Processed: " . ($log['processed'] ? 'Yes' : 'No') . "\n";
    echo "Payload: {$log['payload_preview']}...\n";
    echo "---\n";
}

echo "\n3. Webhooks não processados (últimas 24h):\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM webhook_raw_logs
    WHERE processed = 0
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total não processados: {$result['total']}\n";

echo "\n4. Communication events recentes (inbound):\n";
$stmt = $pdo->query("
    SELECT id, tenant_id, event_type, direction, channel_type, 
           contact_external_id, created_at
    FROM communication_events
    WHERE direction = 'inbound'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    echo "ID: {$event['id']}\n";
    echo "Tenant: {$event['tenant_id']}\n";
    echo "Event: {$event['event_type']}\n";
    echo "Channel: {$event['channel_type']}\n";
    echo "Contact: {$event['contact_external_id']}\n";
    echo "Created: {$event['created_at']}\n";
    echo "---\n";
}

echo "\n5. Conversas recentes:\n";
$stmt = $pdo->query("
    SELECT id, tenant_id, conversation_key, contact_external_id, 
           contact_name, last_message_at, status
    FROM conversations
    WHERE last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY last_message_at DESC
    LIMIT 10
");
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "ID: {$conv['id']}\n";
    echo "Tenant: {$conv['tenant_id']}\n";
    echo "Contact: {$conv['contact_name']} ({$conv['contact_external_id']})\n";
    echo "Last Message: {$conv['last_message_at']}\n";
    echo "Status: {$conv['status']}\n";
    echo "---\n";
}
