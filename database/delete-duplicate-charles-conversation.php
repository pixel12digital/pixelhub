<?php

/**
 * Script para deletar a conversa duplicada do Charles Dietrich
 * 
 * Deleta a conversa ID 3 (número incorreto) já que a ID 2 (número correto) já existe
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== DELETANDO CONVERSA DUPLICADA DO CHARLES DIETRICH ===\n\n";

$duplicateConversationId = 3; // Número incorreto
$correctConversationId = 2; // Número correto

// 1. Verifica conversa duplicada antes de deletar
echo "1. Verificando conversa duplicada (ID {$duplicateConversationId})...\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        message_count,
        created_at
    FROM conversations
    WHERE id = ?
");
$stmt->execute([$duplicateConversationId]);
$duplicate = $stmt->fetch();

if (!$duplicate) {
    echo "   ❌ Conversa duplicada não encontrada!\n";
    exit(1);
}

echo "   Conversa duplicada encontrada:\n";
echo "     - ID: {$duplicate['id']}\n";
echo "     - Key: {$duplicate['conversation_key']}\n";
echo "     - Contact: {$duplicate['contact_external_id']}\n";
echo "     - Nome: " . ($duplicate['contact_name'] ?: 'NULL') . "\n";
echo "     - Mensagens: {$duplicate['message_count']}\n";
echo "     - Criada em: {$duplicate['created_at']}\n\n";

// 2. Verifica conversa correta
echo "2. Verificando conversa correta (ID {$correctConversationId})...\n";
$stmt->execute([$correctConversationId]);
$correct = $stmt->fetch();

if (!$correct) {
    echo "   ❌ Conversa correta não encontrada! Não é seguro deletar.\n";
    exit(1);
}

echo "   Conversa correta encontrada:\n";
echo "     - ID: {$correct['id']}\n";
echo "     - Key: {$correct['conversation_key']}\n";
echo "     - Contact: {$correct['contact_external_id']}\n";
echo "     - Nome: " . ($correct['contact_name'] ?: 'NULL') . "\n";
echo "     - Mensagens: {$correct['message_count']}\n";
echo "     - Criada em: {$correct['created_at']}\n\n";

// 3. Confirmação
echo "3. DELETANDO conversa duplicada (ID {$duplicateConversationId})...\n";
echo "   ⚠️  Esta ação não pode ser desfeita!\n";
echo "   As mensagens estão nos eventos (communication_events), não serão perdidas.\n\n";

try {
    $db->beginTransaction();
    
    $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
    $deleteStmt->execute([$duplicateConversationId]);
    
    $rowsAffected = $deleteStmt->rowCount();
    
    if ($rowsAffected > 0) {
        $db->commit();
        echo "   ✅ Conversa duplicada deletada com sucesso!\n\n";
        
        // 4. Verifica resultado
        echo "4. Verificando resultado...\n";
        $verifyStmt = $db->prepare("SELECT COUNT(*) FROM conversations WHERE id = ?");
        $verifyStmt->execute([$duplicateConversationId]);
        $stillExists = $verifyStmt->fetchColumn() > 0;
        
        if (!$stillExists) {
            echo "   ✅ Conversa ID {$duplicateConversationId} não existe mais.\n";
            echo "   ✅ Conversa ID {$correctConversationId} (número correto) permanece ativa.\n";
            echo "\n   Agora o número correto (554796164699) será exibido na interface!\n";
        } else {
            echo "   ⚠️  Aviso: Conversa ainda existe após tentativa de deletar.\n";
        }
    } else {
        $db->rollBack();
        echo "   ⚠️  Nenhuma linha foi deletada.\n";
    }
    
} catch (\Exception $e) {
    $db->rollBack();
    echo "   ❌ Erro ao deletar conversa: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

