<?php

/**
 * Script para corrigir contact_external_id da conversa
 * 
 * A conversa foi criada com número 554796474223, mas os eventos têm @lid
 * Precisamos verificar o mapeamento e corrigir
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== CORRIGINDO CONTACT_EXTERNAL_ID DA CONVERSA ===\n\n";

// 1. Verifica mapeamento @lid
echo "1. Verificando mapeamento @lid na tabela whatsapp_business_ids...\n";
$stmt = $db->query("
    SELECT business_id, phone_number
    FROM whatsapp_business_ids
    WHERE business_id LIKE '%@lid'
    ORDER BY id DESC
    LIMIT 10
");
$mappings = $stmt->fetchAll();

if (count($mappings) > 0) {
    echo "   Mapeamentos encontrados:\n";
    foreach ($mappings as $m) {
        echo "     - business_id: {$m['business_id']}, phone: {$m['phone_number']}, session: {$m['session_id']}\n";
    }
} else {
    echo "   Nenhum mapeamento encontrado\n";
}

echo "\n";

// 2. Verifica eventos recentes com @lid
echo "2. Verificando eventos com @lid...\n";
$stmt = $db->query("
    SELECT 
        event_id,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.message.text') as message_text
    FROM communication_events
    WHERE event_type = 'whatsapp.inbound.message'
    AND JSON_EXTRACT(payload, '$.message.from') LIKE '%@lid'
    ORDER BY created_at DESC
    LIMIT 5
");
$eventsWithLid = $stmt->fetchAll();

if (count($eventsWithLid) > 0) {
    echo "   Eventos com @lid encontrados:\n";
    foreach ($eventsWithLid as $e) {
        $from = trim($e['message_from'], '"');
        echo "     - Event ID: {$e['event_id']}, from: {$from}, text: " . ($e['message_text'] ?: 'NULL') . "\n";
        
        // Verifica se esse @lid está mapeado
        $checkStmt = $db->prepare("
            SELECT phone_number 
            FROM whatsapp_business_ids 
            WHERE business_id = ?
        ");
        $checkStmt->execute([$from]);
        $mapping = $checkStmt->fetch();
        
        if ($mapping) {
            echo "       -> Mapeado para: {$mapping['phone_number']}\n";
        } else {
            echo "       -> NÃO mapeado\n";
        }
    }
} else {
    echo "   Nenhum evento com @lid encontrado\n";
}

echo "\n";

// 3. Verifica conversa atual
echo "3. Verificando conversa atual...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        remote_key,
        message_count
    FROM conversations
    WHERE id = 1
");
$stmt->execute();
$conversation = $stmt->fetch();

if ($conversation) {
    echo "   Conversa ID: {$conversation['id']}\n";
    echo "   conversation_key: {$conversation['conversation_key']}\n";
    echo "   contact_external_id: {$conversation['contact_external_id']}\n";
    echo "   remote_key: " . ($conversation['remote_key'] ?: 'NULL') . "\n";
    echo "   message_count: {$conversation['message_count']}\n";
    
    // Verifica se o contact_external_id atual está nos eventos
    $checkStmt = $db->prepare("
        SELECT COUNT(*) as count
        FROM communication_events
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.to') LIKE ?
            OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.message.to') LIKE ?
        )
    ");
    $pattern = "%{$conversation['contact_external_id']}%";
    $checkStmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $eventCount = $checkStmt->fetchColumn();
    
    echo "   Eventos com esse número: {$eventCount}\n";
    
    if ($eventCount == 0) {
        echo "\n   ⚠️  PROBLEMA: Nenhum evento encontrado com o número {$conversation['contact_external_id']}!\n";
        echo "   A conversa precisa ser corrigida para usar o @lid ou o número correto dos eventos.\n";
        
        // Busca o @lid mais recente dos eventos
        $lidStmt = $db->query("
            SELECT DISTINCT JSON_EXTRACT(payload, '$.message.from') as message_from
            FROM communication_events
            WHERE event_type = 'whatsapp.inbound.message'
            AND JSON_EXTRACT(payload, '$.message.from') LIKE '%@lid'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $lidEvent = $lidStmt->fetch();
        
        if ($lidEvent) {
            $lid = trim($lidEvent['message_from'], '"');
            echo "\n   Sugestão: Usar o @lid dos eventos: {$lid}\n";
            
            // Verifica se há mapeamento
            $mapStmt = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ?");
            $mapStmt->execute([$lid]);
            $mappedPhone = $mapStmt->fetchColumn();
            
            if ($mappedPhone) {
                echo "   Esse @lid está mapeado para: {$mappedPhone}\n";
                echo "\n   Deseja corrigir a conversa para usar o número mapeado? (Execute manualmente)\n";
                echo "   UPDATE conversations SET contact_external_id = '{$mappedPhone}' WHERE id = 1;\n";
            } else {
                echo "   Esse @lid NÃO está mapeado.\n";
                echo "   Opções:\n";
                echo "   1. Criar mapeamento na tabela whatsapp_business_ids\n";
                echo "   2. Usar o @lid diretamente como contact_external_id\n";
            }
        }
    }
} else {
    echo "   Conversa não encontrada!\n";
}

echo "\n";

