<?php
/**
 * Teste rápido para verificar se a API IA está funcionando
 */

// Simula requisição POST para /api/ai/chat
$requestData = [
    'context_slug' => 'ecommerce',
    'objective' => 'follow_up',
    'attendant_note' => 'Teste de integração',
    'opportunity_id' => 6,
    'contact_name' => 'Test Lead',
    'contact_phone' => '11999999999',
    'ai_chat_messages' => []
];

echo "=== Teste da API IA ===\n";
echo "Dados da requisição:\n";
echo json_encode($requestData, JSON_PRETTY_PRINT) . "\n\n";

// Verifica se as classes estão carregando
echo "Verificando classes:\n";

try {
    if (class_exists('PixelHub\Services\OpportunityService')) {
        echo "✓ OpportunityService carregada\n";
        echo "  STAGES: " . json_encode(\PixelHub\Services\OpportunityService::STAGES) . "\n";
    } else {
        echo "✗ OpportunityService não encontrada\n";
    }
} catch (Exception $e) {
    echo "✗ Erro ao carregar OpportunityService: " . $e->getMessage() . "\n";
}

try {
    if (class_exists('PixelHub\Services\AISuggestReplyService')) {
        echo "✓ AISuggestReplyService carregada\n";
    } else {
        echo "✗ AISuggestReplyService não encontrada\n";
    }
} catch (Exception $e) {
    echo "✗ Erro ao carregar AISuggestReplyService: " . $e->getMessage() . "\n";
}

echo "\n=== Teste concluído ===\n";
