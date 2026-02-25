<?php
/**
 * Script de correção: Corrigir conversa duplicada do Paulo
 * 
 * A conversa ID 469 foi criada incorretamente como "não vinculada" (tenant_id=NULL, is_incoming_lead=1)
 * quando deveria estar vinculada ao tenant #7 (LAWINTER) que enviou a mensagem.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== CORREÇÃO: Conversa duplicada do Paulo ===\n\n";
    
    $pauloPhone = '554796517660';
    $conversationId = 469;
    $correctTenantId = 7; // LAWINTER
    
    // 1. Verificar estado atual da conversa
    echo "1. ESTADO ATUAL DA CONVERSA:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            id,
            conversation_key,
            contact_external_id,
            contact_name,
            channel_id,
            tenant_id,
            is_incoming_lead,
            created_at,
            last_message_at
        FROM conversations
        WHERE id = ?
    ");
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        echo "❌ Conversa ID $conversationId não encontrada\n";
        exit(1);
    }
    
    echo "Conversa ID {$conversation['id']}:\n";
    echo "  - contact_external_id: {$conversation['contact_external_id']}\n";
    echo "  - contact_name: " . ($conversation['contact_name'] ?: 'NULL') . "\n";
    echo "  - channel_id: " . ($conversation['channel_id'] ?: 'NULL') . "\n";
    echo "  - tenant_id: " . ($conversation['tenant_id'] ?: 'NULL') . " (INCORRETO - deveria ser 7)\n";
    echo "  - is_incoming_lead: {$conversation['is_incoming_lead']} (INCORRETO - deveria ser 0)\n";
    echo "  - created_at: {$conversation['created_at']}\n\n";
    
    // 2. Verificar se há outras conversas do Paulo
    echo "2. VERIFICAR OUTRAS CONVERSAS DO PAULO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT id, tenant_id, is_incoming_lead, created_at
        FROM conversations
        WHERE contact_external_id = ?
          AND id != ?
    ");
    $stmt->execute([$pauloPhone, $conversationId]);
    $otherConversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($otherConversations)) {
        echo "✅ Nenhuma outra conversa encontrada (sem duplicidade)\n\n";
    } else {
        echo "⚠️ Encontradas " . count($otherConversations) . " outras conversas:\n";
        foreach ($otherConversations as $other) {
            echo "  - ID {$other['id']}: tenant_id=" . ($other['tenant_id'] ?: 'NULL') . 
                 ", is_incoming_lead={$other['is_incoming_lead']}, created_at={$other['created_at']}\n";
        }
        echo "\n";
    }
    
    // 3. Aplicar correção
    echo "3. APLICANDO CORREÇÃO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $updateStmt = $db->prepare("
        UPDATE conversations
        SET tenant_id = ?,
            is_incoming_lead = 0,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $result = $updateStmt->execute([$correctTenantId, $conversationId]);
    
    if ($result) {
        echo "✅ Conversa atualizada com sucesso!\n";
        echo "  - tenant_id: NULL → 7 (LAWINTER)\n";
        echo "  - is_incoming_lead: 1 → 0\n\n";
    } else {
        echo "❌ Erro ao atualizar conversa\n";
        exit(1);
    }
    
    // 4. Verificar estado após correção
    echo "4. ESTADO APÓS CORREÇÃO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT 
            c.id,
            c.contact_external_id,
            c.contact_name,
            c.channel_id,
            c.tenant_id,
            t.name as tenant_name,
            c.is_incoming_lead,
            c.updated_at
        FROM conversations c
        LEFT JOIN tenants t ON c.tenant_id = t.id
        WHERE c.id = ?
    ");
    $stmt->execute([$conversationId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Conversa ID {$updated['id']}:\n";
    echo "  - contact_external_id: {$updated['contact_external_id']}\n";
    echo "  - contact_name: " . ($updated['contact_name'] ?: 'NULL') . "\n";
    echo "  - channel_id: " . ($updated['channel_id'] ?: 'NULL') . "\n";
    echo "  - tenant_id: {$updated['tenant_id']} ({$updated['tenant_name']})\n";
    echo "  - is_incoming_lead: {$updated['is_incoming_lead']}\n";
    echo "  - updated_at: {$updated['updated_at']}\n\n";
    
    // 5. Validação final
    echo "5. VALIDAÇÃO FINAL:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($updated['tenant_id'] == $correctTenantId && $updated['is_incoming_lead'] == 0) {
        echo "🎉 SUCESSO! Conversa corrigida corretamente!\n";
        echo "  ✅ tenant_id: {$updated['tenant_id']} (LAWINTER)\n";
        echo "  ✅ is_incoming_lead: 0 (não é lead)\n";
        echo "\nA conversa do Paulo agora aparecerá corretamente vinculada ao tenant LAWINTER.\n";
    } else {
        echo "❌ ERRO: Correção não foi aplicada corretamente\n";
        exit(1);
    }
    
    echo "\n=== FIM DA CORREÇÃO ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
