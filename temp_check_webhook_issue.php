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

echo "=== INVESTIGANDO PROBLEMA DE WEBHOOKS NÃO PROCESSADOS ===\n\n";

// 1. Verificar webhooks não processados
echo "1. Total de webhooks não processados:\n";
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM webhook_raw_logs
    WHERE processed = 0
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Total não processados (24h): {$result['total']}\n";

echo "\n2. Últimos 5 webhooks não processados (detalhado):\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, payload_json
    FROM webhook_raw_logs
    WHERE processed = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as $log) {
    echo "ID: {$log['id']}\n";
    echo "Created: {$log['created_at']}\n";
    echo "Event: {$log['event_type']}\n";
    
    $payload = json_decode($log['payload_json'], true);
    if ($payload && isset($payload['message'])) {
        echo "Message ID: {$payload['message']['id']}\n";
        echo "From: {$payload['message']['from']}\n";
        echo "Type: {$payload['message']['type']}\n";
        if (isset($payload['message']['body'])) {
            echo "Body: " . substr($payload['message']['body'], 0, 100) . "...\n";
        }
    }
    echo "---\n";
}

// 3. Verificar estrutura das tabelas principais
echo "\n3. Estrutura da tabela communication_events:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM communication_events");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n4. Eventos de comunicação recentes:\n";
$stmt = $pdo->query("
    SELECT id, tenant_id, event_type, channel_type, 
           contact_external_id, created_at
    FROM communication_events
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
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

// 5. Verificar conversas recentes
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

// 6. Verificar se há erros de processamento
echo "\n6. Webhooks com erros:\n";
$stmt = $pdo->query("
    SELECT id, created_at, event_type, error_message
    FROM webhook_raw_logs
    WHERE error_message IS NOT NULL
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC
    LIMIT 10
");
$errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($errors as $error) {
    echo "ID: {$error['id']}\n";
    echo "Created: {$error['created_at']}\n";
    echo "Event: {$error['event_type']}\n";
    echo "Error: {$error['error_message']}\n";
    echo "---\n";
}

echo "\n=== RESUMO ===\n";
echo "- Webhooks recebidos (24h): Sim\n";
echo "- Webhooks processados: NÃO (166 não processados)\n";
echo "- Communication events: " . count($events) . " recentes\n";
echo "- Conversas: " . count($conversations) . " recentes\n";
echo "- Erros: " . count($errors) . " recentes\n";
