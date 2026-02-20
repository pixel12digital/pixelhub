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

echo "=== VERIFICANDO WEBHOOK RAW LOGS COM ERROS ===\n";
$sql = "SELECT wrl.id, wrl.event_type, wrl.created_at, wrl.error_message,
               LEFT(wrl.payload_json, 300) as payload_preview
        FROM webhook_raw_logs wrl 
        WHERE wrl.error_message IS NOT NULL 
           AND wrl.error_message != ''
        ORDER BY wrl.created_at DESC LIMIT 10";

$stmt = $pdo->query($sql);
$errorLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($errorLogs)) {
    echo "Nenhum erro encontrado nos webhooks\n";
} else {
    foreach ($errorLogs as $log) {
        echo "ID: {$log['id']} | Tipo: {$log['event_type']} | Data: {$log['created_at']}\n";
        echo "Erro: {$log['error_message']}\n";
        echo "Payload: " . substr($log['payload_preview'], 0, 200) . "...\n\n";
    }
}

echo "\n=== VERIFICANDO COMMUNICATION_EVENTS COM ERROS ===\n";
$sql2 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at, ce.status, ce.error_message,
                LEFT(ce.payload, 200) as payload_preview
         FROM communication_events ce 
         WHERE ce.error_message IS NOT NULL 
           AND ce.error_message != ''
         ORDER BY ce.created_at DESC LIMIT 10";

$stmt2 = $pdo->query($sql2);
$errorEvents = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($errorEvents)) {
    echo "Nenhum erro encontrado nos eventos de comunicação\n";
} else {
    foreach ($errorEvents as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_id']} | Status: {$event['status']} | Data: {$event['created_at']}\n";
        echo "Erro: {$event['error_message']}\n";
        echo "Payload: " . substr($event['payload_preview'], 0, 200) . "...\n\n";
    }
}

echo "\n=== VERIFICANDO EVENTOS PRESOS EM 'queued' ===\n";
$sql3 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at, ce.status,
                LEFT(ce.payload, 200) as payload_preview
         FROM communication_events ce 
         WHERE ce.status = 'queued'
         ORDER BY ce.created_at DESC LIMIT 10";

$stmt3 = $pdo->query($sql3);
$queuedEvents = $stmt3->fetchAll(PDO::FETCH_ASSOC);

foreach ($queuedEvents as $event) {
    echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_id']} | Data: {$event['created_at']}\n";
    echo "Payload: " . substr($event['payload_preview'], 0, 200) . "...\n\n";
}

echo "\n=== VERIFICANDO SE EXISTE ALGUM CRON/WORKER RODANDO ===\n";
// Verificar se há algum processo relacionado ao processamento de webhooks
echo "Verificando se há arquivos de worker...\n";
$workerFiles = [
    'scripts/webhook_worker.php',
    'scripts/process_webhooks.php', 
    'src/Services/WebhookProcessorService.php',
    'src/Services/EventProcessingService.php'
];

foreach ($workerFiles as $file) {
    if (file_exists(ROOT_PATH . $file)) {
        echo "✅ Encontrado: $file\n";
    } else {
        echo "❌ Não encontrado: $file\n";
    }
}
