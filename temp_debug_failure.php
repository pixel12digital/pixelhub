<?php
// Debug da falha no envio

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

use PixelHub\Core\DB;

echo "=== Debug da falha ===\n\n";

$db = DB::getConnection();

// Verificar status atual
$stmt = $db->prepare('SELECT id, status, sent_at, failed_reason FROM scheduled_messages WHERE id = 2');
$stmt->execute();
$msg = $stmt->fetch();

echo "Scheduled Message ID 2:\n";
echo "Status: {$msg['status']}\n";
echo "Sent At: " . ($msg['sent_at'] ?? 'NULL') . "\n";
echo "Failed Reason: " . ($msg['failed_reason'] ?? 'NULL') . "\n\n";

// Verificar agenda_manual_items
$stmt2 = $db->prepare('SELECT id, status, completed_at FROM agenda_manual_items WHERE id = 2');
$stmt2->execute();
$task = $stmt2->fetch();

echo "Agenda Item ID 2:\n";
echo "Status: {$task['status']}\n";
echo "Completed At: " . ($task['completed_at'] ?? 'NULL') . "\n\n";

// Verificar logs recentes
echo "=== Logs recentes ===\n";
$stmt3 = $db->prepare('SELECT created_at, event_type, JSON_EXTRACT(event_data, "$.error") as error FROM communication_events WHERE source_system = "scheduled_messages_worker" ORDER BY created_at DESC LIMIT 5');
$stmt3->execute();
$logs = $stmt3->fetchAll();

foreach ($logs as $log) {
    echo "Data: {$log['created_at']} | Tipo: {$log['event_type']} | Erro: " . ($log['error'] ?? 'NULL') . "\n";
}

// Verificar se há erro no gateway
echo "\n=== Verificando gateway ===\n";
try {
    require_once __DIR__ . '/src/Integrations/WhatsAppGateway/WhatsAppGatewayClient.php';
    require_once __DIR__ . '/src/Services/GatewaySecret.php';
    
    $client = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient();
    
    // Teste de listagem de canais
    $result = $client->listChannels();
    echo "Gateway Status: " . ($result['success'] ? 'OK' : 'ERRO') . "\n";
    
    if (!$result['success']) {
        echo "Gateway Error: " . ($result['error'] ?? 'Unknown') . "\n";
    }
    
} catch (Exception $e) {
    echo "Gateway Exception: " . $e->getMessage() . "\n";
}
?>
