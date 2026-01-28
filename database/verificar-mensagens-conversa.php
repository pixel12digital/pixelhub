<?php
/**
 * Verifica mensagens da conversa 112 (whatsapp_112)
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load(__DIR__ . '/../.env');

echo "=== MENSAGENS DA CONVERSA 112 ===\n\n";

try {
    $db = DB::getConnection();
    
    // Busca conversa
    $stmt = $db->prepare("SELECT * FROM conversations WHERE id = 112");
    $stmt->execute();
    $conv = $stmt->fetch();
    
    if (!$conv) {
        echo "❌ Conversa 112 não encontrada!\n";
        exit;
    }
    
    echo "CONVERSA:\n";
    echo "  id: {$conv['id']}\n";
    echo "  contact_external_id: {$conv['contact_external_id']}\n";
    echo "  tenant_id: {$conv['tenant_id']}\n\n";
    
    // Busca eventos COM conversation_id = 112
    echo "--- EVENTOS COM conversation_id = 112 ---\n";
    $stmt = $db->query("
        SELECT event_id, event_type, created_at, 
               JSON_EXTRACT(payload, '$.type') as msg_type
        FROM communication_events 
        WHERE conversation_id = 112 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $events = $stmt->fetchAll();
    echo "Encontrados: " . count($events) . "\n";
    foreach ($events as $e) {
        echo "  - {$e['event_id']} | {$e['event_type']} | {$e['msg_type']} | {$e['created_at']}\n";
    }
    
    // Busca eventos pelo número do contato (como a query faz)
    $contactExternalId = $conv['contact_external_id'];
    $normalized = preg_replace('/\D/', '', $contactExternalId);
    
    echo "\n--- EVENTOS PELO NÚMERO (normalizado: {$normalized}) ---\n";
    $stmt = $db->prepare("
        SELECT event_id, event_type, created_at, 
               JSON_EXTRACT(payload, '$.type') as msg_type,
               JSON_EXTRACT(payload, '$.from') as from_field,
               JSON_EXTRACT(payload, '$.to') as to_field
        FROM communication_events 
        WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
        AND (
            JSON_EXTRACT(payload, '$.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.to') LIKE ?
            OR JSON_EXTRACT(payload, '$.message.from') LIKE ?
            OR JSON_EXTRACT(payload, '$.message.to') LIKE ?
        )
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $pattern = "%{$normalized}%";
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $events = $stmt->fetchAll();
    echo "Encontrados: " . count($events) . "\n";
    foreach ($events as $e) {
        echo "  - {$e['event_id']} | {$e['event_type']} | {$e['msg_type']} | from={$e['from_field']} to={$e['to_field']} | {$e['created_at']}\n";
    }
    
    // Verifica eventos de áudio outbound recentes
    echo "\n--- ÁUDIOS OUTBOUND RECENTES (últimas 2h) ---\n";
    $stmt = $db->query("
        SELECT event_id, event_type, created_at, conversation_id,
               JSON_EXTRACT(payload, '$.type') as msg_type,
               JSON_EXTRACT(payload, '$.to') as to_field
        FROM communication_events 
        WHERE event_type = 'whatsapp.outbound.message'
        AND JSON_EXTRACT(payload, '$.type') = '\"audio\"'
        AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $events = $stmt->fetchAll();
    echo "Encontrados: " . count($events) . "\n";
    foreach ($events as $e) {
        echo "  - {$e['event_id']} | conv_id={$e['conversation_id']} | to={$e['to_field']} | {$e['created_at']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
