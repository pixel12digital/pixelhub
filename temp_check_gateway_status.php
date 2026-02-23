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

echo "=== VERIFICANDO CANAIS WHATSAPP ===\n\n";

$stmt = $pdo->query("
    SELECT id, tenant_id, provider, channel_id, 
           is_enabled, webhook_configured, metadata, updated_at
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    ORDER BY tenant_id, id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Canais encontrados: " . count($channels) . "\n\n";
foreach ($channels as $ch) {
    $metadata = json_decode($ch['metadata'], true);
    echo "ID: {$ch['id']}\n";
    echo "Tenant: {$ch['tenant_id']}\n";
    echo "Provider: {$ch['provider']}\n";
    echo "Channel ID: {$ch['channel_id']}\n";
    echo "Enabled: {$ch['is_enabled']}\n";
    echo "Webhook Configured: {$ch['webhook_configured']}\n";
    echo "Metadata: " . json_encode($metadata, JSON_PRETTY_PRINT) . "\n";
    echo "Updated: {$ch['updated_at']}\n";
    echo "---\n\n";
}

// Verificar últimos webhooks recebidos (qualquer número)
echo "\n=== ÚLTIMOS WEBHOOKS RECEBIDOS (qualquer número) ===\n\n";

$stmt = $pdo->query("
    SELECT id, event_type, received_at, processed,
           JSON_EXTRACT(payload_json, '$.from') as from_number,
           JSON_EXTRACT(payload_json, '$.body') as message_body
    FROM webhook_raw_logs
    ORDER BY received_at DESC
    LIMIT 10
");
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Últimos webhooks: " . count($recent) . "\n\n";
foreach ($recent as $wh) {
    echo "ID: {$wh['id']}\n";
    echo "Event: {$wh['event_type']}\n";
    echo "From: {$wh['from_number']}\n";
    echo "Body: " . substr($wh['message_body'], 0, 50) . "...\n";
    echo "Received: {$wh['received_at']}\n";
    echo "Processed: {$wh['processed']}\n";
    echo "---\n\n";
}

// Verificar se há webhooks recentes (última hora)
$stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM webhook_raw_logs
    WHERE received_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$count = $stmt->fetch(PDO::FETCH_ASSOC);

echo "\n=== ATIVIDADE RECENTE ===\n";
echo "Webhooks na última hora: {$count['count']}\n";

if ($count['count'] == 0) {
    echo "\n⚠️ ALERTA: Nenhum webhook recebido na última hora!\n";
    echo "Possível problema: Gateway desconectado ou sessão WhatsApp offline\n";
}
