<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PixelHub\Core\DB;

echo "=== INVESTIGAÇÃO DA CONFIGURAÇÃO DO TENANT IMOBSSITES ===\n\n";

$db = DB::getConnection();

// 1. Encontrar tenant ImobSites
echo "1. TENANT IMOBSSITES:\n";
$stmt = $db->prepare("
    SELECT * FROM tenants
    WHERE name LIKE '%ImobSites%' OR name LIKE '%imobsites%' OR name LIKE '%Imobsites%'
");
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tenants as $tenant) {
    echo sprintf(
        "  - ID: %d\n    Name: %s\n    Slug: %s\n    Enabled: %s\n    Created: %s\n\n",
        $tenant['id'],
        $tenant['name'],
        $tenant['slug'],
        $tenant['is_enabled'] ? 'YES' : 'NO',
        $tenant['created_at']
    );
}

// 2. Verificar canais de mensagem do tenant
if (!empty($tenants)) {
    $tenantId = $tenants[0]['id'];
    
    echo "2. CANAIS DE MENSAGEM DO TENANT (ID: $tenantId):\n";
    $stmt = $db->prepare("
        SELECT * FROM tenant_message_channels
        WHERE tenant_id = ?
        ORDER BY id
    ");
    $stmt->execute([$tenantId]);
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($channels as $channel) {
        echo sprintf(
            "  - ID: %d\n    Channel ID: %s\n    Provider: %s\n    Enabled: %s\n    Created: %s\n\n",
            $channel['id'],
            $channel['channel_id'],
            $channel['provider'],
            $channel['is_enabled'] ? 'YES' : 'NO',
            $channel['created_at']
        );
    }
    
    // 3. Verificar se há conversas para este tenant
    echo "3. CONVERSAS DO TENANT (últimas 10):\n";
    $stmt = $db->prepare("
        SELECT 
            id,
            conversation_key,
            channel_type,
            channel_account_id,
            contact_external_id,
            contact_name,
            status,
            created_at,
            updated_at
        FROM conversations
        WHERE tenant_id = ?
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenantId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "  Nenhuma conversa encontrada\n\n";
    } else {
        foreach ($conversations as $conv) {
            echo sprintf(
                "  - ID: %d\n    Key: %s\n    Channel: %s | Account: %s\n    Contact: %s (%s)\n    Status: %s\n    Updated: %s\n\n",
                $conv['id'],
                $conv['conversation_key'],
                $conv['channel_type'],
                $conv['channel_account_id'],
                $conv['contact_name'],
                $conv['contact_external_id'],
                $conv['status'],
                $conv['updated_at']
            );
        }
    }
    
    // 4. Verificar eventos de comunicação do tenant
    echo "4. EVENTOS DE COMUNICAÇÃO DO TENANT (últimos 10):\n";
    $stmt = $db->prepare("
        SELECT 
            id,
            event_type,
            source_system,
            tenant_id,
            created_at
        FROM communication_events
        WHERE tenant_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenantId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "  Nenhum evento de comunicação encontrado\n\n";
    } else {
        foreach ($events as $event) {
            echo sprintf(
                "  - ID: %s\n    Type: %s\n    Source: %s\n    Tenant: %s\n    Created: %s\n\n",
                $event['id'],
                $event['event_type'],
                $event['source_system'],
                $event['tenant_id'],
                $event['created_at']
            );
        }
    }
} else {
    echo "  Tenant ImobSites não encontrado!\n";
}

echo "=== FIM DA INVESTIGAÇÃO ===\n";
