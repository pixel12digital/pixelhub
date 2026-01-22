<?php

/**
 * Script para corrigir a associação da conversa "171717"
 * 
 * 1. Atualiza o tenant_id do evento
 * 2. Cria/atualiza a conversa correspondente
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\ConversationService;

// Carrega .env
Env::load();

echo "=== CORRIGINDO CONVERSA ENCAMINHADA 171717 ===\n\n";

$db = DB::getConnection();

// 1. Busca o evento que contém "171717"
echo "1. Buscando evento que contém '171717'...\n";
$stmt = $db->prepare("
    SELECT * FROM communication_events
    WHERE id = 15
       OR (payload LIKE '%171717%' AND event_type = 'whatsapp.inbound.message')
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "❌ Evento não encontrado!\n";
    exit(1);
}

echo "✅ Evento encontrado (ID: " . $event['id'] . ")\n\n";

// 2. Decodifica payload e metadata
$payload = json_decode($event['payload'], true);
$metadata = !empty($event['metadata']) ? json_decode($event['metadata'], true) : [];

// Extrai channel_id do payload
$channelId = $payload['session']['id'] 
    ?? $payload['session']['session'] 
    ?? $payload['channel'] 
    ?? $payload['channelId']
    ?? $metadata['channel_id'] 
    ?? null;

echo "2. Channel ID extraído: " . ($channelId ?? 'NULL') . "\n";

// 3. Resolve tenant_id pelo channel_id
echo "3. Resolvendo tenant_id pelo channel_id...\n";
$tenantId = null;

if ($channelId) {
    // Tenta buscar canal exato (case-insensitive)
    $stmt = $db->prepare("
        SELECT tenant_id 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway' 
        AND (channel_id = ? OR LOWER(channel_id) = LOWER(?))
        AND is_enabled = 1
        LIMIT 1
    ");
    $stmt->execute([$channelId, $channelId]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($channel) {
        $tenantId = (int) $channel['tenant_id'];
        echo "✅ Tenant ID encontrado: " . $tenantId . "\n";
        
        // Busca nome do tenant
        $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $tenantStmt->execute([$tenantId]);
        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            echo "   Tenant: " . $tenant['name'] . "\n";
        }
    } else {
        echo "⚠️  Canal não encontrado no banco. Tentando buscar por similaridade...\n";
        
        // Busca por similaridade (sem case-sensitive)
        $stmt = $db->prepare("
            SELECT id, tenant_id, channel_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND is_enabled = 1
        ");
        $stmt->execute();
        $allChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($allChannels as $ch) {
            $normalizedDb = strtolower(str_replace(' ', '', $ch['channel_id']));
            $normalizedEvent = strtolower(str_replace(' ', '', $channelId));
            
            if ($normalizedDb === $normalizedEvent) {
                $tenantId = (int) $ch['tenant_id'];
                echo "✅ Tenant ID encontrado por similaridade: " . $tenantId . " (Channel: " . $ch['channel_id'] . ")\n";
                
                // Busca nome do tenant
                $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
                $tenantStmt->execute([$tenantId]);
                $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
                if ($tenant) {
                    echo "   Tenant: " . $tenant['name'] . "\n";
                }
                break;
            }
        }
        
        if (!$tenantId) {
            echo "❌ Não foi possível resolver tenant_id automaticamente.\n";
            echo "   Canais disponíveis:\n";
            foreach ($allChannels as $ch) {
                echo "     - ID: " . $ch['id'] . ", Channel ID: " . $ch['channel_id'] . ", Tenant ID: " . $ch['tenant_id'] . "\n";
            }
            exit(1);
        }
    }
}

if (!$tenantId) {
    echo "❌ Não foi possível resolver tenant_id!\n";
    exit(1);
}

// 4. Atualiza o evento com o tenant_id correto
echo "\n4. Atualizando evento com tenant_id = " . $tenantId . "...\n";
try {
    $db->beginTransaction();
    
    // Atualiza tenant_id no evento
    $updateStmt = $db->prepare("
        UPDATE communication_events 
        SET tenant_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$tenantId, $event['id']]);
    
    // Atualiza metadata para incluir channel_id se não tiver
    if (!isset($metadata['channel_id']) && $channelId) {
        $metadata['channel_id'] = $channelId;
        $updateMetadataStmt = $db->prepare("
            UPDATE communication_events 
            SET metadata = ?
            WHERE id = ?
        ");
        $updateMetadataStmt->execute([json_encode($metadata), $event['id']]);
    }
    
    $db->commit();
    echo "✅ Evento atualizado com sucesso!\n";
} catch (\Exception $e) {
    $db->rollBack();
    echo "❌ Erro ao atualizar evento: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Prepara dados do evento para o ConversationService
echo "\n5. Preparando dados para criar/atualizar conversa...\n";
$eventData = [
    'event_type' => $event['event_type'],
    'source_system' => $event['source_system'],
    'tenant_id' => $tenantId,
    'payload' => $payload,
    'metadata' => $metadata ?: []
];

// 6. Resolve/cria a conversa usando o ConversationService
echo "6. Criando/atualizando conversa...\n";
try {
    $conversation = ConversationService::resolveConversation($eventData);
    
    if ($conversation) {
        echo "✅ Conversa criada/atualizada com sucesso!\n";
        echo "\nDetalhes da conversa:\n";
        echo "  ID: " . $conversation['id'] . "\n";
        echo "  Conversation Key: " . ($conversation['conversation_key'] ?? 'NULL') . "\n";
        echo "  Channel Type: " . ($conversation['channel_type'] ?? 'NULL') . "\n";
        echo "  Contact External ID: " . ($conversation['contact_external_id'] ?? 'NULL') . "\n";
        echo "  Contact Name: " . ($conversation['contact_name'] ?? 'NULL') . "\n";
        echo "  Tenant ID: " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
        echo "  Channel ID: " . ($conversation['channel_id'] ?? 'NULL') . "\n";
        echo "  Status: " . ($conversation['status'] ?? 'NULL') . "\n";
        echo "  Last Message At: " . ($conversation['last_message_at'] ?? 'NULL') . "\n";
        echo "  Message Count: " . ($conversation['message_count'] ?? 0) . "\n";
        echo "  Unread Count: " . ($conversation['unread_count'] ?? 0) . "\n";
    } else {
        echo "⚠️  ConversationService retornou null (pode ser normal se não for evento de mensagem válido)\n";
        
        // Tenta buscar conversa manualmente
        $from = $payload['from'] 
            ?? $payload['message']['from'] 
            ?? $payload['data']['from'] 
            ?? null;
        
        if ($from) {
            $contactId = preg_replace('/@.*$/', '', $from);
            echo "\nTentando buscar conversa manualmente por contact: " . $contactId . "\n";
            
            $convStmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE channel_type = 'whatsapp'
                  AND (contact_external_id LIKE ? OR contact_external_id = ?)
                  AND tenant_id = ?
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            $searchTerm = '%' . $contactId . '%';
            $convStmt->execute([$searchTerm, $from, $tenantId]);
            $existingConv = $convStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingConv) {
                echo "✅ Conversa existente encontrada (ID: " . $existingConv['id'] . ")\n";
            } else {
                echo "❌ Nenhuma conversa encontrada. Pode ser necessário processar o evento novamente.\n";
            }
        }
    }
} catch (\Exception $e) {
    echo "❌ Erro ao criar/atualizar conversa: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

// 7. Verifica resultado final
echo "\n7. Verificando resultado final...\n";
$verifyStmt = $db->prepare("
    SELECT 
        ce.id as event_id,
        ce.tenant_id as event_tenant_id,
        ce.event_type,
        c.id as conversation_id,
        c.conversation_key,
        c.contact_external_id,
        c.contact_name,
        c.tenant_id as conversation_tenant_id,
        c.channel_id,
        c.last_message_at
    FROM communication_events ce
    LEFT JOIN conversations c ON (
        c.channel_type = 'whatsapp'
        AND c.tenant_id = ce.tenant_id
        AND JSON_EXTRACT(ce.payload, '$.message.from') = c.contact_external_id
    )
    WHERE ce.id = ?
");
$verifyStmt->execute([$event['id']]);
$result = $verifyStmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "\n✅ RESUMO FINAL:\n";
    echo str_repeat("=", 60) . "\n";
    echo "Event ID: " . $result['event_id'] . "\n";
    echo "Event Tenant ID: " . ($result['event_tenant_id'] ?? 'NULL') . "\n";
    echo "Event Type: " . $result['event_type'] . "\n";
    
    if ($result['conversation_id']) {
        echo "\n✅ Conversa associada:\n";
        echo "  Conversation ID: " . $result['conversation_id'] . "\n";
        echo "  Conversation Key: " . ($result['conversation_key'] ?? 'NULL') . "\n";
        echo "  Contact: " . ($result['contact_external_id'] ?? 'NULL') . "\n";
        echo "  Contact Name: " . ($result['contact_name'] ?? 'NULL') . "\n";
        echo "  Conversation Tenant ID: " . ($result['conversation_tenant_id'] ?? 'NULL') . "\n";
        echo "  Channel ID: " . ($result['channel_id'] ?? 'NULL') . "\n";
        echo "  Last Message At: " . ($result['last_message_at'] ?? 'NULL') . "\n";
    } else {
        echo "\n⚠️  Conversa não foi criada automaticamente.\n";
        echo "   O evento foi atualizado com tenant_id, mas a conversa precisa ser processada.\n";
    }
} else {
    echo "❌ Erro ao verificar resultado!\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "=== CORREÇÃO CONCLUÍDA ===\n";

