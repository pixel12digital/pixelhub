<?php

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== BUSCA DETALHADA: Mensagens 4699 e 4223 ===\n\n";

// Busca por ID numérico (PK)
$stmt = $db->prepare("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_contact,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_contact,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) as msg_to,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.text')) as text,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.body')) as body
    FROM communication_events
    WHERE id IN (4699, 4223)
       OR (created_at >= '2026-01-14 15:24:00' 
           AND created_at <= '2026-01-14 15:27:00'
           AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message'))
    ORDER BY created_at
");
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($results) . "\n\n";

foreach ($results as $r) {
    echo "---\n";
    echo "ID (PK): {$r['id']}\n";
    echo "Event ID (UUID): {$r['event_id']}\n";
    echo "Event Type: {$r['event_type']}\n";
    echo "Created At: {$r['created_at']}\n";
    echo "Tenant ID: " . ($r['tenant_id'] ?? 'NULL') . "\n";
    
    $from = $r['from_contact'] ?: $r['msg_from'] ?: 'NULL';
    $to = $r['to_contact'] ?: $r['msg_to'] ?: 'NULL';
    echo "From: {$from}\n";
    echo "To: {$to}\n";
    
    $text = $r['text'] ?: $r['body'] ?: 'NULL';
    if ($text !== 'NULL' && strlen($text) > 0) {
        $preview = substr($text, 0, 100);
        echo "Text Preview: {$preview}" . (strlen($text) > 100 ? '...' : '') . "\n";
    }
    
    // Tenta encontrar conversation relacionada
    if ($from !== 'NULL' || $to !== 'NULL') {
        $contact = $from !== 'NULL' ? $from : $to;
        $normalizeContact = function($c) {
            if (empty($c)) return null;
            $cleaned = preg_replace('/@.*$/', '', (string) $c);
            return preg_replace('/[^0-9]/', '', $cleaned);
        };
        $normalized = $normalizeContact($contact);
        
        if ($normalized && $r['tenant_id']) {
            $convStmt = $db->prepare("
                SELECT id, contact_external_id, tenant_id, last_message_at
                FROM conversations
                WHERE tenant_id = ?
                  AND (
                      contact_external_id LIKE ?
                      OR contact_external_id LIKE ?
                  )
                LIMIT 1
            ");
            $pattern1 = "%{$normalized}%";
            $pattern2 = "%{$contact}%";
            $convStmt->execute([$r['tenant_id'], $pattern1, $pattern2]);
            $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conv) {
                echo "✅ Conversation encontrada: ID={$conv['id']}, Thread=whatsapp_{$conv['id']}, Contact={$conv['contact_external_id']}\n";
            } else {
                echo "❌ Conversation NÃO encontrada para este contato\n";
            }
        }
    }
    echo "\n";
}

echo "=== FIM ===\n";

