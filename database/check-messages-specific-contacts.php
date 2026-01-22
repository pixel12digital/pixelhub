<?php

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== BUSCA MENSAGENS: 554796164699 e 554796474223 ===\n\n";

$contacts = ['554796164699', '554796474223'];

foreach ($contacts as $contact) {
    echo "--- Buscando mensagens para: {$contact} ---\n";
    
    // Busca no período 15:24-15:27
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.text')) as text,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.body')) as body
        FROM communication_events ce
        WHERE ce.created_at >= '2026-01-14 15:24:00'
          AND ce.created_at <= '2026-01-14 15:27:00'
          AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
          AND (
              JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
              OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
              OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
              OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
          )
        ORDER BY ce.created_at ASC
    ");
    
    $pattern = "%{$contact}%";
    $stmt->execute([$pattern, $pattern, $pattern, $pattern]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total encontrado: " . count($results) . "\n\n";
    
    if (empty($results)) {
        echo "❌ NENHUMA MENSAGEM ENCONTRADA no período 15:24-15:27\n\n";
        
        // Busca última mensagem deste contato
        $stmt2 = $db->prepare("
            SELECT 
                ce.id,
                ce.event_id,
                ce.event_type,
                ce.created_at,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
              AND (
                  JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
              )
            ORDER BY ce.created_at DESC
            LIMIT 5
        ");
        $stmt2->execute([$pattern, $pattern, $pattern, $pattern]);
        $lastMessages = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($lastMessages)) {
            echo "📨 Últimas mensagens deste contato (fora do período):\n";
            foreach ($lastMessages as $msg) {
                $from = $msg['from_contact'] ?: 'NULL';
                $to = $msg['to_contact'] ?: 'NULL';
                echo "  - ID: {$msg['id']}, Event ID: {$msg['event_id']}, Created: {$msg['created_at']}, From: {$from}, To: {$to}\n";
            }
        }
        echo "\n";
        continue;
    }
    
    foreach ($results as $r) {
        echo "✅ Mensagem encontrada:\n";
        echo "  - ID (PK): {$r['id']}\n";
        echo "  - Event ID (UUID): {$r['event_id']}\n";
        echo "  - Event Type: {$r['event_type']}\n";
        echo "  - Created At: {$r['created_at']}\n";
        echo "  - Tenant ID: " . ($r['tenant_id'] ?? 'NULL') . "\n";
        
        $from = $r['from_contact'] ?: $r['msg_from'] ?: 'NULL';
        $to = $r['to_contact'] ?: $r['msg_to'] ?: 'NULL';
        echo "  - From: {$from}\n";
        echo "  - To: {$to}\n";
        
        $text = $r['text'] ?: $r['body'] ?: 'NULL';
        if ($text !== 'NULL' && strlen($text) > 0) {
            $preview = substr($text, 0, 100);
            echo "  - Text Preview: {$preview}" . (strlen($text) > 100 ? '...' : '') . "\n";
        }
        
        // Verifica conversation
        if ($r['tenant_id']) {
            $convStmt = $db->prepare("
                SELECT id, contact_external_id, tenant_id, last_message_at
                FROM conversations
                WHERE tenant_id = ?
                  AND contact_external_id LIKE ?
                LIMIT 1
            ");
            $convPattern = "%{$contact}%";
            $convStmt->execute([$r['tenant_id'], $convPattern]);
            $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conv) {
                echo "  - ✅ Thread ID: whatsapp_{$conv['id']}\n";
                echo "  - Conversation Contact: {$conv['contact_external_id']}\n";
            } else {
                echo "  - ❌ Thread ID: NÃO ENCONTRADO\n";
            }
        }
        echo "\n";
    }
}

echo "=== FIM ===\n";

