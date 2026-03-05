<?php
require 'vendor/autoload.php';
require 'src/Core/DB.php';

$db = \PixelHub\Core\DB::getConnection();

echo "=== ESTRUTURA DE OPORTUNIDADES ===\n\n";

// Verificar tabela opportunities
echo "Tabela 'opportunities':\n";
$columns = $db->query("DESCRIBE opportunities")->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['Field']} ({$col['Type']})\n";
}

echo "\n=== VERIFICANDO STAGES (ETAPAS) ===\n\n";

// Verificar valores únicos de stage
$stages = $db->query("SELECT DISTINCT stage FROM opportunities WHERE stage IS NOT NULL ORDER BY stage")->fetchAll(PDO::FETCH_COLUMN);
if (count($stages) > 0) {
    echo "Etapas existentes:\n";
    foreach ($stages as $stage) {
        echo "  - $stage\n";
    }
} else {
    echo "Nenhuma etapa encontrada (tabela vazia)\n";
}

echo "\n=== VERIFICANDO SERVICES (PRODUTOS/SERVIÇOS) ===\n\n";

// Verificar se existe serviço "Imobiliária"
$services = $db->query("SELECT id, name FROM billing_service_types ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
echo "Serviços disponíveis:\n";
foreach ($services as $service) {
    echo "  - ID {$service['id']}: {$service['name']}\n";
}

echo "\n=== VERIFICANDO NOTIFICAÇÕES ===\n\n";

$tables = $db->query("SHOW TABLES LIKE 'notifications'")->fetchAll();
if (count($tables) > 0) {
    echo "✅ Tabela 'notifications' existe\n";
    $columns = $db->query("DESCRIBE notifications")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} else {
    echo "❌ Tabela 'notifications' NÃO existe\n";
}

echo "\n=== VERIFICANDO ChatbotFlowService ===\n\n";

// Verificar se o método executeFlow existe
if (class_exists('\PixelHub\Services\ChatbotFlowService')) {
    echo "✅ ChatbotFlowService existe\n";
    
    if (method_exists('\PixelHub\Services\ChatbotFlowService', 'executeFlow')) {
        echo "✅ Método executeFlow existe\n";
    } else {
        echo "❌ Método executeFlow NÃO existe\n";
    }
} else {
    echo "❌ ChatbotFlowService NÃO existe\n";
}
