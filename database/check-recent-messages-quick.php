<?php

/**
 * Script rápido para verificar últimas mensagens após envio
 * 
 * Uso: php database/check-recent-messages-quick.php
 * 
 * Verifica as últimas 10 mensagens criadas nos últimos 30 segundos
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICAÇÃO RÁPIDA: ÚLTIMAS MENSAGENS ===\n\n";

// Busca últimas 10 mensagens criadas nos últimos 30 segundos
$stmt = $db->query("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        ce.source_system,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as msg_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as msg_to
    FROM communication_events ce
    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
      AND ce.created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ORDER BY ce.created_at DESC
    LIMIT 10
");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Mensagens criadas nos últimos 30 segundos: " . count($results) . "\n\n";

if (empty($results)) {
    echo "❌ NENHUMA MENSAGEM encontrada nos últimos 30 segundos\n";
    echo "   → Webhook pode não ter chegado ou foi descartado\n\n";
} else {
    foreach ($results as $r) {
        echo "✅ Mensagem encontrada:\n";
        echo "  - ID (PK): {$r['id']}\n";
        echo "  - Event ID: {$r['event_id']}\n";
        echo "  - Created: {$r['created_at']}\n";
        echo "  - Source: {$r['source_system']}\n";
        echo "  - Tenant ID: " . ($r['tenant_id'] ?? 'NULL') . "\n";
        
        $from = $r['from_contact'] ?: $r['msg_from'] ?: 'NULL';
        $to = $r['to_contact'] ?: $r['msg_to'] ?: 'NULL';
        echo "  - From: {$from}\n";
        echo "  - To: {$to}\n";
        
        // Verifica conversation
        if ($r['tenant_id'] && $from !== 'NULL') {
            $normalizeContact = function($c) {
                if (empty($c)) return null;
                $cleaned = preg_replace('/@.*$/', '', (string) $c);
                return preg_replace('/[^0-9]/', '', $cleaned);
            };
            $normalized = $normalizeContact($from);
            
            if ($normalized) {
                $convStmt = $db->prepare("
                    SELECT id, contact_external_id, tenant_id
                    FROM conversations
                    WHERE tenant_id = ?
                      AND contact_external_id LIKE ?
                    LIMIT 1
                ");
                $pattern = "%{$normalized}%";
                $convStmt->execute([$r['tenant_id'], $pattern]);
                $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    echo "  - ✅ Thread ID: whatsapp_{$conv['id']}\n";
                } else {
                    echo "  - ❌ Thread ID: NÃO ENCONTRADO\n";
                }
            }
        }
        echo "\n";
    }
}

echo "=== FIM ===\n";
echo "\n💡 Dica: Execute este script imediatamente após enviar uma mensagem do ServPro\n";

