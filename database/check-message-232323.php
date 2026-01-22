<?php

/**
 * Script para verificar a mensagem 232323 que não aparece na thread
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

echo "=== VERIFICANDO MENSAGEM 232323 ===\n\n";

$db = DB::getConnection();

// 1. Busca evento com 232323
echo "1. Buscando evento com '232323'...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        source_system,
        tenant_id,
        created_at,
        JSON_EXTRACT(payload, '$.message.text') as message_text,
        JSON_EXTRACT(payload, '$.message.from') as message_from,
        JSON_EXTRACT(payload, '$.session.id') as session_id
    FROM communication_events
    WHERE payload LIKE '%232323%'
       OR JSON_EXTRACT(payload, '$.message.text') = '232323'
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($events) > 0) {
    echo "✅ Encontrados " . count($events) . " evento(s):\n\n";
    foreach ($events as $event) {
        echo "Event ID: " . $event['id'] . "\n";
        echo "  Event UUID: " . ($event['event_id'] ?? 'NULL') . "\n";
        echo "  Event Type: " . ($event['event_type'] ?? 'NULL') . "\n";
        echo "  Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
        echo "  Session ID: " . trim($event['session_id'] ?? 'NULL', '"') . "\n";
        echo "  From: " . trim($event['message_from'] ?? 'NULL', '"') . "\n";
        echo "  Text: " . trim($event['message_text'] ?? 'NULL', '"') . "\n";
        echo "  Created At: " . ($event['created_at'] ?? 'NULL') . "\n";
        echo "\n";
    }
} else {
    echo "❌ Nenhum evento encontrado com '232323'!\n";
}

echo "\n";

// 2. Busca conversa relacionada
echo "2. Buscando conversa relacionada...\n";
if (count($events) > 0) {
    $event = $events[0];
    $from = trim($event['message_from'] ?? '', '"');
    $sessionId = trim($event['session_id'] ?? '', '"');
    
    if ($from) {
        // Remove @lid ou @c.us
        $contactId = preg_replace('/@.*$/', '', $from);
        
        echo "   From original: " . $from . "\n";
        echo "   Contact ID extraído: " . $contactId . "\n";
        echo "   Session ID: " . $sessionId . "\n";
        
        // Busca conversa
        $convStmt = $db->prepare("
            SELECT * FROM conversations 
            WHERE (contact_external_id LIKE ? OR contact_external_id = ?)
               AND (channel_id = ? OR channel_id IS NULL)
            ORDER BY last_message_at DESC
            LIMIT 5
        ");
        $searchTerm = '%' . $contactId . '%';
        $convStmt->execute([$searchTerm, $from, $sessionId]);
        $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($conversations) > 0) {
            echo "✅ Encontradas " . count($conversations) . " conversa(s):\n\n";
            foreach ($conversations as $conv) {
                echo "Conversation ID: " . $conv['id'] . "\n";
                echo "  Conversation Key: " . ($conv['conversation_key'] ?? 'NULL') . "\n";
                echo "  Contact External ID: " . ($conv['contact_external_id'] ?? 'NULL') . "\n";
                echo "  Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
                echo "  Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
                echo "  Last Message At: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
                echo "  Message Count: " . ($conv['message_count'] ?? 0) . "\n";
                echo "\n";
            }
        } else {
            echo "❌ Nenhuma conversa encontrada!\n";
        }
    }
}

echo "\n";

// 3. Verifica se o evento está sendo filtrado incorretamente
echo "3. Verificando filtros que podem estar excluindo a mensagem...\n";
if (count($events) > 0) {
    $event = $events[0];
    $eventId = $event['id'];
    $tenantId = $event['tenant_id'];
    $from = trim($event['message_from'] ?? '', '"');
    
    echo "   Event ID: " . $eventId . "\n";
    echo "   Event Tenant ID: " . ($tenantId ?? 'NULL') . "\n";
    echo "   From: " . $from . "\n";
    
    // Verifica se há conversa com tenant_id diferente
    if ($from) {
        $contactId = preg_replace('/@.*$/', '', $from);
        $convStmt = $db->prepare("
            SELECT id, tenant_id, contact_external_id 
            FROM conversations 
            WHERE contact_external_id LIKE ?
            LIMIT 1
        ");
        $searchTerm = '%' . $contactId . '%';
        $convStmt->execute([$searchTerm]);
        $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conv) {
            echo "\n   Conversa encontrada:\n";
            echo "     Conversation ID: " . $conv['id'] . "\n";
            echo "     Conversation Tenant ID: " . ($conv['tenant_id'] ?? 'NULL') . "\n";
            echo "     Contact External ID: " . ($conv['contact_external_id'] ?? 'NULL') . "\n";
            
            if ($tenantId && $conv['tenant_id'] && $tenantId != $conv['tenant_id']) {
                echo "\n   ⚠️  PROBLEMA: Tenant IDs não coincidem!\n";
                echo "      Event Tenant ID: " . $tenantId . "\n";
                echo "      Conversation Tenant ID: " . $conv['tenant_id'] . "\n";
                echo "      Isso pode fazer a mensagem ser filtrada!\n";
            } elseif (!$tenantId && $conv['tenant_id']) {
                echo "\n   ⚠️  PROBLEMA: Evento sem tenant_id mas conversa tem!\n";
                echo "      Event Tenant ID: NULL\n";
                echo "      Conversation Tenant ID: " . $conv['tenant_id'] . "\n";
            } elseif ($tenantId && !$conv['tenant_id']) {
                echo "\n   ⚠️  PROBLEMA: Evento tem tenant_id mas conversa não tem!\n";
                echo "      Event Tenant ID: " . $tenantId . "\n";
                echo "      Conversation Tenant ID: NULL\n";
            } else {
                echo "\n   ✅ Tenant IDs estão consistentes\n";
            }
        }
    }
}

echo "\n=== FIM ===\n";

