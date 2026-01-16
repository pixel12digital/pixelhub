<?php

/**
 * Script para testar criação direta de conversa
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== TESTE DIRETO DE CRIAÇÃO DE CONVERSA ===\n\n";

$conversationKey = 'whatsapp_shared_554796164699';
$channelType = 'whatsapp';
$channelAccountId = null;
$channelId = 'pixel12digital';
$contactExternalId = '554796164699';
$remoteIdRaw = '554796164699@c.us';
$remoteKey = 'tel:554796164699';
$contactKey = 'wpp_gateway:pixel12digital:tel:554796164699';
$threadKey = 'wpp_gateway:pixel12digital:tel:554796164699';
$contactName = 'Charles Dietrich';
$tenantId = null;
$isIncomingLead = 1;
$direction = 'inbound';
$now = date('Y-m-d H:i:s');

echo "Dados a inserir:\n";
echo "conversation_key: {$conversationKey}\n";
echo "channel_type: {$channelType}\n";
echo "channel_account_id: " . ($channelAccountId ?: 'NULL') . "\n";
echo "channel_id: {$channelId}\n";
echo "contact_external_id: {$contactExternalId}\n";
echo "tenant_id: " . ($tenantId ?: 'NULL') . "\n";
echo "is_incoming_lead: {$isIncomingLead}\n";
echo "\n";

try {
    $stmt = $db->prepare("
        INSERT INTO conversations 
        (conversation_key, channel_type, channel_account_id, channel_id, session_id,
         contact_external_id, remote_id_raw, remote_key, contact_key, thread_key,
         contact_name, tenant_id, is_incoming_lead, status, last_message_at, last_message_direction, 
         message_count, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, 1, ?, ?)
    ");

    $stmt->execute([
        $conversationKey,
        $channelType,
        $channelAccountId,
        $channelId,
        $channelId, // session_id = channel_id
        $contactExternalId,
        $remoteIdRaw,
        $remoteKey,
        $contactKey,
        $threadKey,
        $contactName,
        $tenantId,
        $isIncomingLead,
        $now, // last_message_at
        $direction,
        $now,
        $now
    ]);

    $conversationId = (int) $db->lastInsertId();
    echo "SUCESSO: Conversa criada com ID {$conversationId}\n";
    
    // Verifica se foi criada
    $checkStmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
    $checkStmt->execute([$conversationId]);
    $conversation = $checkStmt->fetch();
    
    if ($conversation) {
        echo "\nConversa criada:\n";
        echo "ID: {$conversation['id']}\n";
        echo "Key: {$conversation['conversation_key']}\n";
        echo "Contact: {$conversation['contact_external_id']}\n";
        echo "Tenant ID: " . ($conversation['tenant_id'] ?? 'NULL') . "\n";
        echo "is_incoming_lead: {$conversation['is_incoming_lead']}\n";
    }
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($e instanceof \PDOException) {
        echo "SQL State: " . $e->getCode() . "\n";
        echo "SQL Error Info: " . print_r($e->errorInfo, true) . "\n";
    }
}

