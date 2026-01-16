<?php

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== VERIFICANDO ASSOCIAÇÃO DE TENANT ===\n\n";

// 1. Evento com 171717
echo "1. EVENTO 15 (mensagem 171717):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        tenant_id,
        JSON_EXTRACT(payload, '$.session.id') as session_id,
        JSON_EXTRACT(payload, '$.session.name') as session_name
    FROM communication_events 
    WHERE id = 15
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "   Event ID: " . $event['id'] . "\n";
    echo "   Tenant ID Atual: " . ($event['tenant_id'] ?? 'NULL') . "\n";
    echo "   Session ID (payload): " . trim($event['session_id'] ?? 'NULL', '"') . "\n";
    echo "   Session Name (payload): " . trim($event['session_name'] ?? 'NULL', '"') . "\n";
} else {
    echo "   Evento não encontrado!\n";
}

echo "\n";

// 2. Todos os canais disponíveis
echo "2. TODOS OS CANAIS NO BANCO (tenant_message_channels):\n";
$stmt = $db->query("
    SELECT 
        tmc.id,
        tmc.tenant_id,
        t.name as tenant_name,
        tmc.channel_id,
        tmc.is_enabled
    FROM tenant_message_channels tmc
    LEFT JOIN tenants t ON tmc.tenant_id = t.id
    WHERE tmc.provider = 'wpp_gateway'
    ORDER BY tmc.tenant_id, tmc.id
");
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($channels as $ch) {
    echo "   Channel Account ID: " . $ch['id'] . "\n";
    echo "      Tenant ID: " . $ch['tenant_id'] . "\n";
    echo "      Tenant Name: " . ($ch['tenant_name'] ?? 'NULL') . "\n";
    echo "      Channel ID: '" . $ch['channel_id'] . "'\n";
    echo "      Enabled: " . ($ch['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "\n";
}

// 3. Verifica qual canal corresponde ao session_id do evento
if ($event && $event['session_id']) {
    $eventSessionId = trim($event['session_id'], '"');
    echo "3. VERIFICANDO MATCH COM SESSION_ID DO EVENTO:\n";
    echo "   Session ID do evento: '" . $eventSessionId . "'\n";
    echo "\n";
    
    $found = false;
    foreach ($channels as $ch) {
        $normalizedEvent = strtolower(str_replace(' ', '', $eventSessionId));
        $normalizedChannel = strtolower(str_replace(' ', '', $ch['channel_id']));
        
        echo "   Comparando:\n";
        echo "      Event: '" . $eventSessionId . "' (normalizado: '" . $normalizedEvent . "')\n";
        echo "      Channel: '" . $ch['channel_id'] . "' (normalizado: '" . $normalizedChannel . "')\n";
        
        if ($normalizedEvent === $normalizedChannel) {
            echo "      ✅ MATCH! Tenant ID: " . $ch['tenant_id'] . " (" . ($ch['tenant_name'] ?? 'NULL') . ")\n";
            $found = true;
        } else {
            echo "      ❌ Não match\n";
        }
        echo "\n";
    }
    
    if (!$found) {
        echo "   ⚠️  NENHUM CANAL CORRESPONDE EXATAMENTE!\n";
    }
}

// 4. Verifica conversa criada
echo "4. CONVERSA CRIADA:\n";
$stmt = $db->prepare("
    SELECT * FROM conversations WHERE id = 1
");
$stmt->execute();
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "   Conversation ID: " . $conversation['id'] . "\n";
    echo "   Tenant ID: " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
    echo "   Channel Account ID: " . ($conversation['channel_account_id'] ?? 'NULL') . "\n";
    echo "   Channel ID: " . ($conversation['channel_id'] ?? 'NULL') . "\n";
    
    if ($conversation['tenant_id']) {
        $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $tenantStmt->execute([$conversation['tenant_id']]);
        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            echo "   Tenant Name: " . $tenant['name'] . "\n";
        }
    }
} else {
    echo "   Conversa não encontrada!\n";
}

echo "\n=== FIM ===\n";

