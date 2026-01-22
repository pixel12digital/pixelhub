<?php
/**
 * Script de diagnóstico para investigar por que conversas não estão exibindo mensagens
 * 
 * Uso: php database/diagnose-conversation-messages.php [thread_id]
 * Exemplo: php database/diagnose-conversation-messages.php whatsapp_123
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

// Pega thread_id do argumento ou lista todas as conversas não vinculadas
$threadId = $argv[1] ?? null;

if (!$threadId) {
    echo "=== DIAGNÓSTICO: Conversas sem mensagens ===\n\n";
    echo "Buscando conversas não vinculadas...\n\n";
    
    $stmt = $db->query("
        SELECT 
            c.id as conversation_id,
            c.contact_external_id,
            c.tenant_id,
            c.channel_id,
            c.status,
            c.last_message_at,
            c.created_at,
            CONCAT('whatsapp_', c.id) as thread_id
        FROM conversations c
        WHERE c.tenant_id IS NULL
        ORDER BY c.last_message_at DESC
        LIMIT 10
    ");
    
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversations)) {
        echo "Nenhuma conversa não vinculada encontrada.\n";
        exit(0);
    }
    
    echo "Conversas encontradas:\n";
    foreach ($conversations as $conv) {
        echo "\n";
        echo "Thread ID: {$conv['thread_id']}\n";
        echo "Conversation ID: {$conv['conversation_id']}\n";
        echo "Contact: {$conv['contact_external_id']}\n";
        echo "Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "Last Message: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
    }
    
    echo "\n\nPara diagnosticar uma conversa específica, execute:\n";
    echo "php database/diagnose-conversation-messages.php whatsapp_{$conversations[0]['conversation_id']}\n";
    exit(0);
}

echo "=== DIAGNÓSTICO: Conversa e Mensagens ===\n\n";
echo "Thread ID: {$threadId}\n\n";

// Extrai conversation_id do thread_id
$conversationId = null;
if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
    $conversationId = (int) $matches[1];
} else {
    die("Thread ID inválido. Deve ser no formato 'whatsapp_123'\n");
}

// 1. Busca informações da conversa
echo "1. INFORMAÇÕES DA CONVERSA:\n";
echo str_repeat("-", 60) . "\n";

$stmt = $db->prepare("
    SELECT 
        c.*,
        t.name as tenant_name,
        t.phone as tenant_phone
    FROM conversations c
    LEFT JOIN tenants t ON c.tenant_id = t.id
    WHERE c.id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversation) {
    die("❌ CONVERSA NÃO ENCONTRADA no banco de dados!\n");
}

echo "✅ Conversa encontrada:\n";
echo "   ID: {$conversation['id']}\n";
echo "   Contact External ID: {$conversation['contact_external_id']}\n";
echo "   Tenant ID: " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
echo "   Tenant Name: " . ($conversation['tenant_name'] ?? 'NULL') . "\n";
echo "   Channel ID: " . ($conversation['channel_id'] ?? 'NULL') . "\n";
echo "   Status: {$conversation['status']}\n";
echo "   Last Message At: " . ($conversation['last_message_at'] ?? 'NULL') . "\n";
echo "   Created At: {$conversation['created_at']}\n";
echo "   Remote Key: " . ($conversation['remote_key'] ?? 'NULL') . "\n";
echo "\n";

// 2. Busca eventos na tabela communication_events
echo "2. EVENTOS NA TABELA communication_events:\n";
echo str_repeat("-", 60) . "\n";

$contactExternalId = $conversation['contact_external_id'];
$tenantId = $conversation['tenant_id'];
$channelId = $conversation['channel_id'];

// Normaliza telefone (remove @c.us, etc)
$normalizedContact = preg_replace('/@.*$/', '', $contactExternalId);
$normalizedContact = preg_replace('/[^0-9]/', '', $normalizedContact);

echo "   Buscando eventos com:\n";
echo "   - contact_external_id: {$contactExternalId}\n";
echo "   - normalized: {$normalizedContact}\n";
echo "   - tenant_id: " . ($tenantId ?? 'NULL') . "\n";
echo "   - channel_id: " . ($channelId ?? 'NULL') . "\n\n";

// Busca eventos sem filtros restritivos primeiro
$stmt = $db->prepare("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.from') as event_from,
        JSON_EXTRACT(ce.payload, '$.to') as event_to,
        JSON_EXTRACT(ce.metadata, '$.channel_id') as event_channel_id,
        ce.tenant_id as event_tenant_id,
        LEFT(JSON_EXTRACT(ce.payload, '$.message.body'), 50) as message_preview
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    AND (
        JSON_EXTRACT(ce.payload, '$.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.to') LIKE ?
        OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?
    )
    ORDER BY ce.created_at DESC
    LIMIT 20
");
$searchPattern = "%{$normalizedContact}%";
$stmt->execute([$searchPattern, $searchPattern, $searchPattern, $searchPattern]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "   ❌ NENHUM EVENTO ENCONTRADO com esse contato!\n\n";
    
    // Busca eventos relacionados ao channel_id
    if ($channelId) {
        echo "   Tentando buscar por channel_id...\n";
        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                ce.event_type,
                ce.created_at,
                JSON_EXTRACT(ce.payload, '$.from') as event_from,
                JSON_EXTRACT(ce.payload, '$.to') as event_to,
                JSON_EXTRACT(ce.metadata, '$.channel_id') as event_channel_id,
                ce.tenant_id as event_tenant_id
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            AND (
                JSON_EXTRACT(ce.metadata, '$.channel_id') = ?
                OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
                OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
            )
            ORDER BY ce.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$channelId, $channelId, $channelId]);
        $channelEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($channelEvents)) {
            echo "   ❌ NENHUM EVENTO ENCONTRADO com esse channel_id!\n";
        } else {
            echo "   ✅ Encontrados " . count($channelEvents) . " evento(s) com channel_id:\n";
            foreach ($channelEvents as $event) {
                echo "      - Event ID: {$event['event_id']}\n";
                echo "        Type: {$event['event_type']}\n";
                echo "        From: {$event['event_from']}\n";
                echo "        To: {$event['event_to']}\n";
                echo "        Created: {$event['created_at']}\n\n";
            }
        }
    }
} else {
    echo "   ✅ Encontrados " . count($events) . " evento(s):\n";
    foreach ($events as $event) {
        echo "      - Event ID: {$event['event_id']}\n";
        echo "        Type: {$event['event_type']}\n";
        echo "        From: {$event['event_from']}\n";
        echo "        To: {$event['event_to']}\n";
        echo "        Event Channel ID: {$event['event_channel_id']}\n";
        echo "        Event Tenant ID: " . ($event['event_tenant_id'] ?? 'NULL') . "\n";
        echo "        Message: {$event['message_preview']}\n";
        echo "        Created: {$event['created_at']}\n\n";
        
        // Verifica se o tenant_id ou channel_id batem
        if ($tenantId && $event['event_tenant_id'] != $tenantId) {
            echo "        ⚠️  ATENÇÃO: tenant_id do evento ({$event['event_tenant_id']}) difere da conversa ({$tenantId})\n";
        }
        if ($channelId && $event['event_channel_id'] != $channelId) {
            echo "        ⚠️  ATENÇÃO: channel_id do evento ({$event['event_channel_id']}) difere da conversa ({$channelId})\n";
        }
        echo "\n";
    }
}

// 3. Verifica se há eventos recentes gerais
echo "3. ÚLTIMOS EVENTOS GERAIS (para referência):\n";
echo str_repeat("-", 60) . "\n";

$stmt = $db->query("
    SELECT 
        ce.event_id,
        ce.event_type,
        ce.created_at,
        JSON_EXTRACT(ce.payload, '$.from') as event_from,
        ce.tenant_id
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at DESC
    LIMIT 5
");
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($recentEvents)) {
    echo "   ⚠️  NENHUM EVENTO encontrado na tabela (tabela vazia?)\n";
} else {
    echo "   Últimos 5 eventos no sistema:\n";
    foreach ($recentEvents as $event) {
        echo "      - Event ID: {$event['event_id']}\n";
        echo "        Type: {$event['event_type']}\n";
        echo "        From: {$event['event_from']}\n";
        echo "        Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "        Created: {$event['created_at']}\n\n";
    }
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";

