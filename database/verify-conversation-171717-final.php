<?php

/**
 * Script de verificação final - confirma que a conversa "171717" está corretamente associada
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
Env::load();

echo "=== VERIFICAÇÃO FINAL DA CONVERSA 171717 ===\n\n";

$db = DB::getConnection();

// 1. Verifica o evento
echo "1. VERIFICANDO EVENTO:\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        created_at
    FROM communication_events
    WHERE id = 15
       OR payload LIKE '%171717%'
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "✅ Evento encontrado:\n";
    echo "   ID: " . $event['id'] . "\n";
    echo "   Event Type: " . $event['event_type'] . "\n";
    echo "   Source System: " . $event['source_system'] . "\n";
    echo "   Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
    
    if ($event['tenant_id']) {
        $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $tenantStmt->execute([$event['tenant_id']]);
        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            echo "   Tenant: " . $tenant['name'] . "\n";
        }
    } else {
        echo "   ❌ PROBLEMA: Tenant ID está NULL!\n";
    }
} else {
    echo "❌ Evento não encontrado!\n";
}

echo "\n";

// 2. Verifica a conversa
echo "2. VERIFICANDO CONVERSA:\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE conversation_key LIKE '%171717%'
       OR contact_external_id LIKE '%171717%'
       OR channel_id = 'pixel12digital'
       OR id = 1
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($conversations) > 0) {
    echo "✅ Encontradas " . count($conversations) . " conversa(s):\n\n";
    foreach ($conversations as $conv) {
        echo "Conversation ID: " . $conv['id'] . "\n";
        echo "  Conversation Key: " . ($conv['conversation_key'] ?? 'NULL') . "\n";
        echo "  Channel Type: " . ($conv['channel_type'] ?? 'NULL') . "\n";
        echo "  Channel Account ID: " . ($conv['channel_account_id'] ?? 'NULL') . "\n";
        echo "  Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
        echo "  Contact External ID: " . ($conv['contact_external_id'] ?? 'NULL') . "\n";
        echo "  Contact Name: " . ($conv['contact_name'] ?? 'NULL') . "\n";
        echo "  Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "  Status: " . ($conv['status'] ?? 'NULL') . "\n";
        echo "  Last Message At: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
        echo "  Message Count: " . ($conv['message_count'] ?? 0) . "\n";
        echo "  Unread Count: " . ($conv['unread_count'] ?? 0) . "\n";
        
        if ($conv['tenant_id']) {
            $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
            $tenantStmt->execute([$conv['tenant_id']]);
            $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
            if ($tenant) {
                echo "  Tenant: " . $tenant['name'] . "\n";
            }
        }
        
        // Verifica se contém "171717" ou "teste"
        $checkText = strtolower($conv['conversation_key'] . ' ' . ($conv['contact_external_id'] ?? '') . ' ' . ($conv['contact_name'] ?? ''));
        if (strpos($checkText, '171717') !== false || strpos($checkText, 'teste') !== false) {
            echo "  ⚠️  Contém '171717' ou 'teste' nos campos!\n";
        }
        
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
} else {
    echo "❌ Nenhuma conversa encontrada!\n";
}

echo "\n";

// 3. Verifica associação evento-conversa
echo "3. VERIFICANDO ASSOCIAÇÃO EVENTO-CONVERSA:\n";
echo str_repeat("=", 60) . "\n";
if ($event && count($conversations) > 0) {
    $conv = $conversations[0];
    
    echo "Comparação:\n";
    echo "  Event Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
    echo "  Conversation Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
    
    if ($event['tenant_id'] && $conv['tenant_id'] && $event['tenant_id'] == $conv['tenant_id']) {
        echo "  ✅ Tenant IDs coincidem!\n";
    } else {
        echo "  ❌ PROBLEMA: Tenant IDs não coincidem!\n";
    }
    
    // Verifica channel_id
    $payload = json_decode($event['payload'] ?? '{}', true);
    $eventChannelId = $payload['session']['id'] 
        ?? $payload['session']['session'] 
        ?? null;
    
    echo "\n  Event Channel ID (do payload): " . ($eventChannelId ?? 'NULL') . "\n";
    echo "  Conversation Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
    
    $eventChannelNormalized = strtolower(str_replace(' ', '', $eventChannelId ?? ''));
    $convChannelNormalized = strtolower(str_replace(' ', '', $conv['channel_id'] ?? ''));
    
    if ($eventChannelNormalized && $convChannelNormalized && $eventChannelNormalized === $convChannelNormalized) {
        echo "  ✅ Channel IDs coincidem (normalizados)!\n";
    } else {
        echo "  ⚠️  Channel IDs diferentes (mas podem ser válidos se normalizados corretamente)\n";
    }
}

echo "\n";

// 4. Lista sessões conectadas para referência
echo "4. SESSÕES CONECTADAS (REFERÊNCIA):\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        tmc.id,
        tmc.tenant_id,
        t.name as tenant_name,
        tmc.channel_id,
        tmc.is_enabled
    FROM tenant_message_channels tmc
    LEFT JOIN tenants t ON tmc.tenant_id = t.id
    WHERE tmc.provider = 'wpp_gateway'
      AND tmc.is_enabled = 1
    ORDER BY tmc.tenant_id
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($channels) > 0) {
    foreach ($channels as $ch) {
        echo "Channel Account ID: " . $ch['id'] . "\n";
        echo "  Tenant: " . ($ch['tenant_name'] ?? 'NULL') . " (ID: " . ($ch['tenant_id'] ?? 'NULL') . ")\n";
        echo "  Channel ID: " . ($ch['channel_id'] ?? 'NULL') . "\n";
        echo "  Habilitado: " . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "=== VERIFICAÇÃO CONCLUÍDA ===\n";

