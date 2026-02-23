<?php
// Carrega o ambiente
define('ROOT_PATH', __DIR__ . '/');
require_once ROOT_PATH . 'src/Core/Env.php';

PixelHub\Core\Env::load();

// Pega configurações do banco
$config = require ROOT_PATH . 'config/database.php';

try {
    $db = new PDO("mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}", $config['username'], $config['password']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== INVESTIGAÇÃO: EVENTO QUE FALHOU NO WORKER ===\n\n";

// Pegar um dos eventos que falhou (evento do Andrei Lima)
$eventId = '1301549f-13dd-48a6-9919-e3d644749099';

$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        tenant_id,
        payload,
        metadata,
        created_at
    FROM communication_events
    WHERE event_id = :event_id
");

$stmt->execute(['event_id' => $eventId]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "Evento não encontrado.\n";
    exit(1);
}

echo "Event ID: {$event['event_id']}\n";
echo "Event Type: {$event['event_type']}\n";
echo "Tenant ID: " . ($event['tenant_id'] ?: 'NULL') . "\n";
echo "Created: {$event['created_at']}\n\n";

echo "--- PAYLOAD (estrutura completa) ---\n";
$payload = json_decode($event['payload'], true);
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "--- METADATA ---\n";
$metadata = json_decode($event['metadata'], true);
echo json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "--- ANÁLISE DE EXTRAÇÃO ---\n";

// Tentar extrair contact_external_id usando diferentes caminhos
$attempts = [
    'from' => $payload['from'] ?? null,
    'data.from' => $payload['data']['from'] ?? null,
    'message.from' => $payload['message']['from'] ?? null,
    'chatId' => $payload['chatId'] ?? null,
    'data.chatId' => $payload['data']['chatId'] ?? null,
];

foreach ($attempts as $path => $value) {
    echo sprintf("  %-20s: %s\n", $path, $value ?: 'NULL');
}

echo "\n--- SOLUÇÃO ---\n";
if ($payload['chatId'] ?? null) {
    echo "✅ Usar 'chatId' como contact_external_id: {$payload['chatId']}\n";
} elseif ($payload['from'] ?? null) {
    echo "✅ Usar 'from' como contact_external_id: {$payload['from']}\n";
} else {
    echo "❌ Nenhum campo válido encontrado para contact_external_id\n";
}
