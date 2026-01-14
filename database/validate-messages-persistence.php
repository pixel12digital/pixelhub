<?php

/**
 * Script para validar persistência das mensagens 4699 e 4223
 * 
 * Uso: php database/validate-messages-persistence.php
 * 
 * Valida:
 * 1. Se as mensagens existem na tabela communication_events
 * 2. Thread_id, created_at, event_id, contact_phone/from/to de cada mensagem
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

// Carrega .env
try {
    Env::load();
} catch (\Exception $e) {
    die("Erro ao carregar .env: " . $e->getMessage() . "\n");
}

$db = DB::getConnection();

echo "=== VALIDAÇÃO DE PERSISTÊNCIA DE MENSAGENS ===\n\n";

// Event IDs específicos mencionados
$eventIds = ['4699', '4223'];
$timeRange = [
    'start' => '2026-01-14 15:24:00',
    'end' => '2026-01-14 15:27:00'
];

echo "Buscando mensagens:\n";
echo "  - Event IDs: " . implode(', ', $eventIds) . "\n";
echo "  - Período: {$timeRange['start']} até {$timeRange['end']}\n\n";

// Busca por event_id (pode ser o ID numérico ou UUID)
$results = [];

foreach ($eventIds as $eventId) {
    // Tenta buscar por event_id exato (pode ser UUID ou numérico)
    $stmt = $db->prepare("
        SELECT 
            ce.id,
            ce.event_id,
            ce.event_type,
            ce.created_at,
            ce.tenant_id,
            ce.payload,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
            JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to
        FROM communication_events ce
        WHERE ce.event_id = ? 
           OR ce.id = ?
        ORDER BY ce.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$eventId, $eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        $results[] = $event;
    }
}

// Também busca por período de tempo (15:24-15:27)
$stmt = $db->prepare("
    SELECT 
        ce.id,
        ce.event_id,
        ce.event_type,
        ce.created_at,
        ce.tenant_id,
        ce.payload,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
        JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to
    FROM communication_events ce
    WHERE ce.created_at >= ? 
      AND ce.created_at <= ?
      AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
    ORDER BY ce.created_at ASC
");
$stmt->execute([$timeRange['start'], $timeRange['end']]);
$timeRangeEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== RESULTADOS ===\n\n";

if (empty($results) && empty($timeRangeEvents)) {
    echo "❌ NENHUMA MENSAGEM ENCONTRADA\n";
    echo "   - Verifique se os event_ids estão corretos\n";
    echo "   - Verifique se o período de tempo está correto\n\n";
} else {
    // Mostra eventos encontrados por event_id
    if (!empty($results)) {
        echo "✅ MENSAGENS ENCONTRADAS POR EVENT_ID:\n\n";
        foreach ($results as $event) {
            echo "Event ID: {$event['event_id']}\n";
            echo "  - ID (PK): {$event['id']}\n";
            echo "  - Event Type: {$event['event_type']}\n";
            echo "  - Created At: {$event['created_at']}\n";
            echo "  - Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
            echo "  - From (payload.from): " . ($event['from_contact'] ?? 'NULL') . "\n";
            echo "  - To (payload.to): " . ($event['to_contact'] ?? 'NULL') . "\n";
            echo "  - From (payload.message.from): " . ($event['message_from'] ?? 'NULL') . "\n";
            echo "  - To (payload.message.to): " . ($event['message_to'] ?? 'NULL') . "\n";
            
            // Tenta identificar thread_id relacionado
            $contact = $event['from_contact'] ?? $event['message_from'] ?? $event['to_contact'] ?? $event['message_to'] ?? null;
            if ($contact && $event['tenant_id']) {
                // Busca conversation relacionada
                $normalizeContact = function($c) {
                    if (empty($c)) return null;
                    $cleaned = preg_replace('/@.*$/', '', (string) $c);
                    return preg_replace('/[^0-9]/', '', $cleaned);
                };
                $normalized = $normalizeContact($contact);
                
                $convStmt = $db->prepare("
                    SELECT id, contact_external_id, tenant_id
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
                $convStmt->execute([$event['tenant_id'], $pattern1, $pattern2]);
                $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    echo "  - Thread ID (whatsapp_{$conv['id']}): whatsapp_{$conv['id']}\n";
                    echo "  - Conversation ID: {$conv['id']}\n";
                    echo "  - Contact External ID: {$conv['contact_external_id']}\n";
                } else {
                    echo "  - Thread ID: NÃO ENCONTRADO (sem conversation)\n";
                }
            }
            echo "\n";
        }
    }
    
    // Mostra eventos encontrados por período
    if (!empty($timeRangeEvents)) {
        echo "✅ MENSAGENS ENCONTRADAS NO PERÍODO 15:24-15:27:\n\n";
        echo "Total: " . count($timeRangeEvents) . " mensagem(ns)\n\n";
        
        foreach ($timeRangeEvents as $event) {
            echo "Event ID: {$event['event_id']}\n";
            echo "  - ID (PK): {$event['id']}\n";
            echo "  - Event Type: {$event['event_type']}\n";
            echo "  - Created At: {$event['created_at']}\n";
            echo "  - Tenant ID: " . ($event['tenant_id'] ?? 'NULL') . "\n";
            echo "  - From: " . ($event['from_contact'] ?? $event['message_from'] ?? 'NULL') . "\n";
            echo "  - To: " . ($event['to_contact'] ?? $event['message_to'] ?? 'NULL') . "\n";
            
            // Tenta identificar thread_id relacionado
            $contact = $event['from_contact'] ?? $event['message_from'] ?? $event['to_contact'] ?? $event['message_to'] ?? null;
            if ($contact && $event['tenant_id']) {
                $normalizeContact = function($c) {
                    if (empty($c)) return null;
                    $cleaned = preg_replace('/@.*$/', '', (string) $c);
                    return preg_replace('/[^0-9]/', '', $cleaned);
                };
                $normalized = $normalizeContact($contact);
                
                $convStmt = $db->prepare("
                    SELECT id, contact_external_id, tenant_id
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
                $convStmt->execute([$event['tenant_id'], $pattern1, $pattern2]);
                $conv = $convStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($conv) {
                    echo "  - Thread ID: whatsapp_{$conv['id']}\n";
                } else {
                    echo "  - Thread ID: NÃO ENCONTRADO\n";
                }
            }
            echo "\n";
        }
    }
}

echo "\n=== FIM DA VALIDAÇÃO ===\n";

