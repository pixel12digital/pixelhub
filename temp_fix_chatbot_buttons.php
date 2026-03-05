<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "=== CORRIGINDO BOTÕES DO FLUXO 'QUERO CONHECER' ===\n\n";

// Corrige os botões para apontar para os fluxos corretos
$correctButtons = json_encode([
    ['text' => 'Sou autônomo', 'flow_id' => 3],
    ['text' => 'Trabalho em imobiliária', 'flow_id' => 4]
]);

$stmt = $db->prepare("UPDATE chatbot_flows SET next_buttons = ? WHERE id = 1");
$result = $stmt->execute([$correctButtons]);

if ($result) {
    echo "✅ Botões corrigidos com sucesso!\n\n";
    
    // Verifica a correção
    $flow = $db->query("SELECT id, name, next_buttons FROM chatbot_flows WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    echo "Botões atualizados:\n";
    echo json_encode(json_decode($flow['next_buttons'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "❌ Erro ao atualizar botões\n";
}

echo "\n\n=== VERIFICANDO FOLLOW-UP (Vou analisar primeiro) ===\n\n";

// Verifica se existe fluxo para "Vou analisar primeiro"
$followUpFlow = $db->query("SELECT * FROM chatbot_flows WHERE trigger_value = 'Vou analisar primeiro'")->fetch(PDO::FETCH_ASSOC);

if ($followUpFlow) {
    echo "✅ Fluxo 'Vou analisar primeiro' existe (ID: " . $followUpFlow['id'] . ")\n";
    echo "Resposta: " . $followUpFlow['response_message'] . "\n";
} else {
    echo "❌ Fluxo 'Vou analisar primeiro' NÃO existe\n";
}

echo "\n\n=== VERIFICANDO TABELA scheduled_messages ===\n\n";

// Verifica estrutura da tabela
$columns = $db->query("DESCRIBE scheduled_messages")->fetchAll(PDO::FETCH_COLUMN);
echo "Colunas: " . implode(', ', $columns) . "\n";
