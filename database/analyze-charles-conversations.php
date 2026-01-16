<?php

/**
 * Script para analisar as duas conversas do Charles Dietrich
 */

require_once __DIR__ . '/../src/Core/DB.php';
require_once __DIR__ . '/../src/Core/Env.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();

$db = DB::getConnection();

echo "=== ANÁLISE DAS CONVERSAS DO CHARLES DIETRICH ===\n\n";

$conversationId1 = 2; // Número correto
$conversationId2 = 3; // Número incorreto

// Verifica conversa 1 (correta)
echo "1. Conversa ID {$conversationId1} (número correto):\n";
$stmt = $db->prepare("
    SELECT 
        id,
        conversation_key,
        contact_external_id,
        contact_name,
        tenant_id,
        message_count,
        unread_count,
        last_message_at,
        created_at
    FROM conversations
    WHERE id = ?
");
$stmt->execute([$conversationId1]);
$conv1 = $stmt->fetch();

if ($conv1) {
    echo "   - Key: {$conv1['conversation_key']}\n";
    echo "   - Contact: {$conv1['contact_external_id']}\n";
    echo "   - Nome: " . ($conv1['contact_name'] ?: 'NULL') . "\n";
    echo "   - Tenant: " . ($conv1['tenant_id'] ?: 'NULL') . "\n";
    echo "   - Mensagens: {$conv1['message_count']}\n";
    echo "   - Não lidas: {$conv1['unread_count']}\n";
    echo "   - Última mensagem: " . ($conv1['last_message_at'] ?: 'NULL') . "\n";
    echo "   - Criada em: {$conv1['created_at']}\n";
    
    // Conta mensagens reais (se a tabela existir)
    try {
        $msgStmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM communication_messages
            WHERE conversation_id = ?
        ");
        $msgStmt->execute([$conversationId1]);
        $msgCount1 = $msgStmt->fetchColumn();
        echo "   - Mensagens na tabela communication_messages: {$msgCount1}\n";
    } catch (\Exception $e) {
        $msgCount1 = 0;
        echo "   - Tabela communication_messages não existe\n";
    }
} else {
    echo "   ❌ Conversa não encontrada!\n";
}

echo "\n";

// Verifica conversa 2 (incorreta)
echo "2. Conversa ID {$conversationId2} (número incorreto):\n";
$stmt->execute([$conversationId2]);
$conv2 = $stmt->fetch();

if ($conv2) {
    echo "   - Key: {$conv2['conversation_key']}\n";
    echo "   - Contact: {$conv2['contact_external_id']}\n";
    echo "   - Nome: " . ($conv2['contact_name'] ?: 'NULL') . "\n";
    echo "   - Tenant: " . ($conv2['tenant_id'] ?: 'NULL') . "\n";
    echo "   - Mensagens: {$conv2['message_count']}\n";
    echo "   - Não lidas: {$conv2['unread_count']}\n";
    echo "   - Última mensagem: " . ($conv2['last_message_at'] ?: 'NULL') . "\n";
    echo "   - Criada em: {$conv2['created_at']}\n";
    
    // Conta mensagens reais (se a tabela existir)
    try {
        $msgStmt2 = $db->prepare("
            SELECT COUNT(*) as total
            FROM communication_messages
            WHERE conversation_id = ?
        ");
        $msgStmt2->execute([$conversationId2]);
        $msgCount2 = $msgStmt2->fetchColumn();
        echo "   - Mensagens na tabela communication_messages: {$msgCount2}\n";
    } catch (\Exception $e) {
        $msgCount2 = 0;
        echo "   - Tabela communication_messages não existe\n";
    }
} else {
    echo "   ❌ Conversa não encontrada!\n";
}

echo "\n";

// Verifica eventos relacionados
echo "3. Eventos de comunicação:\n";
$eventStmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN JSON_EXTRACT(payload, '$.from') LIKE '%554796164699%' 
                  OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554796164699%' 
            THEN 1 ELSE 0 END) as correct_from,
        SUM(CASE WHEN JSON_EXTRACT(payload, '$.from') LIKE '%554797146908%' 
                  OR JSON_EXTRACT(payload, '$.message.from') LIKE '%554797146908%' 
            THEN 1 ELSE 0 END) as wrong_from
    FROM communication_events
    WHERE event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
");
$eventStats = $eventStmt->fetch();
echo "   - Total de eventos: {$eventStats['total']}\n";
echo "   - Eventos com número correto (554796164699): {$eventStats['correct_from']}\n";
echo "   - Eventos com número incorreto (554797146908): {$eventStats['wrong_from']}\n";

echo "\n";

// Recomendação
echo "4. RECOMENDAÇÃO:\n";
if ($conv1 && $conv2) {
    $count1 = (int)$conv1['message_count'];
    $count2 = (int)$conv2['message_count'];
    
    if ($count1 > 0 && $count2 > 0) {
        echo "   ⚠️  Ambas as conversas têm mensagens (ID {$conversationId1}: {$count1}, ID {$conversationId2}: {$count2}).\n";
        echo "   Sugestão: Como a conversa ID {$conversationId1} já existe com o número correto, deletar a duplicada ID {$conversationId2}.\n";
        echo "   (As mensagens estão nos eventos, não serão perdidas)\n";
    } elseif ($count1 > 0 && $count2 == 0) {
        echo "   ✅ A conversa correta (ID {$conversationId1}) tem {$count1} mensagens, a incorreta (ID {$conversationId2}) não tem.\n";
        echo "   Sugestão: Deletar a conversa ID {$conversationId2} (duplicada sem mensagens).\n";
    } else {
        echo "   ✅ A conversa correta (ID {$conversationId1}) já existe.\n";
        echo "   Sugestão: Deletar a conversa ID {$conversationId2} (duplicada com número incorreto).\n";
    }
}

echo "\n";

