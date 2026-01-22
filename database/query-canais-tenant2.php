<?php
require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== Canais para tenant_id = 2 ===\n\n";

$stmt = $db->query("
    SELECT id, tenant_id, provider, channel_id, is_enabled
    FROM tenant_message_channels
    WHERE tenant_id = 2
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "❌ Nenhum canal encontrado para tenant_id = 2\n\n";
} else {
    foreach ($results as $row) {
        echo "ID: {$row['id']}\n";
        echo "Tenant ID: {$row['tenant_id']}\n";
        echo "Provider: {$row['provider']}\n";
        echo "Channel ID: '{$row['channel_id']}'\n";
        echo "Is Enabled: " . ($row['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "---\n";
    }
}

echo "\n=== Todos os canais wpp_gateway ===\n\n";

$stmt2 = $db->query("
    SELECT id, tenant_id, provider, channel_id, is_enabled
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    ORDER BY tenant_id, channel_id
");

$all = $stmt2->fetchAll(PDO::FETCH_ASSOC);

foreach ($all as $row) {
    $tenantId = $row['tenant_id'] ?: 'NULL';
    echo "Tenant: {$tenantId} | Channel: '{$row['channel_id']}' | Enabled: " . ($row['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
}


