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

// Buscar conversas por telefone (E.164 format)
$phone = '+555599235045';
$sql = "SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name, c.tenant_id, c.status, c.created_at, c.updated_at, 
               t.name as tenant_name
        FROM conversations c 
        JOIN tenants t ON c.tenant_id = t.id 
        WHERE c.contact_external_id LIKE ? OR c.conversation_key LIKE ?
        ORDER BY c.updated_at DESC LIMIT 10";

$stmt = $pdo->prepare($sql);
$stmt->execute([$phone, '%'.$phone.'%']);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== CONVERSAS ENCONTRADAS ===\n";
if (empty($conversations)) {
    echo "Nenhuma conversa encontrada para o telefone $phone\n\n";
    
    // Tentar buscar em communication_events
    echo "=== BUSCANDO EM COMMUNICATION_EVENTS ===\n";
    $sql2 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at,
                    t.name as tenant_name,
                    ce.payload as message_payload
             FROM communication_events ce 
             JOIN tenants t ON ce.tenant_id = t.id 
             WHERE ce.payload LIKE ?
             ORDER BY ce.created_at DESC LIMIT 10";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute(['%'.$phone.'%']);
    $events = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "Nenhum evento encontrado para o telefone $phone\n";
    } else {
        foreach ($events as $event) {
            echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_name']} | Data: {$event['created_at']}\n";
            echo "Payload: " . substr($event['message_payload'] ?: 'N/A', 0, 200) . "\n\n";
        }
    }
} else {
    foreach ($conversations as $conv) {
        echo "ID: {$conv['id']} | Key: {$conv['conversation_key']} | Contact: {$conv['contact_external_id']} | Nome: {$conv['contact_name']} | Tenant: {$conv['tenant_name']} | Status: {$conv['status']} | Última atividade: {$conv['updated_at']}\n\n";
    }
}
