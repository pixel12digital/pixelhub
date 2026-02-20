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

// Várias variações do telefone
$phones = [
    '+555599235045',  // E.164 completo
    '555599235045',   // Sem +
    '5599235045',     // Sem 55
    '99235045',       // Apenas número
    '+55 (55) 9923-5045', // Formatado
    '55559923-5045',  // Com hífen
];

echo "=== BUSCANDO CONVERSAS COM VARIAÇÕES DO TELEFONE ===\n";
foreach ($phones as $phone) {
    echo "\n--- Buscando: $phone ---\n";
    
    $sql = "SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name, c.tenant_id, c.status, c.created_at, c.updated_at, 
                   t.name as tenant_name
            FROM conversations c 
            JOIN tenants t ON c.tenant_id = t.id 
            WHERE c.contact_external_id LIKE ? OR c.conversation_key LIKE ?
            ORDER BY c.updated_at DESC LIMIT 5";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$phone, '%'.$phone.'%']);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($conversations)) {
        foreach ($conversations as $conv) {
            echo "ENCONTRADO: ID: {$conv['id']} | Contact: {$conv['contact_external_id']} | Nome: {$conv['contact_name']} | Tenant: {$conv['tenant_name']} | Status: {$conv['status']}\n";
        }
    }
}

echo "\n=== BUSCANDO EM COMMUNICATION_EVENTS ===\n";
foreach ($phones as $phone) {
    echo "\n--- Events para: $phone ---\n";
    
    $sql2 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at,
                    t.name as tenant_name
             FROM communication_events ce 
             JOIN tenants t ON ce.tenant_id = t.id 
             WHERE ce.payload LIKE ?
             ORDER BY ce.created_at DESC LIMIT 3";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute(['%'.$phone.'%']);
    $events = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($events)) {
        foreach ($events as $event) {
            echo "EVENTO: ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_name']} | Data: {$event['created_at']}\n";
        }
    }
}
