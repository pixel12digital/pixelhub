<?php
require_once 'vendor/autoload.php';

echo "=== TESTE SIMPLES: RASCUNHO DA PROPOSTA ===\n\n";

// Teste direto do AISuggestReplyService
echo "1. TESTE DIRETO DO SERVIÇO:\n";

try {
    $params = [
        'context_slug' => 'ecommerce',
        'objective' => 'send_proposal',
        'attendant_note' => 'Teste de proposta para Lidy sobre valores',
        'conversation_id' => null,
        'ai_chat_messages' => [],
        'user_prompt' => 'gere uma resposta inicial baseada no contexto',
        'thread_id' => null,
        'lead_id' => null,
        'contact_id' => null,
        'contact_name' => 'Lidy',
        'contact_phone' => '61999999999',
        'thread_messages' => []
    ];
    
    echo "Parâmetros:\n";
    echo "- Contexto: {$params['context_slug']}\n";
    echo "- Objetivo: {$params['objective']}\n";
    echo "- Nota: {$params['attendant_note']}\n";
    echo "- Nome: {$params['contact_name']}\n";
    
    // Chama o serviço
    $result = \PixelHub\Services\AISuggestReplyService::suggestChat($params);
    
    echo "\n=== RESULTADO ===\n";
    if ($result['success']) {
        echo "✅ SUCESSO!\n";
        echo "✅ Message: " . substr($result['message'], 0, 300) . "...\n";
        echo "✅ Context: " . ($result['context_used'] ?? 'N/A') . "\n";
        echo "✅ Learned examples: " . ($result['learned_examples_count'] ?? 0) . "\n";
        
        // Verifica se a mensagem contém elementos esperados
        $message = $result['message'];
        if (strpos($message, 'R$ 197') !== false) {
            echo "✅ Contém valor R$ 197\n";
        } else {
            echo "❌ Não contém valor R$ 197\n";
        }
        
        if (strpos($message, '12x') !== false) {
            echo "✅ Contém 12x\n";
        } else {
            echo "❌ Não contém 12x\n";
        }
        
        if (strpos($message, 'entrada') !== false) {
            echo "✅ Contém entrada\n";
        } else {
            echo "❌ Não contém entrada\n";
        }
        
    } else {
        echo "❌ ERRO: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEÇÃO: " . $e->getMessage() . "\n";
    echo "❌ Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== CONCLUSÃO ===\n";
echo "Se este teste funcionar, o problema está na autenticação do endpoint.\n";
echo "Se este teste falhar, o problema está no serviço ou na configuração.\n";

?>
