<?php
/**
 * Script de teste: Verificar se a correção de mensagens outbound funciona
 * 
 * Simula o fluxo de criação de conversa para mensagem outbound
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Services/ConversationService.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== TESTE: Correção de mensagens OUTBOUND ===\n\n";
    
    // Simular dados de um evento outbound (como o que foi enviado para o Paulo)
    $testEventData = [
        'event_type' => 'whatsapp.outbound.message',
        'source_system' => 'pixelhub_operator',
        'tenant_id' => 7, // LAWINTER
        'payload' => [
            'to' => '554796517999', // Número de teste (não existe no banco)
            'text' => 'Mensagem de teste',
            'timestamp' => time()
        ],
        'metadata' => [
            'sent_by' => 1,
            'sent_by_name' => 'Admin Master',
            'channel_id' => 'pixel12digital'
        ]
    ];
    
    $testChannelInfo = [
        'channel_type' => 'whatsapp',
        'channel_id' => 'pixel12digital',
        'channel_account_id' => 3,
        'contact_external_id' => '554796517999',
        'contact_name' => null,
        'direction' => 'outbound'
    ];
    
    echo "1. TESTE: Criar conversa para mensagem OUTBOUND\n";
    echo str_repeat("-", 80) . "\n";
    echo "Event data:\n";
    echo "  - event_type: {$testEventData['event_type']}\n";
    echo "  - tenant_id: {$testEventData['tenant_id']}\n";
    echo "  - to: {$testEventData['payload']['to']}\n";
    echo "  - direction: {$testChannelInfo['direction']}\n\n";
    
    // Verificar se já existe conversa com este número
    $stmt = $db->prepare("
        SELECT id, tenant_id, is_incoming_lead, contact_external_id
        FROM conversations
        WHERE contact_external_id = ?
    ");
    $stmt->execute(['554796517999']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "⚠️ Conversa já existe (ID {$existing['id']})\n";
        echo "  - tenant_id: " . ($existing['tenant_id'] ?: 'NULL') . "\n";
        echo "  - is_incoming_lead: {$existing['is_incoming_lead']}\n\n";
        
        // Deletar para teste limpo
        echo "Deletando conversa existente para teste limpo...\n";
        $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
        $deleteStmt->execute([$existing['id']]);
        echo "✅ Conversa deletada\n\n";
    }
    
    // Testar criação de conversa via ConversationService
    echo "2. CHAMANDO ConversationService::resolveConversation()...\n";
    echo str_repeat("-", 80) . "\n";
    
    $conversation = \PixelHub\Services\ConversationService::resolveConversation([
        'event_type' => $testEventData['event_type'],
        'source_system' => $testEventData['source_system'],
        'tenant_id' => $testEventData['tenant_id'],
        'payload' => $testEventData['payload'],
        'metadata' => $testEventData['metadata']
    ]);
    
    if ($conversation) {
        echo "✅ Conversa criada/encontrada:\n";
        echo "  - ID: {$conversation['id']}\n";
        echo "  - tenant_id: " . ($conversation['tenant_id'] ?: 'NULL') . "\n";
        echo "  - is_incoming_lead: {$conversation['is_incoming_lead']}\n";
        echo "  - contact_external_id: {$conversation['contact_external_id']}\n";
        echo "  - channel_id: " . ($conversation['channel_id'] ?: 'NULL') . "\n\n";
        
        // Verificar se está correto
        echo "3. VALIDAÇÃO:\n";
        echo str_repeat("-", 80) . "\n";
        
        $errors = [];
        
        if ($conversation['tenant_id'] != 7) {
            $errors[] = "❌ ERRO: tenant_id deveria ser 7 (LAWINTER), mas é " . ($conversation['tenant_id'] ?: 'NULL');
        } else {
            echo "✅ tenant_id correto: 7 (LAWINTER)\n";
        }
        
        if ($conversation['is_incoming_lead'] != 0) {
            $errors[] = "❌ ERRO: is_incoming_lead deveria ser 0 (não é lead), mas é {$conversation['is_incoming_lead']}";
        } else {
            echo "✅ is_incoming_lead correto: 0 (não é lead)\n";
        }
        
        if ($conversation['channel_id'] != 'pixel12digital') {
            $errors[] = "❌ ERRO: channel_id deveria ser 'pixel12digital', mas é " . ($conversation['channel_id'] ?: 'NULL');
        } else {
            echo "✅ channel_id correto: pixel12digital\n";
        }
        
        if (!empty($errors)) {
            echo "\n⚠️ PROBLEMAS ENCONTRADOS:\n";
            foreach ($errors as $error) {
                echo "  $error\n";
            }
        } else {
            echo "\n🎉 SUCESSO! Todos os campos estão corretos!\n";
        }
        
        // Limpar teste
        echo "\n4. LIMPEZA:\n";
        echo str_repeat("-", 80) . "\n";
        $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
        $deleteStmt->execute([$conversation['id']]);
        echo "✅ Conversa de teste deletada (ID {$conversation['id']})\n";
        
    } else {
        echo "❌ ERRO: ConversationService::resolveConversation() retornou NULL\n";
    }
    
    echo "\n=== FIM DO TESTE ===\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
