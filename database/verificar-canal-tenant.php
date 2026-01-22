<?php
/**
 * Verifica canais cadastrados para tenant_id = 2
 */

require_once __DIR__ . '/../public/index.php';

use PixelHub\Core\DB;

$db = DB::getConnection();

echo "=== Verificação de Canais para tenant_id = 2 ===\n\n";

$stmt = $db->query("
    SELECT id, tenant_id, provider, channel_id, is_enabled
    FROM tenant_message_channels
    WHERE tenant_id = 2
");

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($results)) {
    echo "❌ Nenhum canal encontrado para tenant_id = 2\n";
} else {
    echo "✅ Canais encontrados:\n\n";
    foreach ($results as $row) {
        echo "ID: {$row['id']}\n";
        echo "Tenant ID: {$row['tenant_id']}\n";
        echo "Provider: {$row['provider']}\n";
        echo "Channel ID: '{$row['channel_id']}' (len=" . strlen($row['channel_id']) . ")\n";
        echo "Is Enabled: " . ($row['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "---\n";
    }
}

echo "\n=== Verificação de todos os canais wpp_gateway ===\n\n";

$stmt2 = $db->query("
    SELECT id, tenant_id, provider, channel_id, is_enabled
    FROM tenant_message_channels
    WHERE provider = 'wpp_gateway'
    ORDER BY tenant_id, channel_id
");

$allChannels = $stmt2->fetchAll(PDO::FETCH_ASSOC);

if (empty($allChannels)) {
    echo "❌ Nenhum canal wpp_gateway encontrado\n";
} else {
    echo "✅ Todos os canais wpp_gateway:\n\n";
    foreach ($allChannels as $row) {
        echo "Tenant ID: {$row['tenant_id']} | Channel ID: '{$row['channel_id']}' | Enabled: " . ($row['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
    }
}


