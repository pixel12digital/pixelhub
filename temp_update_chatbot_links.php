<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "=== ATUALIZANDO LINKS DOS FLUXOS ===\n\n";

// Fluxo 3: Sou autônomo
$messageAutonomo = "Perfeito! Desenvolvemos uma estrutura que ajuda corretores autônomos a captarem e organizarem leads de imóveis através de um site próprio integrado com WhatsApp.

Conheça mais em: https://imobsites.com.br/

Gostaria de agendar uma demonstração personalizada ou prefere analisar primeiro?";

$stmt = $db->prepare("UPDATE chatbot_flows SET response_message = ? WHERE id = 3");
$result1 = $stmt->execute([$messageAutonomo]);

echo "Fluxo 3 (Sou autônomo): " . ($result1 ? "✅ Atualizado" : "❌ Erro") . "\n";

// Fluxo 4: Trabalho em imobiliária
$messageImobiliaria = "Excelente! Desenvolvemos uma estrutura que ajuda imobiliárias a gerenciarem leads e equipes através de um site próprio integrado com WhatsApp.

Conheça mais em: https://imobsites.com.br/

Gostaria de agendar uma demonstração personalizada ou prefere analisar primeiro?";

$stmt = $db->prepare("UPDATE chatbot_flows SET response_message = ? WHERE id = 4");
$result2 = $stmt->execute([$messageImobiliaria]);

echo "Fluxo 4 (Trabalho em imobiliária): " . ($result2 ? "✅ Atualizado" : "❌ Erro") . "\n";

echo "\n=== VERIFICANDO ATUALIZAÇÕES ===\n\n";

$flows = $db->query("SELECT id, name, response_message FROM chatbot_flows WHERE id IN (3, 4)")->fetchAll(PDO::FETCH_ASSOC);

foreach ($flows as $flow) {
    echo "ID {$flow['id']}: {$flow['name']}\n";
    echo "Mensagem:\n{$flow['response_message']}\n\n";
}

echo "\n=== VERIFICANDO BOTÕES DOS FLUXOS 3 E 4 ===\n\n";

// Agora os fluxos 3 e 4 também precisam ter botões para "Agendar demonstração" e "Vou analisar primeiro"
$nextButtons = json_encode([
    ['text' => 'Quero agendar demonstração', 'flow_id' => 5],
    ['text' => 'Vou analisar primeiro', 'flow_id' => 6]
]);

$stmt = $db->prepare("UPDATE chatbot_flows SET next_buttons = ? WHERE id IN (3, 4)");
$result3 = $stmt->execute([$nextButtons]);

echo "Botões dos fluxos 3 e 4: " . ($result3 ? "✅ Configurados" : "❌ Erro") . "\n";
echo "Botões: Quero agendar demonstração (→ Fluxo 5) | Vou analisar primeiro (→ Fluxo 6)\n";
