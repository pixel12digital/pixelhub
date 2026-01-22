<?php

/**
 * Script para corrigir o número do Charles Dietrich na tabela conversations
 * 
 * Número correto: 554796164699
 * Número incorreto que aparece: 554797146908
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORRIGINDO NÚMERO DO CHARLES DIETRICH ===\n\n";

$correctPhone = '554796164699';
$wrongPhone = '554797146908';

// 1. Verifica conversas com o número errado
echo "1. Verificando conversas com número incorreto ({$wrongPhone})...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        message_count,
        created_at
    FROM conversations
    WHERE contact_external_id = ?
       OR contact_external_id LIKE ?
       OR conversation_key LIKE ?
");
$stmt->execute([$wrongPhone, "%{$wrongPhone}%", "%{$wrongPhone}%"]);
$conversations = $stmt->fetchAll();

if (count($conversations) > 0) {
    echo "   Encontradas " . count($conversations) . " conversa(s) com número incorreto:\n";
    foreach ($conversations as $conv) {
        echo "     - ID: {$conv['id']}, Key: {$conv['conversation_key']}, Contact: {$conv['contact_external_id']}, Nome: " . ($conv['contact_name'] ?: 'NULL') . "\n";
    }
} else {
    echo "   Nenhuma conversa encontrada com número incorreto\n";
}

echo "\n";

// 2. Verifica conversas com o número correto
echo "2. Verificando conversas com número correto ({$correctPhone})...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        message_count,
        created_at
    FROM conversations
    WHERE contact_external_id = ?
       OR contact_external_id LIKE ?
       OR conversation_key LIKE ?
");
$stmt->execute([$correctPhone, "%{$correctPhone}%", "%{$correctPhone}%"]);
$correctConversations = $stmt->fetchAll();

if (count($correctConversations) > 0) {
    echo "   Encontradas " . count($correctConversations) . " conversa(s) com número correto:\n";
    foreach ($correctConversations as $conv) {
        echo "     - ID: {$conv['id']}, Key: {$conv['conversation_key']}, Contact: {$conv['contact_external_id']}, Nome: " . ($conv['contact_name'] ?: 'NULL') . "\n";
    }
} else {
    echo "   Nenhuma conversa encontrada com número correto\n";
}

echo "\n";

// 3. Verifica eventos com ambos os números
echo "3. Verificando eventos de comunicação...\n";
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN JSON_EXTRACT(payload, '$.from') LIKE '%{$correctPhone}%' 
                  OR JSON_EXTRACT(payload, '$.message.from') LIKE '%{$correctPhone}%' 
                  OR JSON_EXTRACT(payload, '$.to') LIKE '%{$correctPhone}%' 
                  OR JSON_EXTRACT(payload, '$.message.to') LIKE '%{$correctPhone}%' 
            THEN 1 ELSE 0 END) as correct_count,
        SUM(CASE WHEN JSON_EXTRACT(payload, '$.from') LIKE '%{$wrongPhone}%' 
                  OR JSON_EXTRACT(payload, '$.message.from') LIKE '%{$wrongPhone}%' 
                  OR JSON_EXTRACT(payload, '$.to') LIKE '%{$wrongPhone}%' 
                  OR JSON_EXTRACT(payload, '$.message.to') LIKE '%{$wrongPhone}%' 
            THEN 1 ELSE 0 END) as wrong_count
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
");
$eventStats = $stmt->fetch();

echo "   Total de eventos: {$eventStats['total']}\n";
echo "   Eventos com número correto ({$correctPhone}): {$eventStats['correct_count']}\n";
echo "   Eventos com número incorreto ({$wrongPhone}): {$eventStats['wrong_count']}\n";

echo "\n";

// 4. Verifica mapeamento @lid
echo "4. Verificando mapeamento @lid...\n";
$stmt = $db->prepare("
    SELECT business_id, phone_number, tenant_id
    FROM whatsapp_business_ids
    WHERE phone_number = ? OR phone_number = ?
       OR business_id LIKE ?
       OR business_id LIKE ?
");
$stmt->execute([
    $correctPhone, 
    $wrongPhone,
    "%{$correctPhone}%",
    "%{$wrongPhone}%"
]);
$mappings = $stmt->fetchAll();

if (count($mappings) > 0) {
    echo "   Mapeamentos encontrados:\n";
    foreach ($mappings as $m) {
        echo "     - business_id: {$m['business_id']}, phone: {$m['phone_number']}, tenant: {$m['tenant_id']}\n";
    }
} else {
    echo "   Nenhum mapeamento encontrado\n";
}

echo "\n";

// 5. Proposta de correção
if (count($conversations) > 0) {
    echo "5. PROPOSTA DE CORREÇÃO:\n";
    echo "   As seguintes conversas precisam ser corrigidas:\n\n";
    
    foreach ($conversations as $conv) {
        $convId = $conv['id'];
        $oldContact = $conv['contact_external_id'];
        $oldKey = $conv['conversation_key'];
        
        // Tenta construir nova key
        $newKey = preg_replace('/' . preg_quote($wrongPhone, '/') . '/', $correctPhone, $oldKey);
        if ($newKey === $oldKey) {
            // Se não substituiu, tenta construir do zero
            $tenantId = $conv['tenant_id'] ?? 0;
            $newKey = "whatsapp_{$tenantId}_{$correctPhone}";
        }
        
        echo "   Conversa ID {$convId}:\n";
        echo "     - contact_external_id: '{$oldContact}' → '{$correctPhone}'\n";
        if ($oldKey !== $newKey) {
            echo "     - conversation_key: '{$oldKey}' → '{$newKey}'\n";
        }
        echo "\n";
        echo "   SQL para corrigir:\n";
        echo "   UPDATE conversations SET contact_external_id = '{$correctPhone}'";
        if ($oldKey !== $newKey) {
            echo ", conversation_key = '{$newKey}'";
        }
        echo " WHERE id = {$convId};\n\n";
    }
    
    echo "   Deseja executar a correção? (Execute manualmente se necessário)\n";
} else {
    echo "5. Nenhuma correção necessária - não foram encontradas conversas com número incorreto.\n";
}

echo "\n";

