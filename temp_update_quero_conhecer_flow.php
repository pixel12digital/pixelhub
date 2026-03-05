<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "=== ATUALIZANDO FLUXO 'QUERO CONHECER' ===\n\n";

// Nova mensagem conforme especificação
$newMessage = "Perfeito! 🎯
Vou te encaminhar para um consultor que pode te mostrar rapidamente como funciona.

Enquanto isso, você pode visitar nossa página e entender melhor a proposta:

https://imobsites.com.br/

Em breve um consultor entrará em contato!";

// Atualizar fluxo ID 1 (Quero conhecer)
$stmt = $db->prepare("
    UPDATE chatbot_flows 
    SET 
        response_message = ?,
        next_buttons = NULL,
        forward_to_human = 1,
        updated_at = NOW()
    WHERE id = 1
");

$result = $stmt->execute([$newMessage]);

if ($result) {
    echo "✅ Fluxo 'Quero conhecer' atualizado com sucesso!\n\n";
    
    // Verificar atualização
    $flow = $db->query("SELECT id, name, response_message, next_buttons, forward_to_human FROM chatbot_flows WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    
    echo "Configuração atual:\n";
    echo "- ID: {$flow['id']}\n";
    echo "- Nome: {$flow['name']}\n";
    echo "- Encaminha para humano: " . ($flow['forward_to_human'] ? 'SIM' : 'NÃO') . "\n";
    echo "- Próximos botões: " . ($flow['next_buttons'] ? 'SIM' : 'NENHUM (correto!)') . "\n\n";
    echo "Mensagem que será enviada:\n";
    echo "---\n{$flow['response_message']}\n---\n\n";
} else {
    echo "❌ Erro ao atualizar fluxo\n";
}

echo "\n=== VERIFICANDO ESTRUTURA DE OPORTUNIDADES ===\n\n";

// Verificar se tabela opportunities existe
$tables = $db->query("SHOW TABLES LIKE 'opportunities'")->fetchAll();
if (count($tables) > 0) {
    echo "✅ Tabela 'opportunities' existe\n";
    
    // Verificar colunas
    $columns = $db->query("DESCRIBE opportunities")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colunas: " . implode(', ', $columns) . "\n\n";
    
    // Verificar pipelines
    $pipelines = $db->query("SELECT id, name FROM opportunity_pipelines ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    echo "Pipelines disponíveis:\n";
    foreach ($pipelines as $pipeline) {
        echo "  - ID {$pipeline['id']}: {$pipeline['name']}\n";
    }
} else {
    echo "❌ Tabela 'opportunities' NÃO existe\n";
}

echo "\n\n=== VERIFICANDO ESTRUTURA DE NOTIFICAÇÕES ===\n\n";

// Verificar se tabela notifications existe
$tables = $db->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
if (count($tables) > 0) {
    echo "✅ Tabela 'notifications' existe\n";
    $columns = $db->query("DESCRIBE notifications")->fetchAll(PDO::FETCH_COLUMN);
    echo "Colunas: " . implode(', ', $columns) . "\n";
} else {
    echo "❌ Tabela 'notifications' NÃO existe\n";
}
