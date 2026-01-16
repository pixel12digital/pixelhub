<?php

/**
 * Script para criar/atualizar conversa para a mensagem 232323
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';
require_once __DIR__ . '/../src/Services/PhoneNormalizer.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

echo "=== CORRIGINDO CONVERSA PARA MENSAGEM 232323 ===\n\n";

$db = DB::getConnection();

// 1. Busca evento 232323
echo "1. Buscando evento 232323...\n";
$stmt = $db->prepare("
    SELECT * FROM communication_events
    WHERE id = 40
       OR (payload LIKE '%232323%' AND event_type = 'whatsapp.inbound.message')
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

// 2. Decodifica payload
$payload = json_decode($event['payload'], true);
$from = $payload['from'] 
    ?? $payload['message']['from'] 
    ?? $payload['data']['from'] 
    ?? null;

$contactName = $payload['message']['notifyName'] 
    ?? $payload['raw']['payload']['notifyName'] 
    ?? $payload['raw']['payload']['sender']['verifiedName']
    ?? $payload['raw']['payload']['sender']['name']
    ?? null;

$channelId = $payload['session']['id'] 
    ?? $payload['session']['session'] 
    ?? null;

$text = $payload['text'] 
    ?? $payload['message']['text'] 
    ?? null;

echo "2. Informações extraídas:\n";
echo "   From: " . ($from ?? 'NULL') . "\n";
echo "   Contact Name: " . ($contactName ?? 'NULL') . "\n";
echo "   Channel ID: " . ($channelId ?? 'NULL') . "\n";
echo "   Text: " . ($text ?? 'NULL') . "\n\n";

if (!$from) {
    echo "❌ Não foi possível extrair 'from'!\n";
    exit(1);
}

// 3. Processa contact_external_id
echo "3. Processando contact_external_id...\n";
$contactExternalId = $from;

// Se for @lid, tenta mapear
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
        // Usa @lid diretamente
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
        }
    }
}

echo "   Contact External ID final: " . $contactExternalId . "\n\n";

// 4. Busca conversa existente
echo "4. Buscando conversa existente...\n";
$stmt = $db->prepare("
    SELECT * FROM conversations 
    WHERE (contact_external_id = ? OR contact_external_id LIKE ?)
       AND channel_type = 'whatsapp'
       AND (channel_id = ? OR channel_id IS NULL)
    ORDER BY last_message_at DESC
    LIMIT 1
");
$searchTerm1 = $contactExternalId;
$searchTerm2 = '%' . preg_replace('/@.*$/', '', $from) . '%';
$stmt->execute([$searchTerm1, $searchTerm2, $channelId]);
$existingConv = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingConv) {
    echo "✅ Conversa existente encontrada (ID: " . $existingConv['id'] . ")\n";
    echo "   Conversation Key: " . ($existingConv['conversation_key'] ?? 'NULL') . "\n";
    echo "   Contact External ID: " . ($existingConv['contact_external_id'] ?? 'NULL') . "\n";
    echo "   Tenant ID: " . ($existingConv['tenant_id'] ?? 'NULL') . "\n";
    echo "   Message Count: " . ($existingConv['message_count'] ?? 0) . "\n";
    
    // Atualiza conversa
    echo "\n5. Atualizando conversa...\n";
    
    $timestamp = $payload['timestamp'] 
        ?? $payload['message']['timestamp'] 
        ?? $payload['raw']['payload']['t'] 
        ?? time();
    
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
            updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([
        $messageTimestamp,
        $contactName ?: null,
        $channelId ?: null,
        $existingConv['id']
    ]);
    
    echo "✅ Conversa atualizada!\n";
    $conversationId = $existingConv['id'];
} else {
    echo "❌ Conversa não encontrada. Criando nova...\n";
    
    // Cria nova conversa
    $conversationKey = 'whatsapp_shared_' . $contactExternalId;
    
    $timestamp = $payload['timestamp'] 
        ?? $payload['message']['timestamp'] 
        ?? $payload['raw']['payload']['t'] 
        ?? time();
    
    if (is_numeric($timestamp)) {
        if ($timestamp < 10000000000) {
            $messageTimestamp = date('Y-m-d H:i:s', (int) $timestamp);
        } else {
            $messageTimestamp = date('Y-m-d H:i:s', (int) ($timestamp / 1000));
        }
    } else {
        $messageTimestamp = $event['created_at'];
    }
    
    $insertStmt = $db->prepare("
        INSERT INTO conversations 
        (conversation_key, channel_type, channel_id, session_id,
         contact_external_id, contact_name, tenant_id, status, 
         last_message_at, last_message_direction, message_count, unread_count,
         created_at, updated_at)
        VALUES (?, 'whatsapp', ?, ?, ?, ?, NULL, 'new', ?, 'inbound', 1, 1, ?, ?)
    ");
    
    $now = date('Y-m-d H:i:s');
    $insertStmt->execute([
        $conversationKey,
        $channelId,
        $channelId,
        $contactExternalId,
        $contactName ?: null,
        $messageTimestamp,
        $now,
        $now
    ]);
    
    $conversationId = (int) $db->lastInsertId();
    echo "✅ Conversa criada (ID: " . $conversationId . ")\n";
}

echo "\n=== RESUMO ===\n";
echo "Conversation ID: " . $conversationId . "\n";
echo "Contact: " . $contactExternalId . "\n";
echo "Channel ID: " . ($channelId ?? 'NULL') . "\n";
echo "Tenant ID: NULL (sistema Pixel Hub)\n";
echo "\n✅ Conversa criada/atualizada com sucesso!\n";
echo "   Agora a mensagem 232323 deve aparecer na thread.\n";

