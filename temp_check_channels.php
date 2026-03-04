<?php
$host = 'r225us.hmservers.net';
$dbname = 'pixel12digital_pixelhub';
$user = 'pixel12digital_pixelhub';
$pass = 'Los@ngo#081081';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

echo "=== VERIFICANDO CONFIGURAÇÃO DOS CANAIS ===\n\n";

// 1. Estrutura da tabela tenant_message_channels
echo "1. ESTRUTURA tenant_message_channels:\n";
$stmt = $pdo->query("DESCRIBE tenant_message_channels");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "   - {$col['Field']} ({$col['Type']})\n";
}

// 2. Canais WhatsApp ativos
echo "\n2. CANAIS WHATSAPP:\n";
$stmt = $pdo->query("
    SELECT c.*, t.name as tenant_name
    FROM tenant_message_channels c
    LEFT JOIN tenants t ON t.id = c.tenant_id
    WHERE c.provider = 'whatsapp'
    ORDER BY c.is_enabled DESC, c.id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($channels as $ch) {
    $status = $ch['is_enabled'] ? '✓ ATIVO' : '✗ INATIVO';
    echo "   [{$ch['id']}] {$status} | Tenant: {$ch['tenant_name']} (ID: {$ch['tenant_id']})\n";
    echo "      Channel ID: {$ch['channel_id']}\n";
    echo "      Provider: {$ch['provider']} ({$ch['provider_type']})\n";
    echo "      Webhook Configured: " . ($ch['webhook_configured'] ? 'SIM' : 'NÃO') . "\n";
    
    // Mostrar metadata se existir
    if (!empty($ch['metadata'])) {
        $metadata = json_decode($ch['metadata'], true);
        if ($metadata) {
            echo "      Metadata: " . print_r($metadata, true) . "\n";
        }
    }
    echo "\n";
}

// 3. Verificar qual sessão do gateway está configurada
echo "\n3. SESSÕES DO GATEWAY CONFIGURADAS:\n";
$stmt = $pdo->query("
    SELECT DISTINCT channel_id
    FROM tenant_message_channels
    WHERE provider = 'whatsapp'
");
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($sessions as $s) {
    echo "   - {$s['channel_id']}\n";
}

echo "\n=== FIM ===\n";
