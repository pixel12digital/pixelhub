<?php
require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
use PixelHub\Core\DB;
use PixelHub\Core\Env;
Env::load(__DIR__ . '/../.env');

$db = DB::getConnection();
$stmt = $db->query("SELECT payload FROM communication_events WHERE id = 81474");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$payload = json_decode($row['payload'], true);

echo "=== PAYLOAD COMPLETO DO EVENTO 81474 ===\n\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "\n\n";

echo "=== ANÁLISE DO fromMe ===\n";
echo "payload['fromMe']: " . (isset($payload['fromMe']) ? ($payload['fromMe'] ? 'true' : 'false') : 'NÃO EXISTE') . "\n";
echo "payload['message']['fromMe']: " . (isset($payload['message']['fromMe']) ? ($payload['message']['fromMe'] ? 'true' : 'false') : 'NÃO EXISTE') . "\n";
echo "payload['message']['key']['fromMe']: " . (isset($payload['message']['key']['fromMe']) ? ($payload['message']['key']['fromMe'] ? 'true' : 'false') : 'NÃO EXISTE') . "\n";
echo "payload['data']['fromMe']: " . (isset($payload['data']['fromMe']) ? ($payload['data']['fromMe'] ? 'true' : 'false') : 'NÃO EXISTE') . "\n";
echo "payload['raw']['payload']['fromMe']: " . (isset($payload['raw']['payload']['fromMe']) ? ($payload['raw']['payload']['fromMe'] ? 'true' : 'false') : 'NÃO EXISTE') . "\n";
echo "payload['raw']['payload']['key']['fromMe']: " . (isset($payload['raw']['payload']['key']['fromMe']) ? ($payload['raw']['payload']['key']['fromMe'] ? 'true' : 'false') : 'NÃO EXISTE') . "\n";

echo "\n=== CAMPOS RELEVANTES ===\n";
echo "from: " . ($payload['message']['from'] ?? 'N/A') . "\n";
echo "to: " . ($payload['message']['to'] ?? 'N/A') . "\n";
echo "raw.payload.event: " . ($payload['raw']['payload']['event'] ?? 'N/A') . "\n";
