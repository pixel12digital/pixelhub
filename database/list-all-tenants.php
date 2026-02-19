<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== LISTA DE TODOS OS TENANTS ===\n\n";

$db = DB::getConnection();

$stmt = $db->prepare("
    SELECT 
        id,
        name,
        slug,
        is_enabled,
        created_at
    FROM tenants
    ORDER BY id
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenants as $tenant) {
    echo sprintf(
        "ID: %d | Name: %s | Slug: %s | Enabled: %s | Created: %s\n",
        $tenant['id'],
        $tenant['name'],
        $tenant['slug'],
        $tenant['is_enabled'] ? 'YES' : 'NO',
        $tenant['created_at']
    );
}

echo "\n=== CANAIS DE MENSAGEM POR TENANT ===\n\n";

foreach ($tenants as $tenant) {
    echo "TENANT: {$tenant['name']} (ID: {$tenant['id']})\n";
    
    $stmt = $db->prepare("
        SELECT 
            id,
            channel_id,
            provider,
            is_enabled,
            created_at
        FROM tenant_message_channels
        WHERE tenant_id = ?
        ORDER BY id
    ");
    $stmt->execute([$tenant['id']]);
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($channels)) {
        echo "  Nenhum canal configurado\n";
    } else {
        foreach ($channels as $channel) {
            echo sprintf(
                "  - Channel ID: %s | Provider: %s | Enabled: %s\n",
                $channel['channel_id'],
                $channel['provider'],
                $channel['is_enabled'] ? 'YES' : 'NO'
            );
        }
    }
    echo "\n";
}

echo "=== FIM ===\n";
