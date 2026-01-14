<?php

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== EVENTOS SIMULADOS (pixelhub_test) ===\n\n";

$stmt = $db->query("
    SELECT 
        id,
        event_id,
        event_type,
        created_at,
        source_system,
        tenant_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) as from_contact,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) as to_contact,
        JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) as msg_to
    FROM communication_events
    WHERE source_system = 'pixelhub_test'
    ORDER BY created_at DESC
    LIMIT 20
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total encontrado: " . count($results) . "\n\n";

foreach ($results as $r) {
    echo "---\n";
    echo "ID (PK): {$r['id']}\n";
    echo "Event ID (UUID): {$r['event_id']}\n";
    echo "Event Type: {$r['event_type']}\n";
    echo "Created At: {$r['created_at']}\n";
    echo "Source System: {$r['source_system']}\n";
    echo "Tenant ID: " . ($r['tenant_id'] ?? 'NULL') . "\n";
    
    $from = $r['from_contact'] ?: $r['msg_from'] ?: 'NULL';
    $to = $r['to_contact'] ?: $r['msg_to'] ?: 'NULL';
    echo "From: {$from}\n";
    echo "To: {$to}\n";
    
    // Verifica se há conversation relacionada
    $contact = $from !== 'NULL' ? $from : $to;
    if ($contact !== 'NULL' && $r['tenant_id']) {
        $normalizeContact = function($c) {
            if (empty($c)) return null;
            $cleaned = preg_replace('/@.*$/', '', (string) $c);
            return preg_replace('/[^0-9]/', '', $cleaned);
        };
        $normalized = $normalizeContact($contact);
        
        if ($normalized) {
            $convStmt = $db->prepare("
                SELECT id, contact_external_id, tenant_id, last_message_at
                FROM conversations
                WHERE tenant_id = ?
                  AND contact_external_id LIKE ?
                LIMIT 1
            ");
            $pattern = "%{$normalized}%";
            $convStmt->execute([$r['tenant_id'], $pattern]);
            $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conv) {
                echo "✅ Conversation encontrada: ID={$conv['id']}, Thread=whatsapp_{$conv['id']}, Contact={$conv['contact_external_id']}\n";
                echo "   Last Message At: " . ($conv['last_message_at'] ?? 'NULL') . "\n";
            } else {
                echo "❌ Conversation NÃO encontrada para este contato\n";
            }
        }
    }
    echo "\n";
}

echo "=== FIM ===\n";

