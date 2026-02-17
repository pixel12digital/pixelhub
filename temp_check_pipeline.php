<?php

// Define constantes de ambiente manualmente para teste
$_ENV['DB_HOST'] = 'r225us.hmservers.net';
$_ENV['DB_NAME'] = 'pixel12digital_pixelhub';
$_ENV['DB_USER'] = 'pixel12digital_pixelhub';
$_ENV['DB_PASS'] = 'Los@ngo#081081';
$_ENV['DB_CHARSET'] = 'utf8mb4';

// Carrega classes do PixelHub
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Core/DB.php';

$db = PixelHub\Core\DB::getConnection();

echo "=== Verificando opportunities ativas no pipeline ===\n";

// Simula filtro padrão da tela Oportunidades (status = active)
$stmt = $db->prepare("
    SELECT o.*, l.name as lead_name, l.phone as lead_phone 
    FROM opportunities o
    LEFT JOIN leads l ON o.lead_id = l.id
    WHERE o.status = 'active'
    ORDER BY o.created_at DESC
");
$stmt->execute();
$activeOpps = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total de opportunities ativas: " . count($activeOpps) . "\n\n";

foreach ($activeOpps as $opp) {
    echo "ID: {$opp['id']}\n";
    echo "Nome: {$opp['name']}\n";
    echo "Lead: {$opp['lead_name']} ({$opp['lead_phone']})\n";
    echo "Stage: {$opp['stage']}\n";
    echo "Status: {$opp['status']}\n";
    echo "Created: {$opp['created_at']}\n";
    echo "---\n";
}

// Buscar especificamente a Fátima
echo "\n=== Busca específica pela Fátima ===\n";
$stmt = $db->prepare("
    SELECT o.*, l.name as lead_name, l.phone as lead_phone 
    FROM opportunities o
    LEFT JOIN leads l ON o.lead_id = l.id
    WHERE l.name = 'Fátima' AND o.status = 'active'
");
$stmt->execute();
$fatimaOpps = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($fatimaOpps) {
    echo "✅ Fátima encontrada!\n";
    foreach ($fatimaOpps as $opp) {
        echo "- Opportunity ID={$opp['id']}, stage={$opp['stage']}, created={$opp['created_at']}\n";
    }
} else {
    echo "❌ Fátima não encontrada nas opportunities ativas\n";
    
    // Verificar se existe opportunity mas com status diferente
    $stmt = $db->prepare("
        SELECT o.*, l.name as lead_name 
        FROM opportunities o
        LEFT JOIN leads l ON o.lead_id = l.id
        WHERE l.name = 'Fátima'
    ");
    $stmt->execute();
    $allFatimaOpps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($allFatimaOpps) {
        echo "Fátima encontrada com status diferente:\n";
        foreach ($allFatimaOpps as $opp) {
            echo "- Status={$opp['status']}, stage={$opp['stage']}\n";
        }
    }
}
