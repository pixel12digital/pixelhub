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

// Buscar logs de webhook recentes para o tenant pixel12digital
$stmt = $pdo->prepare("
    SELECT 
        wrl.id,
        wrl.tenant_id,
        wrl.source_system,
        wrl.received_at,
        wrl.payload,
        t.slug as tenant_slug
    FROM webhook_raw_logs wrl
    JOIN tenants t ON wrl.tenant_id = t.id
    WHERE t.slug = 'pixel12digital'
    ORDER BY wrl.received_at DESC
    LIMIT 20
");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== WEBHOOK LOGS RECENTES - PIXEL12DIGITAL ===\n\n";

foreach ($logs as $log) {
    echo "ID: {$log['id']}\n";
    echo "Tenant: {$log['tenant_slug']} ({$log['tenant_id']})\n";
    echo "Source: {$log['source_system']}\n";
    echo "Received: {$log['received_at']}\n";
    
    $payload = json_decode($log['payload'], true);
    if ($payload && isset($payload['event'])) {
        echo "Event: {$payload['event']}\n";
        
        if ($payload['event'] === 'messages') {
            echo "Message type: " . (isset($payload['data']['type']) ? $payload['data']['type'] : 'unknown') . "\n";
            if (isset($payload['data']['from'])) {
                echo "From: {$payload['data']['from']}\n";
            }
            if (isset($payload['data']['body'])) {
                echo "Body: " . substr($payload['data']['body'], 0, 50) . "...\n";
            }
        }
    }
    echo "---\n";
}

// Verificar communication_events recentes
echo "\n=== COMMUNICATION EVENTS RECENTES - PIXEL12DIGITAL ===\n\n";

$stmt2 = $pdo->prepare("
    SELECT 
        ce.id,
        ce.tenant_id,
        ce.event_type,
        ce.direction,
        ce.channel_type,
        ce.contact_external_id,
        ce.created_at,
        ce.event_data,
        t.slug as tenant_slug
    FROM communication_events ce
    JOIN tenants t ON ce.tenant_id = t.id
    WHERE t.slug = 'pixel12digital'
    AND ce.direction = 'inbound'
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$stmt2->execute();
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    echo "ID: {$event['id']}\n";
    echo "Event: {$event['event_type']}\n";
    echo "Direction: {$event['direction']}\n";
    echo "Channel: {$event['channel_type']}\n";
    echo "Contact: {$event['contact_external_id']}\n";
    echo "Created: {$event['created_at']}\n";
    
    $event_data = json_decode($event['event_data'], true);
    if ($event_data && isset($event_data['content']['text'])) {
        echo "Text: " . substr($event_data['content']['text'], 0, 50) . "...\n";
    }
    echo "---\n";
}

// Verificar conversations recentes
echo "\n=== CONVERSATIONS RECENTES - PIXEL12DIGITAL ===\n\n";

$stmt3 = $pdo->prepare("
    SELECT 
        c.id,
        c.tenant_id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.last_message_at,
        c.status,
        t.slug as tenant_slug
    FROM conversations c
    JOIN tenants t ON c.tenant_id = t.id
    WHERE t.slug = 'pixel12digital'
    ORDER BY c.last_message_at DESC
    LIMIT 10
");
$stmt3->execute();
$conversations = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "ID: {$conv['id']}\n";
    echo "Contact: {$conv['contact_name']} ({$conv['contact_external_id']})\n";
    echo "Last Message: {$conv['last_message_at']}\n";
    echo "Status: {$conv['status']}\n";
    echo "---\n";
}
