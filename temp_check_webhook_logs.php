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

echo "=== WEBHOOK_RAW_LOGS RECENTES (ÚLTIMOS 20) ===\n";
$sql = "SELECT wrl.id, wrl.method, wrl.url, wrl.status_code, wrl.created_at,
               LEFT(wrl.response_body, 200) as response_preview,
               LEFT(wrl.request_body, 200) as request_preview
        FROM webhook_raw_logs wrl 
        ORDER BY wrl.created_at DESC LIMIT 20";

$stmt = $pdo->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "Nenhum log de webhook encontrado\n";
} else {
    foreach ($logs as $log) {
        echo "ID: {$log['id']} | Method: {$log['method']} | Status: {$log['status_code']} | Data: {$log['created_at']}\n";
        echo "URL: {$log['url']}\n";
        echo "Request: " . substr($log['request_preview'], 0, 100) . "...\n";
        echo "Response: " . substr($log['response_preview'], 0, 100) . "...\n\n";
    }
}

echo "\n=== COMMUNICATION_EVENTS RECENTES (ÚLTIMOS 10) ===\n";
$sql2 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at, ce.status, ce.error_message,
                LEFT(ce.payload, 200) as payload_preview
         FROM communication_events ce 
         ORDER BY ce.created_at DESC LIMIT 10";

$stmt2 = $pdo->query($sql2);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Nenhum evento de comunicação encontrado\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_id']} | Status: {$event['status']} | Data: {$event['created_at']}\n";
        if ($event['error_message']) {
            echo "Erro: {$event['error_message']}\n";
        }
        echo "Payload: " . substr($event['payload_preview'], 0, 100) . "...\n\n";
    }
}

echo "\n=== VERIFICANDO SE HÁ ERROS 500 RECENTES ===\n";
$sql3 = "SELECT COUNT(*) as count, MAX(created_at) as last_error
         FROM webhook_raw_logs 
         WHERE status_code = 500 
         AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)";

$stmt3 = $pdo->query($sql3);
$error500 = $stmt3->fetch(PDO::FETCH_ASSOC);

echo "Erros 500 na última hora: {$error500['count']}\n";
if ($error500['last_error']) {
    echo "Último erro 500: {$error500['last_error']}\n";
}

echo "\n=== VERIFICANDO CONVERSAS CRIADAS RECENTEMENTE ===\n";
$sql4 = "SELECT c.id, c.contact_external_id, c.contact_name, c.tenant_id, c.created_at, c.updated_at,
               t.name as tenant_name
        FROM conversations c 
        JOIN tenants t ON c.tenant_id = t.id 
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOURS)
        ORDER BY c.created_at DESC LIMIT 10";

$stmt4 = $pdo->query($sql4);
$conversations = $stmt4->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "Nenhuma conversa criada nas últimas 2 horas\n";
} else {
    foreach ($conversations as $conv) {
        echo "ID: {$conv['id']} | Contact: {$conv['contact_external_id']} | Nome: {$conv['contact_name']} | Tenant: {$conv['tenant_name']} | Criada: {$conv['created_at']}\n";
    }
}
