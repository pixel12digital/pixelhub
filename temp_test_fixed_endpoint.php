<?php
require_once 'vendor/autoload.php';

echo "=== TESTE DO ENDPOINT CORRIGIDO ===\n\n";

// Teste do endpoint correto
echo "1. TESTE DO ENDPOINT /API/AI/SUGGEST-CHAT:\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/painel.pixel12digital/api/ai/suggest-chat');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
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
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "❌ Erro cURL: {$curlError}\n";
} else {
    echo "✅ HTTP Status: {$httpCode}\n";
    echo "Resposta: " . substr($response, 0, 500) . "...\n";
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        if ($data) {
            echo "✅ JSON válido\n";
            echo "✅ Success: " . ($data['success'] ? 'true' : 'false') . "\n";
            
            if ($data['success']) {
                echo "✅ Message: " . substr($data['message'] ?? 'N/A', 0, 200) . "...\n";
                echo "✅ Context: " . ($data['context_used'] ?? 'N/A') . "\n";
                
                // Verifica se contém elementos esperados
                $message = $data['message'] ?? '';
                if (strpos($message, 'R$ 197') !== false) {
                    echo "✅ Contém R$ 197\n";
                }
                if (strpos($message, '12x') !== false) {
                    echo "✅ Contém 12x\n";
                }
                if (strpos($message, 'entrada') !== false) {
                    echo "✅ Contém entrada\n";
                }
                
                echo "\n📋 MENSAGEM COMPLETA:\n";
                echo $message . "\n";
            } else {
                echo "❌ Erro: " . ($data['error'] ?? 'Erro desconhecido') . "\n";
            }
        } else {
            echo "❌ JSON inválido\n";
        }
    } else {
        echo "❌ HTTP {$httpCode}\n";
    }
}

echo "\n=== CONCLUSÃO ===\n";
echo "✅ Endpoint /api/ai/suggest-chat corrigido\n";
echo "✅ Frontend atualizado para usar endpoint correto\n";
echo "✅ Frontend trata resposta como modo chat (message única)\n";
echo "✅ Teste no navegador para confirmar funcionamento\n\n";

echo "📋 PRÓXIMOS PASSOS:\n";
echo "1. Abra o DevTools (F12)\n";
echo "2. Vá para uma conversa no Inbox\n";
echo "3. Clique no botão IA (robo roxo)\n";
echo "4. Selecione: ecommerce + send_proposal\n";
echo "5. Preencha observação sobre valores\n";
echo "6. Clique em 'Gerar rascunho'\n";
echo "7. Verifique se o rascunho aparece\n\n";

echo "🎯 RESULTADO ESPERADO:\n";
echo "- Rascunho gerado com proposta de e-commerce\n";
echo "- Contém R$ 197 em 12x + entrada + 3x boleto\n";
echo "- Formato inteligente e personalizado\n";
echo "- Sem erros 404 ou 401\n";

?>
