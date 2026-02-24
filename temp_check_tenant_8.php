<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

PixelHub\Core\Env::load(__DIR__ . '/.env');
$db = PixelHub\Core\DB::getConnection();

echo "=== VERIFICANDO TENANT ID 8 ===\n\n";

$stmt = $db->prepare("SELECT * FROM tenants WHERE id = 8");
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if ($tenant) {
    echo "Tenant ID 8:\n";
    foreach ($tenant as $key => $value) {
        if ($value !== null) {
            echo "  {$key}: {$value}\n";
        }
    }
} else {
    echo "Tenant ID 8 não encontrado.\n";
}

echo "\n";

// Verifica se a oportunidade ID 8 tem alguma relação
echo "=== VERIFICANDO OPORTUNIDADE ID 8 ===\n\n";
$stmt = $db->prepare("SELECT * FROM opportunities WHERE id = 8");
$stmt->execute();
$opp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($opp) {
    echo "Oportunidade ID 8:\n";
    echo "  Nome: {$opp['name']}\n";
    echo "  Lead ID: {$opp['lead_id']}\n";
    echo "  Tenant ID: " . ($opp['tenant_id'] ?: 'NULL') . "\n";
    echo "  Status: {$opp['status']}\n";
    echo "  Criado: {$opp['created_at']}\n";
}
