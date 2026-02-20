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

echo "=== TODOS OS CONTATOS ÚNICOS EM CONVERSATIONS ===\n";

// Buscar todos os contact_external_id únicos
$sql = "SELECT DISTINCT c.contact_external_id, COUNT(*) as count
        FROM conversations c 
        WHERE c.contact_external_id IS NOT NULL AND c.contact_external_id != ''
        GROUP BY c.contact_external_id 
        ORDER BY count DESC, c.contact_external_id ASC
        LIMIT 50";

$stmt = $pdo->query($sql);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contacts as $contact) {
    echo "Contact: {$contact['contact_external_id']} ({$contact['count']} conversas)\n";
    
    // Verificar se algum contém "5045" ou "9923"
    if (strpos($contact['contact_external_id'], '5045') !== false || strpos($contact['contact_external_id'], '9923') !== false) {
        echo "  *** POSSÍVEL MATCH ***\n";
        
        // Buscar detalhes desta conversa
        $sql2 = "SELECT c.id, c.conversation_key, c.contact_name, c.tenant_id, c.status, c.created_at, c.updated_at, 
                        t.name as tenant_name
                 FROM conversations c 
                 JOIN tenants t ON c.tenant_id = t.id 
                 WHERE c.contact_external_id = ?
                 ORDER BY c.updated_at DESC";

        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$contact['contact_external_id']]);
        $convs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($convs as $conv) {
            echo "    ID: {$conv['id']} | Nome: {$conv['contact_name']} | Tenant: {$conv['tenant_name']} | Status: {$conv['status']} | Última: {$conv['updated_at']}\n";
        }
    }
}

echo "\n=== BUSCANDO EM PAYLOADS COM PADRÕES 5045/9923 ===\n";

// Buscar nos payloads
$sql3 = "SELECT ce.id, ce.event_type, ce.tenant_id, ce.created_at,
                t.name as tenant_name,
                ce.payload
         FROM communication_events ce 
         JOIN tenants t ON ce.tenant_id = t.id 
         WHERE ce.payload LIKE '%5045%' OR ce.payload LIKE '%9923%'
         ORDER BY ce.created_at DESC LIMIT 30";

$stmt3 = $pdo->query($sql3);
$events = $stmt3->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "Nenhum evento encontrado com padrões 5045 ou 9923\n";
} else {
    foreach ($events as $event) {
        echo "ID: {$event['id']} | Tipo: {$event['event_type']} | Tenant: {$event['tenant_name']} | Data: {$event['created_at']}\n";
        
        // Extrair informações do payload JSON
        $payload = json_decode($event['payload'], true);
        if ($payload && isset($payload['message'])) {
            $from = $payload['message']['from'] ?? 'N/A';
            $to = $payload['message']['to'] ?? 'N/A';
            echo "  From: $from | To: $to\n";
        } elseif ($payload && isset($payload['from'])) {
            $from = $payload['from'] ?? 'N/A';
            $to = $payload['to'] ?? 'N/A';
            echo "  From: $from | To: $to\n";
        }
        echo "\n";
    }
}
