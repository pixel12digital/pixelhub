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

echo "=== PROCURANDO POR +55 55 9923-5045 NOS WEBHOOKS ===\n";
$sql = "SELECT wrl.id, wrl.event_type, wrl.created_at, wrl.payload_json
        FROM webhook_raw_logs wrl 
        WHERE wrl.payload_json LIKE '%555599235045%' 
           OR wrl.payload_json LIKE '%+555599235045%'
           OR wrl.payload_json LIKE '%55 9923%'
        ORDER BY wrl.created_at DESC LIMIT 10";

$stmt = $pdo->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "Nenhum webhook encontrado para o telefone +55 55 9923-5045\n";
} else {
    foreach ($logs as $log) {
        echo "ID: {$log['id']} | Tipo: {$log['event_type']} | Data: {$log['created_at']}\n";
        echo "Payload completo:\n";
        echo $log['payload_json'] . "\n\n";
    }
}

echo "\n=== PROCURANDO POR +55 55 9923-5045 NOS COMMUNICATION_EVENTS ===\n";
$sql2 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at, ce.status, ce.payload
        FROM communication_events ce 
        WHERE ce.payload LIKE '%555599235045%' 
           OR ce.payload LIKE '%+555599235045%'
           OR ce.payload LIKE '%55 9923%'
        ORDER BY ce.created_at DESC LIMIT 10";

$stmt2 = $pdo->query($sql2);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Nenhum evento encontrado para o telefone +55 55 9923-5045\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_id']} | Status: {$event['status']} | Data: {$event['created_at']}\n";
        echo "Payload completo:\n";
        echo $event['payload'] . "\n\n";
    }
}

echo "\n=== VERIFICANDO WEBHOOKS NÃO PROCESSADOS (ÚLTIMOS 5) ===\n";
$sql3 = "SELECT wrl.id, wrl.event_type, wrl.created_at, wrl.error_message,
               LEFT(wrl.payload_json, 500) as payload_preview
        FROM webhook_raw_logs wrl 
        WHERE wrl.processed = 0 
        ORDER BY wrl.created_at DESC LIMIT 5";

$stmt3 = $pdo->query($sql3);
$unprocessed = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($unprocessed as $log) {
    echo "ID: {$log['id']} | Tipo: {$log['event_type']} | Data: {$log['created_at']}\n";
    if ($log['error_message']) {
        echo "Erro: {$log['error_message']}\n";
    }
    echo "Payload: " . $log['payload_preview'] . "...\n\n";
}
