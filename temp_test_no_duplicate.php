<?php
/**
 * Teste: Garantir que não há duplicação ao enviar mensagens
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';
require_once __DIR__ . '/src/Services/ConversationService.php';

use PixelHub\Core\DB;

try {
    $db = DB::getConnection();
    
    echo "=== TESTE: Garantir que não há duplicação ===\n\n";
    
    $testPhone = '554799999999'; // Número de teste
    $testTenantId = 7; // LAWINTER
    
    // Limpar conversas de teste anteriores
    $db->prepare("DELETE FROM conversations WHERE contact_external_id = ?")->execute([$testPhone]);
    
    // 1. Criar conversa inicial (simular que Paulo já tem uma conversa com Authentic)
    echo "1. CRIAR CONVERSA INICIAL (Paulo vinculado à Authentic):\n";
    echo str_repeat("-", 80) . "\n";
    
    $authenticTenantId = 2; // Supondo que Authentic seja tenant ID 2
    
    $initialConv = \PixelHub\Services\ConversationService::resolveConversation([
        'event_type' => 'whatsapp.inbound.message',
        'source_system' => 'wpp_gateway',
        'tenant_id' => $authenticTenantId,
        'payload' => [
            'from' => $testPhone,
            'text' => 'Olá, sou o Paulo',
            'timestamp' => time()
        ],
        'metadata' => [
            'channel_id' => 'pixel12digital'
        ]
    ]);
    
    if ($initialConv) {
        echo "✅ Conversa inicial criada:\n";
        echo "  - ID: {$initialConv['id']}\n";
        echo "  - tenant_id: {$initialConv['tenant_id']} (Authentic)\n";
        echo "  - contact_external_id: {$initialConv['contact_external_id']}\n\n";
    } else {
        echo "❌ Falha ao criar conversa inicial\n";
        exit(1);
    }
    
    // 2. Enviar mensagem outbound de outro tenant (LAWINTER) para o mesmo número
    echo "2. ENVIAR MENSAGEM OUTBOUND DE OUTRO TENANT (LAWINTER):\n";
    echo str_repeat("-", 80) . "\n";
    
    $outboundConv = \PixelHub\Services\ConversationService::resolveConversation([
        'event_type' => 'whatsapp.outbound.message',
        'source_system' => 'pixelhub_operator',
        'tenant_id' => $testTenantId, // LAWINTER
        'payload' => [
            'to' => $testPhone,
            'text' => 'Bom dia, Paulo!',
            'timestamp' => time()
        ],
        'metadata' => [
            'sent_by' => 1,
            'sent_by_name' => 'Admin Master',
            'channel_id' => 'pixel12digital'
        ]
    ]);
    
    if ($outboundConv) {
        echo "✅ Conversa retornada após envio outbound:\n";
        echo "  - ID: {$outboundConv['id']}\n";
        echo "  - tenant_id: {$outboundConv['tenant_id']}\n";
        echo "  - contact_external_id: {$outboundConv['contact_external_id']}\n\n";
    } else {
        echo "❌ Falha ao resolver conversa outbound\n";
        exit(1);
    }
    
    // 3. Verificar se há duplicação
    echo "3. VERIFICAR DUPLICAÇÃO:\n";
    echo str_repeat("-", 80) . "\n";
    
    $stmt = $db->prepare("
        SELECT id, tenant_id, is_incoming_lead, created_at
        FROM conversations
        WHERE contact_external_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$testPhone]);
    $allConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total de conversas para o número: " . count($allConvs) . "\n\n";
    
    if (count($allConvs) > 1) {
        echo "❌ FALHA: Duplicação detectada!\n\n";
        foreach ($allConvs as $conv) {
            echo "  - ID {$conv['id']}: tenant_id={$conv['tenant_id']}, created_at={$conv['created_at']}\n";
        }
        echo "\n";
        exit(1);
    } else {
        echo "✅ SUCESSO: Apenas 1 conversa (sem duplicação)\n";
        echo "  - ID: {$allConvs[0]['id']}\n";
        echo "  - tenant_id: {$allConvs[0]['tenant_id']}\n\n";
    }
    
    // 4. Validar que a conversa foi mantida (não criou nova)
    echo "4. VALIDAÇÃO:\n";
    echo str_repeat("-", 80) . "\n";
    
    if ($initialConv['id'] == $outboundConv['id']) {
        echo "✅ PERFEITO! A mesma conversa foi reutilizada\n";
        echo "  - Conversa inicial ID: {$initialConv['id']}\n";
        echo "  - Conversa após outbound ID: {$outboundConv['id']}\n";
        echo "  - São a MESMA conversa (sem duplicação)\n\n";
    } else {
        echo "❌ ERRO: Conversas diferentes!\n";
        echo "  - Conversa inicial ID: {$initialConv['id']}\n";
        echo "  - Conversa após outbound ID: {$outboundConv['id']}\n";
        echo "  - Criou uma NOVA conversa (duplicação)\n\n";
        exit(1);
    }
    
    // 5. Limpar teste
    echo "5. LIMPEZA:\n";
    echo str_repeat("-", 80) . "\n";
    $db->prepare("DELETE FROM conversations WHERE contact_external_id = ?")->execute([$testPhone]);
    echo "✅ Conversas de teste deletadas\n\n";
    
    echo "=== TESTE CONCLUÍDO COM SUCESSO ===\n";
    echo "✅ Não há duplicação ao enviar mensagens outbound\n";
    echo "✅ Conversas existentes são sempre reutilizadas\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
