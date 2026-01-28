<?php
/**
 * Investigação de roteamento incorreto entre Luiz (7530) e Renato (2320)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

header('Content-Type: text/plain; charset=utf-8');

$db = DB::getConnection();

echo "=== INVESTIGAÇÃO DE ROTEAMENTO: Luiz (7530) vs Renato (2320) ===\n\n";

// 1. Busca conversas com esses números
echo "1. CONVERSAS ENVOLVIDAS\n";
echo str_repeat("-", 60) . "\n\n";

$stmt = $db->query("
    SELECT 
        id, 
        conversation_key, 
        contact_external_id,
        contact_name,
        remote_key,
        tenant_id,
        channel_id,
        status,
        message_count,
        last_message_at
    FROM conversations 
    WHERE contact_external_id LIKE '%7530%' 
       OR contact_external_id LIKE '%2320%'
       OR contact_name LIKE '%Luiz%'
       OR contact_name LIKE '%Renato%'
    ORDER BY last_message_at DESC
");

$convs = $stmt->fetchAll();
echo "Total conversas encontradas: " . count($convs) . "\n\n";

foreach ($convs as $c) {
    echo "Conversa ID: {$c['id']}\n";
    echo "  Nome: {$c['contact_name']}\n";
    echo "  Número (contact_external_id): {$c['contact_external_id']}\n";
    echo "  remote_key: " . ($c['remote_key'] ?? 'NULL') . "\n";
    echo "  conversation_key: {$c['conversation_key']}\n";
    echo "  tenant_id: " . ($c['tenant_id'] ?? 'NULL') . "\n";
    echo "  channel_id: " . ($c['channel_id'] ?? 'NULL') . "\n";
    echo "  message_count: {$c['message_count']}\n";
    echo "  last_message_at: " . ($c['last_message_at'] ?? 'NULL') . "\n";
    echo "\n";
}

// 2. Busca eventos recentes desses números
echo "\n2. EVENTOS RECENTES (últimos 10 de cada número)\n";
echo str_repeat("-", 60) . "\n\n";

echo "=== Eventos do número 7530 (Luiz) ===\n";
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        conversation_id,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as msg_to,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from2,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) as msg_to2,
        SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.content')), 1, 50) as content_preview
    FROM communication_events 
    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%7530%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%7530%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE '%7530%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) LIKE '%7530%'
    ORDER BY created_at DESC
    LIMIT 10
");

$events7530 = $stmt->fetchAll();
echo "Encontrados: " . count($events7530) . " eventos\n\n";

foreach ($events7530 as $ev) {
    echo "Event: " . substr($ev['event_id'], 0, 8) . "... | {$ev['event_type']}\n";
    echo "  conversation_id: " . ($ev['conversation_id'] ?? 'NULL') . "\n";
    echo "  from: " . ($ev['msg_from'] ?? $ev['msg_from2'] ?? 'NULL') . "\n";
    echo "  to: " . ($ev['msg_to'] ?? $ev['msg_to2'] ?? 'NULL') . "\n";
    echo "  created_at: {$ev['created_at']}\n";
    echo "  content: " . ($ev['content_preview'] ?? '(vazio)') . "\n\n";
}

echo "\n=== Eventos do número 2320 (Renato) ===\n";
$stmt = $db->query("
    SELECT 
        event_id,
        event_type,
        conversation_id,
        created_at,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as msg_to,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from2,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) as msg_to2,
        SUBSTRING(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.content')), 1, 50) as content_preview
    FROM communication_events 
    WHERE JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE '%2320%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE '%2320%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE '%2320%'
       OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) LIKE '%2320%'
    ORDER BY created_at DESC
    LIMIT 10
");

$events2320 = $stmt->fetchAll();
echo "Encontrados: " . count($events2320) . " eventos\n\n";

foreach ($events2320 as $ev) {
    echo "Event: " . substr($ev['event_id'], 0, 8) . "... | {$ev['event_type']}\n";
    echo "  conversation_id: " . ($ev['conversation_id'] ?? 'NULL') . "\n";
    echo "  from: " . ($ev['msg_from'] ?? $ev['msg_from2'] ?? 'NULL') . "\n";
    echo "  to: " . ($ev['msg_to'] ?? $ev['msg_to2'] ?? 'NULL') . "\n";
    echo "  created_at: {$ev['created_at']}\n";
    echo "  content: " . ($ev['content_preview'] ?? '(vazio)') . "\n\n";
}

// 3. Verifica tenant Ponto do Golf
echo "\n3. TENANT 'PONTO DO GOLF' E CONVERSAS VINCULADAS\n";
echo str_repeat("-", 60) . "\n\n";

$stmt = $db->query("
    SELECT id, name FROM tenants WHERE name LIKE '%Ponto%Golf%' OR name LIKE '%Ponto do Golf%'
");
$tenants = $stmt->fetchAll();

foreach ($tenants as $t) {
    echo "Tenant: ID={$t['id']} | Nome: {$t['name']}\n";
    
    // Conversas vinculadas a esse tenant
    $stmt2 = $db->prepare("
        SELECT id, contact_name, contact_external_id, remote_key 
        FROM conversations 
        WHERE tenant_id = ?
    ");
    $stmt2->execute([$t['id']]);
    $tenantConvs = $stmt2->fetchAll();
    
    echo "  Conversas vinculadas: " . count($tenantConvs) . "\n";
    foreach ($tenantConvs as $tc) {
        echo "    - ID={$tc['id']} | {$tc['contact_name']} | {$tc['contact_external_id']}\n";
    }
    echo "\n";
}

// 4. Verifica thread_id=whatsapp_114 especificamente (da URL)
echo "\n4. CONVERSA ID=114 (whatsapp_114 da URL)\n";
echo str_repeat("-", 60) . "\n\n";

$stmt = $db->query("SELECT * FROM conversations WHERE id = 114");
$conv114 = $stmt->fetch();

if ($conv114) {
    echo "Conversa 114:\n";
    echo json_encode($conv114, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} else {
    echo "Conversa 114 não encontrada!\n";
}
