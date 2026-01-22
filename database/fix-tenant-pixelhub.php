<?php

/**
 * Script para corrigir a associação incorreta
 * 
 * A sessão "pixel12digital" é do sistema Pixel Hub (central), não de um cliente específico
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

echo "=== CORRIGINDO ASSOCIAÇÃO: pixel12digital é do Pixel Hub ===\n\n";

$db = DB::getConnection();

// 1. Verifica se existe tenant para Pixel Hub
echo "1. Verificando tenant do Pixel Hub...\n";
$stmt = $db->query("
    SELECT id, name FROM tenants 
    WHERE name LIKE '%Pixel%Hub%' 
       OR name LIKE '%Pixel12%Digital%'
       OR name LIKE '%Sistema%'
    ORDER BY id
");
$pixelHubTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($pixelHubTenants) > 0) {
    echo "   Tenants encontrados que podem ser Pixel Hub:\n";
    foreach ($pixelHubTenants as $t) {
        echo "     - ID: " . $t['id'] . ", Name: " . $t['name'] . "\n";
    }
} else {
    echo "   Nenhum tenant específico encontrado para Pixel Hub\n";
}

echo "\n";

// 2. Verifica o evento
echo "2. Verificando evento 15...\n";
$stmt = $db->prepare("
    SELECT id, tenant_id FROM communication_events WHERE id = 15
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "   Event ID: " . $event['id'] . "\n";
    echo "   Tenant ID atual (INCORRETO): " . ($event['tenant_id'] ?? 'NULL') . "\n";
    
    // Remove tenant_id (NULL = sistema central)
    echo "\n3. Corrigindo evento (removendo tenant_id)...\n";
    $updateStmt = $db->prepare("
        UPDATE communication_events 
        SET tenant_id = NULL,
            updated_at = NOW()
        WHERE id = 15
    ");
    $updateStmt->execute();
    echo "   ✅ Evento corrigido (tenant_id = NULL)\n";
} else {
    echo "   ❌ Evento não encontrado!\n";
}

echo "\n";

// 3. Verifica a conversa
echo "4. Verificando conversa criada...\n";
$stmt = $db->prepare("
    SELECT id, tenant_id, channel_account_id, conversation_key 
    FROM conversations 
    WHERE id = 1 OR channel_id = 'pixel12digital'
");
$stmt->execute();
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "   Conversation ID: " . $conversation['id'] . "\n";
    echo "   Tenant ID atual (INCORRETO): " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
    echo "   Channel Account ID: " . ($conversation['channel_account_id'] ?? 'NULL') . "\n";
    echo "   Conversation Key: " . ($conversation['conversation_key'] ?? 'NULL') . "\n";
    
    // Remove tenant_id e channel_account_id (NULL = sistema central)
    echo "\n5. Corrigindo conversa (removendo tenant_id e channel_account_id)...\n";
    
    // Atualiza conversation_key para usar 'shared' ao invés do channel_account_id
    $newKey = str_replace('whatsapp_3_', 'whatsapp_shared_', $conversation['conversation_key']);
    
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET tenant_id = NULL,
            channel_account_id = NULL,
            conversation_key = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$newKey, $conversation['id']]);
    echo "   ✅ Conversa corrigida:\n";
    echo "      - tenant_id = NULL\n";
    echo "      - channel_account_id = NULL\n";
    echo "      - conversation_key = '" . $newKey . "'\n";
} else {
    echo "   ❌ Conversa não encontrada!\n";
}

echo "\n";

// 4. Verifica se existe canal para Pixel Hub
echo "6. Verificando canais no banco...\n";
$stmt = $db->query("
    SELECT id, tenant_id, channel_id, is_enabled 
    FROM tenant_message_channels 
    WHERE provider = 'wpp_gateway'
    ORDER BY id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "   Canais encontrados:\n";
foreach ($channels as $ch) {
    $tenantName = 'NULL';
    if ($ch['tenant_id']) {
        $tStmt = $db->prepare("SELECT name FROM tenants WHERE id = ?");
        $tStmt->execute([$ch['tenant_id']]);
        $tenant = $tStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            $tenantName = $tenant['name'];
        }
    }
    
    echo "     - ID: " . $ch['id'] . ", Channel ID: '" . $ch['channel_id'] . "', Tenant: " . $tenantName . " (ID: " . ($ch['tenant_id'] ?? 'NULL') . ")\n";
}

echo "\n";

// 5. Resumo final
echo "=== RESUMO DA CORREÇÃO ===\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Evento 15: tenant_id removido (NULL = sistema Pixel Hub)\n";
if ($conversation) {
    echo "✅ Conversa " . $conversation['id'] . ": tenant_id e channel_account_id removidos\n";
    echo "   Agora representa conversa do sistema central (Pixel Hub)\n";
}
echo "\n";
echo "NOTA: A sessão 'pixel12digital' é do sistema Pixel Hub (central),\n";
echo "      não pertence a um cliente específico.\n";
echo "\n" . str_repeat("=", 60) . "\n";

