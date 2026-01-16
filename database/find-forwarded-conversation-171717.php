<?php

/**
 * Script para localizar a conversa encaminhada "171717" do WhatsApp teste
 * 
 * Busca em:
 * 1. Tabela conversations (ID da conversa)
 * 2. Tabela communication_events (eventos de mensagens)
 * 3. Payloads contendo "171717" ou "teste"
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

// Carrega .env
Env::load();

echo "=== BUSCANDO CONVERSA ENCAMINHADA 171717 ===\n\n";

$db = DB::getConnection();

// 1. Buscar na tabela conversations pelo ID 171717
echo "1. BUSCANDO CONVERSA POR ID 171717:\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE id = 171717
");
$stmt->execute();
$conversation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($conversation) {
    echo "✅ Conversa encontrada!\n\n";
    echo "ID: " . $conversation['id'] . "\n";
    echo "Conversation Key: " . ($conversation['conversation_key'] ?? 'NULL') . "\n";
    echo "Channel Type: " . ($conversation['channel_type'] ?? 'NULL') . "\n";
    echo "Channel Account ID: " . ($conversation['channel_account_id'] ?? 'NULL') . "\n";
    echo "Channel ID: " . ($conversation['channel_id'] ?? 'NULL') . "\n";
    echo "Contact External ID: " . ($conversation['contact_external_id'] ?? 'NULL') . "\n";
    echo "Contact Name: " . ($conversation['contact_name'] ?? 'NULL') . "\n";
    echo "Tenant ID: " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
    echo "Status: " . ($conversation['status'] ?? 'NULL') . "\n";
    echo "Last Message At: " . ($conversation['last_message_at'] ?? 'NULL') . "\n";
    echo "Message Count: " . ($conversation['message_count'] ?? 0) . "\n";
    echo "Unread Count: " . ($conversation['unread_count'] ?? 0) . "\n";
    echo "Created At: " . ($conversation['created_at'] ?? 'NULL') . "\n";
    
    // Busca nome do tenant se houver
    if ($conversation['tenant_id']) {
        $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $tenantStmt->execute([$conversation['tenant_id']]);
        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            echo "Tenant: " . $tenant['name'] . " (ID: " . $tenant['id'] . ")\n";
        }
    }
    
    // Busca informações do canal se houver
    if ($conversation['channel_account_id']) {
        $channelStmt = $db->prepare("
            SELECT id, tenant_id, provider, channel_id, is_enabled 
            FROM tenant_message_channels 
            WHERE id = ?
        ");
        $channelStmt->execute([$conversation['channel_account_id']]);
        $channel = $channelStmt->fetch(PDO::FETCH_ASSOC);
        if ($channel) {
            echo "\nCanal:\n";
            echo "  - Provider: " . ($channel['provider'] ?? 'NULL') . "\n";
            echo "  - Channel ID: " . ($channel['channel_id'] ?? 'NULL') . "\n";
            echo "  - Habilitado: " . ($channel['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        }
    }
} else {
    echo "❌ Conversa com ID 171717 não encontrada.\n";
}

echo "\n\n";

// 2. Buscar em communication_events por "171717" no payload
echo "2. BUSCANDO EM COMMUNICATION_EVENTS (payload contém '171717'):\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$') as payload_raw
    FROM communication_events
    WHERE payload LIKE '%171717%'
       OR payload LIKE '%teste%'
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Encontrados " . count($events) . " eventos:\n\n";
    foreach ($events as $event) {
        echo "Event ID: " . $event['id'] . "\n";
        echo "Event UUID: " . ($event['event_id'] ?? 'NULL') . "\n";
        echo "Event Type: " . ($event['event_type'] ?? 'NULL') . "\n";
        echo "Source System: " . ($event['source_system'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "Created At: " . ($event['created_at'] ?? 'NULL') . "\n";
        
        // Tenta extrair informações relevantes do payload
        $payload = json_decode($event['payload_raw'], true);
        if ($payload) {
            // Extrai informações de mensagem encaminhada
            $forwardedFrom = $payload['message']['forwardedFrom'] 
                ?? $payload['forwardedFrom'] 
                ?? $payload['data']['forwardedFrom'] 
                ?? null;
            
            $from = $payload['from'] 
                ?? $payload['message']['from'] 
                ?? $payload['data']['from'] 
                ?? null;
            
            $channelId = $payload['channel'] 
                ?? $payload['channelId'] 
                ?? $payload['session']['id'] 
                ?? $payload['session']['session']
                ?? $payload['metadata']['channel_id'] 
                ?? null;
            
            if ($forwardedFrom) {
                echo "  🔄 ENCAMINHADA DE: " . $forwardedFrom . "\n";
            }
            if ($from) {
                echo "  📨 DE: " . $from . "\n";
            }
            if ($channelId) {
                echo "  📱 Channel ID: " . $channelId . "\n";
            }
            
            // Procura por "171717" no payload
            $payloadStr = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (stripos($payloadStr, '171717') !== false) {
                echo "  ⚠️  CONTÉM '171717' no payload!\n";
                // Tenta encontrar o contexto
                $pos = stripos($payloadStr, '171717');
                $context = substr($payloadStr, max(0, $pos - 100), 200);
                echo "  Contexto: ..." . $context . "...\n";
            }
        }
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
} else {
    echo "❌ Nenhum evento encontrado contendo '171717' ou 'teste'.\n";
}

echo "\n\n";

// 3. Buscar conversas relacionadas a "teste" ou que contenham "171717" no contact_external_id
echo "3. BUSCANDO CONVERSAS RELACIONADAS A 'TESTE' OU COM '171717':\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE contact_name LIKE '%teste%'
       OR contact_external_id LIKE '%171717%'
       OR conversation_key LIKE '%171717%'
       OR conversation_key LIKE '%teste%'
    ORDER BY last_message_at DESC
    LIMIT 20
");
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($conversations) > 0) {
    echo "✅ Encontradas " . count($conversations) . " conversas:\n\n";
    foreach ($conversations as $conv) {
        echo "ID: " . $conv['id'] . "\n";
        echo "Conversation Key: " . ($conv['conversation_key'] ?? 'NULL') . "\n";
        echo "Contact Name: " . ($conv['contact_name'] ?? 'NULL') . "\n";
        echo "Contact External ID: " . ($conv['contact_external_id'] ?? 'NULL') . "\n";
        echo "Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
        echo "Last Message At: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
} else {
    echo "❌ Nenhuma conversa encontrada relacionada a 'teste' ou '171717'.\n";
}

echo "\n\n";

// 4. Buscar eventos de mensagem encaminhada (forwardedFrom)
echo "4. BUSCANDO EVENTOS DE MENSAGENS ENCAMINHADAS:\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$.message.forwardedFrom') as forwarded_from,
        JSON_EXTRACT(payload, '$.forwardedFrom') as forwarded_from_alt,
        JSON_EXTRACT(payload, '$.from') as from_field,
        JSON_EXTRACT(payload, '$.message.from') as message_from
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
      AND (
          JSON_EXTRACT(payload, '$.message.forwardedFrom') IS NOT NULL
          OR JSON_EXTRACT(payload, '$.forwardedFrom') IS NOT NULL
          OR payload LIKE '%forwardedFrom%'
      )
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$forwardedEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($forwardedEvents) > 0) {
    echo "✅ Encontrados " . count($forwardedEvents) . " eventos de mensagens encaminhadas:\n\n";
    foreach ($forwardedEvents as $event) {
        echo "Event ID: " . $event['id'] . "\n";
        echo "Event Type: " . ($event['event_type'] ?? 'NULL') . "\n";
        echo "Source System: " . ($event['source_system'] ?? 'NULL') . "\n";
        echo "Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "Created At: " . ($event['created_at'] ?? 'NULL') . "\n";
        echo "Forwarded From: " . ($event['forwarded_from'] ?? $event['forwarded_from_alt'] ?? 'NULL') . "\n";
        echo "From: " . ($event['from_field'] ?? $event['message_from'] ?? 'NULL') . "\n";
        
        // Verifica se contém "171717"
        if (stripos($event['forwarded_from'] ?? '', '171717') !== false || 
            stripos($event['forwarded_from_alt'] ?? '', '171717') !== false) {
            echo "  ⚠️  CONTÉM '171717'!\n";
        }
        
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
} else {
    echo "❌ Nenhum evento de mensagem encaminhada encontrado.\n";
}

echo "\n\n";

// 5. Listar sessões/canais conectados
echo "5. SESSÕES/CANAIS CONECTADOS:\n";
echo str_repeat("=", 60) . "\n";
$stmt = $db->prepare("
    SELECT 
        tmc.id,
        tmc.tenant_id,
        t.name as tenant_name,
        tmc.provider,
        tmc.channel_id,
        tmc.is_enabled,
        tmc.created_at
    FROM tenant_message_channels tmc
    LEFT JOIN tenants t ON tmc.tenant_id = t.id
    WHERE tmc.provider = 'wpp_gateway'
      AND tmc.is_enabled = 1
    ORDER BY tmc.tenant_id, tmc.created_at DESC
");
$stmt->execute();
$channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($channels) > 0) {
    echo "✅ Encontradas " . count($channels) . " sessões conectadas:\n\n";
    foreach ($channels as $channel) {
        echo "Channel Account ID: " . $channel['id'] . "\n";
        echo "Tenant: " . ($channel['tenant_name'] ?? 'NULL') . " (ID: " . ($channel['tenant_id'] ?? 'NULL') . ")\n";
        echo "Channel ID (Session): " . ($channel['channel_id'] ?? 'NULL') . "\n";
        echo "Provider: " . ($channel['provider'] ?? 'NULL') . "\n";
        echo "Habilitado: " . ($channel['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
        echo "Criado em: " . ($channel['created_at'] ?? 'NULL') . "\n";
        echo "\n" . str_repeat("-", 60) . "\n\n";
    }
} else {
    echo "❌ Nenhuma sessão conectada encontrada.\n";
}

echo "\n=== FIM DA BUSCA ===\n";

