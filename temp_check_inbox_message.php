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

// Buscar conversas com este número
$phone = '5535888101165';
echo "=== BUSCANDO CONVERSAS COM $phone ===\n\n";

$stmt = $pdo->prepare("
    SELECT id, conversation_key, contact_external_id, channel_account_id, 
           last_message_at, created_at
    FROM conversations 
    WHERE contact_external_id LIKE ?
    ORDER BY last_message_at DESC 
    LIMIT 5
");
$stmt->execute(["%$phone%"]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Conversas encontradas: " . count($conversations) . "\n\n";
foreach ($conversations as $conv) {
    echo "ID: {$conv['id']}\n";
    echo "Conversation Key: {$conv['conversation_key']}\n";
    echo "Contact External ID: {$conv['contact_external_id']}\n";
    echo "Channel Account ID: {$conv['channel_account_id']}\n";
    echo "Last Message: {$conv['last_message_at']}\n";
    echo "Created: {$conv['created_at']}\n";
    echo "---\n\n";
}

// Buscar eventos recentes deste número
echo "\n=== EVENTOS RECENTES (últimas 24h) ===\n\n";

$stmt = $pdo->prepare("
    SELECT id, event_type, event_id, created_at, status, tenant_id,
           JSON_EXTRACT(payload, '$.body') as message_body,
           JSON_EXTRACT(payload, '$.from') as from_number,
           JSON_EXTRACT(payload, '$.to') as to_number,
           JSON_EXTRACT(payload, '$.isGroupMsg') as is_group,
           source_system
    FROM communication_events 
    WHERE (JSON_EXTRACT(payload, '$.from') LIKE ? 
           OR JSON_EXTRACT(payload, '$.to') LIKE ?)
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute(["%$phone%", "%$phone%"]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Eventos encontrados: " . count($events) . "\n\n";
foreach ($events as $event) {
    echo "ID: {$event['id']}\n";
    echo "Event ID: {$event['event_id']}\n";
    echo "Type: {$event['event_type']}\n";
    echo "Source: {$event['source_system']}\n";
    echo "Status: {$event['status']}\n";
    echo "From: {$event['from_number']}\n";
    echo "To: {$event['to_number']}\n";
    echo "Is Group: {$event['is_group']}\n";
    echo "Message: {$event['message_body']}\n";
    echo "Tenant ID: {$event['tenant_id']}\n";
    echo "Created: {$event['created_at']}\n";
    echo "---\n\n";
}

// Buscar webhook logs recentes
echo "\n=== WEBHOOK LOGS RECENTES (últimas 2h) ===\n\n";

$stmt = $pdo->prepare("
    SELECT id, event_type, received_at, processed, error_message,
           JSON_EXTRACT(payload_json, '$.from') as from_number,
           JSON_EXTRACT(payload_json, '$.body') as message_body,
           JSON_EXTRACT(payload_json, '$.isGroupMsg') as is_group
    FROM webhook_raw_logs 
    WHERE JSON_EXTRACT(payload_json, '$.from') LIKE ?
    AND received_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
    ORDER BY received_at DESC 
    LIMIT 10
");
$stmt->execute(["%$phone%"]);
$webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Webhooks encontrados: " . count($webhooks) . "\n\n";
foreach ($webhooks as $wh) {
    echo "ID: {$wh['id']}\n";
    echo "Event Type: {$wh['event_type']}\n";
    echo "From: {$wh['from_number']}\n";
    echo "Is Group: {$wh['is_group']}\n";
    echo "Message: {$wh['message_body']}\n";
    echo "Processed: {$wh['processed']}\n";
    echo "Error: {$wh['error_message']}\n";
    echo "Received: {$wh['received_at']}\n";
    echo "---\n\n";
}

// Buscar com variações do número (com/sem 9º dígito)
echo "\n=== BUSCANDO VARIAÇÕES DO NÚMERO ===\n\n";

$variations = [
    '5535888101165',   // Original
    '553588101165',    // Sem 9º dígito
    '+5535888101165',  // Com +
    '+553588101165',   // Com + sem 9º
];

foreach ($variations as $var) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM webhook_raw_logs 
        WHERE JSON_EXTRACT(payload_json, '$.from') LIKE ?
        AND received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute(["%$var%"]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Variação '$var': {$result['count']} webhooks\n";
}
