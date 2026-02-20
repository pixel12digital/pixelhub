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

echo "=== VERIFICANDO PAYLOADS COM NÚMEROS SIMILARES (55 9923) ===\n";

// Buscar eventos que contenham "55 9923" ou "559923"
$sql = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at,
                t.name as tenant_name,
                ce.payload
         FROM communication_events ce 
         JOIN tenants t ON ce.tenant_id = t.id 
         WHERE ce.payload LIKE '%55 9923%' OR ce.payload LIKE '%559923%'
         ORDER BY ce.created_at DESC LIMIT 20";

$stmt = $pdo->query($sql);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Nenhum evento encontrado com padrões 55 9923 ou 559923\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_name']} | Data: {$event['created_at']}\n";
        
        // Extrair informações do payload JSON
        $payload = json_decode($event['payload'], true);
        if ($payload && isset($payload['message'])) {
            $from = $payload['message']['from'] ?? 'N/A';
            $to = $payload['message']['to'] ?? 'N/A';
            $body = substr($payload['message']['body'] ?? 'N/A', 0, 100);
            echo "  From: $from | To: $to\n";
            echo "  Mensagem: $body\n";
        }
        echo "\n";
    }
}

echo "\n=== VERIFICANDO CONVERSAS COM NÚMEROS SIMILARES (55 9923) ===\n";
$sql2 = "SELECT c.id, c.conversation_key, c.contact_external_id, c.contact_name, c.tenant_id, c.status, c.created_at, c.updated_at, 
                t.name as tenant_name
         FROM conversations c 
         JOIN tenants t ON c.tenant_id = t.id 
         WHERE c.contact_external_id LIKE '%55 9923%' OR c.contact_external_id LIKE '%559923%'
         ORDER BY c.updated_at DESC LIMIT 10";

$stmt2 = $pdo->query($sql2);
$conversations = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($conversations)) {
    echo "Nenhuma conversa encontrada com padrões 55 9923 ou 559923\n";
} else {
    foreach ($conversations as $conv) {
        echo "ID: {$conv['id']} | Contact: {$conv['contact_external_id']} | Nome: {$conv['contact_name']} | Tenant: {$conv['tenant_name']} | Status: {$conv['status']} | Última: {$conv['updated_at']}\n";
    }
}
