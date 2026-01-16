<?php

/**
 * Script para executar a correção do número do Charles Dietrich
 * 
 * Número correto: 554796164699
 * Número incorreto: 554797146908
 * Conversa ID 3 precisa ser corrigida
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== EXECUTANDO CORREÇÃO DO NÚMERO DO CHARLES DIETRICH ===\n\n";

$correctPhone = '554796164699';
$wrongPhone = '554797146908';
$conversationId = 3;

// 1. Verifica conversa antes da correção
echo "1. Verificando conversa ID {$conversationId} antes da correção...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        message_count
    FROM conversations
    WHERE id = ?
");
$stmt->execute([$conversationId]);
$conversation = $stmt->fetch();

if (!$conversation) {
    echo "   ❌ Conversa não encontrada!\n";
    exit(1);
}

echo "   Conversa encontrada:\n";
echo "     - ID: {$conversation['id']}\n";
echo "     - Key: {$conversation['conversation_key']}\n";
echo "     - Contact: {$conversation['contact_external_id']}\n";
echo "     - Nome: " . ($conversation['contact_name'] ?: 'NULL') . "\n";
echo "     - Tenant: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
echo "     - Mensagens: {$conversation['message_count']}\n\n";

// 2. Verifica se precisa corrigir
if ($conversation['contact_external_id'] === $correctPhone) {
    echo "2. ✅ Conversa já está com o número correto. Nenhuma correção necessária.\n";
    exit(0);
}

// 3. Prepara nova conversation_key
$oldKey = $conversation['conversation_key'];
$newKey = preg_replace('/' . preg_quote($wrongPhone, '/') . '/', $correctPhone, $oldKey);
if ($newKey === $oldKey) {
    $tenantId = $conversation['tenant_id'] ?? 0;
    $newKey = "whatsapp_{$tenantId}_{$correctPhone}";
}

echo "2. Aplicando correção...\n";
echo "   - contact_external_id: '{$conversation['contact_external_id']}' → '{$correctPhone}'\n";
if ($oldKey !== $newKey) {
    echo "   - conversation_key: '{$oldKey}' → '{$newKey}'\n";
}

// 4. Executa correção
try {
    $db->beginTransaction();
    
    $updateStmt = $db->prepare("
        UPDATE conversations 
        SET 
            contact_external_id = ?,
            conversation_key = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $updateStmt->execute([
        $correctPhone,
        $newKey,
        $conversationId
    ]);
    
    $db->commit();
    
    echo "\n3. ✅ Correção aplicada com sucesso!\n\n";
    
    // 5. Verifica resultado
    echo "4. Verificando resultado...\n";
    $verifyStmt = $db->prepare("
        SELECT 
            id,
            conversation_key,
            contact_external_id,
            contact_name
        FROM conversations
        WHERE id = ?
    ");
    $verifyStmt->execute([$conversationId]);
    $updated = $verifyStmt->fetch();
    
    if ($updated) {
        echo "   Conversa atualizada:\n";
        echo "     - ID: {$updated['id']}\n";
        echo "     - Key: {$updated['conversation_key']}\n";
        echo "     - Contact: {$updated['contact_external_id']}\n";
        echo "     - Nome: " . ($updated['contact_name'] ?: 'NULL') . "\n";
        
        if ($updated['contact_external_id'] === $correctPhone) {
            echo "\n   ✅ Número corrigido com sucesso!\n";
        } else {
            echo "\n   ⚠️  Aviso: Número ainda não está correto após atualização.\n";
        }
    }
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "\n   ❌ Erro ao aplicar correção: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

