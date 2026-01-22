<?php

/**
 * Script para validar mapeamento de thread_id
 * 
 * Uso: php database/validate-thread-mapping.php
 * 
 * Compara:
 * 1. Thread_id retornado nas mensagens (4699/4223) vs thread_id que o frontend está abrindo (ex: whatsapp_34)
 * 2. Se divergir, identifica a causa raiz
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

echo "=== VALIDAÇÃO DE MAPEAMENTO DE THREAD_ID ===\n\n";

// Thread IDs que o frontend está usando (exemplo)
$frontendThreadIds = ['whatsapp_34', 'whatsapp_35'];

echo "Thread IDs do Frontend: " . implode(', ', $frontendThreadIds) . "\n\n";

foreach ($frontendThreadIds as $threadId) {
    echo "--- Analisando Thread: {$threadId} ---\n";
    
    // Extrai conversation_id do thread_id
    if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
        $conversationId = (int) $matches[1];
        
        // Busca conversation
        $stmt = $db->prepare("
            SELECT id, contact_external_id, tenant_id, channel_id, last_message_at
            FROM conversations
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conv) {
            echo "❌ Conversation ID {$conversationId} NÃO ENCONTRADA\n\n";
            continue;
        }
        
        echo "✅ Conversation encontrada:\n";
        echo "  - ID: {$conv['id']}\n";
        echo "  - Contact External ID: {$conv['contact_external_id']}\n";
        echo "  - Tenant ID: {$conv['tenant_id']}\n";
        echo "  - Channel ID: " . ($conv['channel_id'] ?? 'NULL') . "\n";
        echo "  - Last Message At: " . ($conv['last_message_at'] ?? 'NULL') . "\n\n";
        
        // Busca mensagens relacionadas a esta conversation
        $normalizeContact = function($c) {
            if (empty($c)) return null;
            $cleaned = preg_replace('/@.*$/', '', (string) $c);
            return preg_replace('/[^0-9]/', '', $cleaned);
        };
        $normalizedContact = $normalizeContact($conv['contact_external_id']);
        
        if (empty($normalizedContact)) {
            echo "⚠️  Contact External ID não pode ser normalizado\n\n";
            continue;
        }
        
        // Busca mensagens recentes desta conversation
        $stmt = $db->prepare("
            SELECT 
                ce.id,
                ce.event_id,
                ce.event_type,
                ce.created_at,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_contact,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) as to_contact,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from,
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) as message_to
            FROM communication_events ce
            WHERE ce.tenant_id = ?
              AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
              AND (
                  JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                  OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
              )
            ORDER BY ce.created_at DESC
            LIMIT 10
        ");
        $pattern = "%{$normalizedContact}%";
        $stmt->execute([$conv['tenant_id'], $pattern, $pattern, $pattern, $pattern]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "📨 Mensagens encontradas: " . count($messages) . "\n";
        
        if (empty($messages)) {
            echo "⚠️  NENHUMA MENSAGEM ENCONTRADA para esta conversation\n";
            echo "   - Verifique se o contact_external_id está correto\n";
            echo "   - Verifique se há mensagens no período esperado\n\n";
        } else {
            echo "   Últimas mensagens:\n";
            foreach ($messages as $msg) {
                $contact = $msg['from_contact'] ?? $msg['message_from'] ?? $msg['to_contact'] ?? $msg['message_to'] ?? 'NULL';
                echo "   - Event ID: {$msg['event_id']}, Created: {$msg['created_at']}, Contact: {$contact}\n";
            }
            echo "\n";
        }
        
        // Verifica se há outras conversations com o mesmo contact
        $stmt = $db->prepare("
            SELECT id, contact_external_id, tenant_id, last_message_at
            FROM conversations
            WHERE tenant_id = ?
              AND id != ?
              AND (
                  contact_external_id LIKE ?
                  OR contact_external_id LIKE ?
              )
            ORDER BY last_message_at DESC
        ");
        $pattern1 = "%{$normalizedContact}%";
        $pattern2 = "%{$conv['contact_external_id']}%";
        $stmt->execute([$conv['tenant_id'], $conversationId, $pattern1, $pattern2]);
        $duplicateConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($duplicateConvs)) {
            echo "⚠️  ATENÇÃO: Existem outras conversations com o mesmo contato:\n";
            foreach ($duplicateConvs as $dup) {
                echo "   - Conversation ID: {$dup['id']} (Thread: whatsapp_{$dup['id']})\n";
                echo "     Contact: {$dup['contact_external_id']}\n";
                echo "     Last Message: " . ($dup['last_message_at'] ?? 'NULL') . "\n";
            }
            echo "\n";
        }
        
    } else {
        echo "❌ Formato de thread_id inválido: {$threadId}\n\n";
    }
}

echo "\n=== FIM DA VALIDAÇÃO ===\n";

