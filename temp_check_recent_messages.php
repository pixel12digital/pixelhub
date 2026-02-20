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

echo "=== CONVERSAS RECENTES (ÚLTIMAS 10) ===\n";
$sql = "SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name, c.tenant_id, c.status, c.created_at, c.updated_at, 
               t.name as tenant_name
        FROM conversations c 
        JOIN tenants t ON c.tenant_id = t.id 
        ORDER BY c.updated_at DESC LIMIT 10";

$stmt = $pdo->query($sql);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($conversations as $conv) {
    echo "ID: {$conv['id']} | Contact: {$conv['contact_external_id']} | Nome: {$conv['contact_name']} | Tenant: {$conv['tenant_name']} | Status: {$conv['status']} | Última: {$conv['updated_at']}\n";
}

echo "\n=== EVENTS RECENTES (ÚLTIMOS 10) ===\n";
$sql2 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at,
                t.name as tenant_name,
                LEFT(ce.payload, 100) as payload_preview
         FROM communication_events ce 
         JOIN tenants t ON ce.tenant_id = t.id 
         ORDER BY ce.created_at DESC LIMIT 10";

$stmt2 = $pdo->query($sql2);
$events = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($events as $event) {
    echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_name']} | Data: {$event['created_at']}\n";
    echo "Payload: {$event['payload_preview']}\n\n";
}
