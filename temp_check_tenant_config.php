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

echo "=== IDENTIFICANDO TENANT CORRETO ===\n\n";

// 1. Ver qual tenant tem configuração Meta
echo "1. TENANTS COM CONFIGURAÇÃO META:\n";
$stmt = $pdo->query("
    SELECT wpc.id, wpc.tenant_id, wpc.meta_phone_number_id, 
           wpc.meta_business_account_id, wpc.is_active,
           t.name as tenant_name
    FROM whatsapp_provider_configs wpc
    LEFT JOIN tenants t ON t.id = wpc.tenant_id
    WHERE wpc.provider_type = 'meta_official'
    ORDER BY wpc.is_active DESC, wpc.id
");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($configs as $cfg) {
    $status = $cfg['is_active'] ? '✓ ATIVO' : '✗ INATIVO';
    echo "   Config [{$cfg['id']}] {$status}\n";
    echo "      Tenant ID: {$cfg['tenant_id']}\n";
    echo "      Tenant Name: {$cfg['tenant_name']}\n";
    echo "      Phone Number ID: {$cfg['meta_phone_number_id']}\n";
    echo "      Business Account: {$cfg['meta_business_account_id']}\n";
    echo "\n";
}

// 2. Ver últimas conversas ativas para identificar qual tenant está sendo usado
echo "\n2. ÚLTIMAS CONVERSAS ATIVAS (para identificar tenant em uso):\n";
$stmt = $pdo->query("
    SELECT c.id, c.conversation_key, c.contact_external_id,
           c.last_message_at, c.status,
           ce.tenant_id,
           t.name as tenant_name
    FROM conversations c
    LEFT JOIN communication_events ce ON ce.conversation_id = c.id
    LEFT JOIN tenants t ON t.id = ce.tenant_id
    WHERE c.status = 'active'
      AND c.last_message_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY c.id
    ORDER BY c.last_message_at DESC
    LIMIT 10
");
$convs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($convs as $conv) {
    echo "   Conv [{$conv['id']}] | Tenant: {$conv['tenant_name']} (ID: {$conv['tenant_id']})\n";
    echo "      Contact: {$conv['contact_external_id']}\n";
    echo "      Última msg: {$conv['last_message_at']}\n";
    echo "\n";
}

// 3. Ver qual tenant tem mais eventos recentes
echo "\n3. TENANTS COM MAIS EVENTOS RECENTES (últimas 24h):\n";
$stmt = $pdo->query("
    SELECT ce.tenant_id, t.name as tenant_name, COUNT(*) as total_events
    FROM communication_events ce
    LEFT JOIN tenants t ON t.id = ce.tenant_id
    WHERE ce.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY ce.tenant_id
    ORDER BY total_events DESC
");
$tenantStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($tenantStats as $stat) {
    echo "   Tenant [{$stat['tenant_id']}] {$stat['tenant_name']}: {$stat['total_events']} eventos\n";
}

// 4. Listar todos os tenants ativos
echo "\n\n4. TODOS OS TENANTS ATIVOS:\n";
$stmt = $pdo->query("
    SELECT id, name, is_active
    FROM tenants
    WHERE is_active = 1
    ORDER BY id
");
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($tenants as $t) {
    echo "   [{$t['id']}] {$t['name']}\n";
}

echo "\n=== FIM ===\n";
