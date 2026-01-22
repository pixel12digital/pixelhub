<?php

/**
 * Script para forçar a criação da conversa para a mensagem "171717"
 * 
 * Este script cria a conversa manualmente usando os dados do evento atualizado
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/ConversationService.php';
require_once __DIR__ . '/../src/Services/PhoneNormalizer.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Services\ConversationService;
use PixelHub\Services\PhoneNormalizer;

// Carrega .env
Env::load();

echo "=== CRIANDO CONVERSA PARA MENSAGEM 171717 ===\n\n";

$db = DB::getConnection();

// 1. Busca o evento atualizado
echo "1. Buscando evento atualizado...\n";
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

echo "✅ Evento encontrado (ID: " . $event['id'] . ", Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . ")\n\n";

if (!$event['tenant_id']) {
    echo "❌ Evento ainda não tem tenant_id associado!\n";
    echo "   Execute primeiro o script fix-conversation-171717.php\n";
    exit(1);
}

// 2. Decodifica payload e metadata
$payload = json_decode($event['payload'], true);
$metadata = !empty($event['metadata']) ? json_decode($event['metadata'], true) : [];

// 3. Extrai informações do canal
$channelId = $payload['session']['id'] 
    ?? $payload['session']['session'] 
    ?? $payload['channel'] 
    ?? $payload['channelId']
    ?? $metadata['channel_id'] 
    ?? null;

echo "2. Extraindo informações do canal...\n";
echo "   Channel ID: " . ($channelId ?? 'NULL') . "\n";

// 4. Extrai informações do contato
$from = $payload['from'] 
    ?? $payload['message']['from'] 
    ?? $payload['data']['from'] 
    ?? $payload['raw']['payload']['from'] 
    ?? null;

$contactName = $payload['message']['notifyName'] 
    ?? $payload['raw']['payload']['notifyName'] 
    ?? $payload['raw']['payload']['sender']['verifiedName']
    ?? $payload['raw']['payload']['sender']['name']
    ?? null;

$text = $payload['text'] 
    ?? $payload['message']['text'] 
    ?? $payload['raw']['payload']['body'] 
    ?? null;

echo "   From: " . ($from ?? 'NULL') . "\n";
echo "   Contact Name: " . ($contactName ?? 'NULL') . "\n";
echo "   Text: " . ($text ?? 'NULL') . "\n\n";

if (!$from) {
    echo "❌ Não foi possível extrair 'from' do payload!\n";
    exit(1);
}

// 5. Resolve channel_account_id
echo "3. Resolvendo channel_account_id...\n";
$channelAccountId = null;

if ($channelId && $event['tenant_id']) {
    // Normaliza channel_id para comparação (remove espaços e case)
    $normalizedChannelId = strtolower(str_replace(' ', '', $channelId));
    
    $stmt = $db->prepare("
        SELECT id, channel_id 
        FROM tenant_message_channels 
        WHERE provider = 'wpp_gateway' 
        AND tenant_id = ?
        AND is_enabled = 1
    ");
    $stmt->execute([$event['tenant_id']]);
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($channels as $ch) {
        $normalizedDb = strtolower(str_replace(' ', '', $ch['channel_id']));
        if ($normalizedDb === $normalizedChannelId) {
            $channelAccountId = (int) $ch['id'];
            echo "✅ Channel Account ID encontrado: " . $channelAccountId . " (Channel: " . $ch['channel_id'] . ")\n\n";
            break;
        }
    }
    
    if (!$channelAccountId && count($channels) > 0) {
        // Fallback: usa o primeiro canal disponível
        $channelAccountId = (int) $channels[0]['id'];
        echo "⚠️  Usando primeiro canal disponível como fallback: " . $channelAccountId . "\n\n";
    }
}

// 6. Processa contact_external_id
echo "4. Processando contact_external_id...\n";
$contactExternalId = $from;

// Se for @lid, tenta mapear para número
if (strpos($from, '@lid') !== false) {
    $stmt = $db->prepare("
        SELECT phone_number 
        FROM whatsapp_business_ids 
        WHERE business_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$from]);
    $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($mapping && !empty($mapping['phone_number'])) {
        $contactExternalId = $mapping['phone_number'];
        echo "   Mapeamento @lid encontrado: " . $from . " -> " . $contactExternalId . "\n";
    } else {
        // Usa @lid diretamente se não tiver mapeamento
        echo "   Usando @lid diretamente (sem mapeamento): " . $from . "\n";
    }
} elseif (strpos($from, '@c.us') !== false || strpos($from, '@s.whatsapp.net') !== false) {
    // Extrai número do JID
    $digitsOnly = preg_replace('/@.*$/', '', $from);
    $digitsOnly = preg_replace('/[^0-9]/', '', $digitsOnly);
    
    if (strlen($digitsOnly) >= 10) {
        $contactExternalId = PhoneNormalizer::toE164OrNull($digitsOnly);
        if ($contactExternalId) {
            echo "   Número extraído e normalizado: " . $contactExternalId . "\n";
        } else {
            echo "   Usando número sem normalização: " . $digitsOnly . "\n";
            $contactExternalId = $digitsOnly;
        }
    }
}

echo "   Contact External ID final: " . $contactExternalId . "\n\n";

// 7. Gera conversation_key
echo "5. Gerando conversation_key...\n";
$accountPart = $channelAccountId ?: 'shared';
$conversationKey = sprintf('whatsapp_%s_%s', $accountPart, $contactExternalId);
echo "   Conversation Key: " . $conversationKey . "\n\n";

// 8. Verifica se conversa já existe
echo "6. Verificando se conversa já existe...\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE conversation_key = ?
    LIMIT 1
");
$stmt->execute([$conversationKey]);
$existingConv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingConv) {
    echo "✅ Conversa já existe (ID: " . $existingConv['id'] . ")\n";
    echo "   Atualizando metadados...\n";
    
    // Atualiza metadados
    $timestamp = $payload['timestamp'] 
        ?? $payload['message']['timestamp'] 
        ?? $payload['raw']['payload']['t'] 
        ?? time();
    
    // Converte timestamp
    if (is_numeric($timestamp)) {
        if ($timestamp < 10000000000) {
            $messageTimestamp = date('Y-m-d H:i:s', (int) $timestamp);
        } else {
            $messageTimestamp = date('Y-m-d H:i:s', (int) ($timestamp / 1000));
        }
    } else {
        $messageTimestamp = $event['created_at'];
    }
    
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET last_message_at = ?,
            last_message_direction = 'inbound',
            message_count = message_count + 1,
            unread_count = unread_count + 1,
            status = CASE WHEN status = 'closed' THEN 'open' ELSE status END,
            contact_name = COALESCE(?, contact_name),
            channel_id = COALESCE(?, channel_id),
            tenant_id = COALESCE(?, tenant_id),
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([
        $messageTimestamp,
        $contactName ?: null,
        $channelId ?: null,
        $event['tenant_id'],
        $existingConv['id']
    ]);
    
    echo "✅ Conversa atualizada com sucesso!\n\n";
    
    // Busca conversa atualizada
    $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$existingConv['id']]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // 9. Cria nova conversa
    echo "   Conversa não existe. Criando nova...\n";
    
    $timestamp = $payload['timestamp'] 
        ?? $payload['message']['timestamp'] 
        ?? $payload['raw']['payload']['t'] 
        ?? time();
    
    // Converte timestamp
    if (is_numeric($timestamp)) {
        if ($timestamp < 10000000000) {
            $messageTimestamp = date('Y-m-d H:i:s', (int) $timestamp);
        } else {
            $messageTimestamp = date('Y-m-d H:i:s', (int) ($timestamp / 1000));
        }
    } else {
        $messageTimestamp = $event['created_at'];
    }
    
    try {
        $insertStmt = $db->prepare("
            INSERT INTO conversations 
            (conversation_key, channel_type, channel_account_id, channel_id, session_id,
             contact_external_id, contact_name, tenant_id, status, 
             last_message_at, last_message_direction, message_count, unread_count,
             created_at, updated_at)
            VALUES (?, 'whatsapp', ?, ?, ?, ?, ?, ?, 'new', ?, 'inbound', 1, 1, ?, ?)
        ");
        
        $now = date('Y-m-d H:i:s');
        $insertStmt->execute([
            $conversationKey,
            $channelAccountId,
            $channelId,
            $channelId, // session_id = channel_id
            $contactExternalId,
            $contactName ?: null,
            $event['tenant_id'],
            $messageTimestamp,
            $now,
            $now
        ]);
        
        $conversationId = (int) $db->lastInsertId();
        echo "✅ Conversa criada com sucesso! (ID: " . $conversationId . ")\n\n";
        
        // Busca conversa criada
        $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
        echo "❌ Erro ao criar conversa: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 10. Exibe resultado final
if ($conversation) {
    echo "=== RESULTADO FINAL ===\n";
    echo str_repeat("=", 60) . "\n";
    echo "Conversation ID: " . $conversation['id'] . "\n";
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
    
    // Busca nome do tenant
    if ($conversation['tenant_id']) {
        $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $tenantStmt->execute([$conversation['tenant_id']]);
        $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            echo "Tenant: " . $tenant['name'] . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
}

echo "\n=== CONVERSA CRIADA/ATUALIZADA COM SUCESSO ===\n";

