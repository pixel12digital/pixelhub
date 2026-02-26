<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "=== PLANO HOSPEDAGEM VERCEL ===\n\n";
$stmt = $db->query("
    SELECT id, name, amount, billing_cycle, 
           annual_enabled, annual_monthly_amount, annual_total_amount,
           provider
    FROM hosting_plans 
    WHERE name LIKE '%Vercel%' 
    AND is_active = 1
");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($plans as $plan) {
    echo "ID: {$plan['id']}\n";
    echo "Nome: {$plan['name']}\n";
    echo "Provedor: {$plan['provider']}\n";
    echo "Valor Mensal: R$ {$plan['amount']}\n";
    echo "Ciclo: {$plan['billing_cycle']}\n";
    echo "Anual Habilitado: " . ($plan['annual_enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "Valor Mensal (Anual): R$ " . ($plan['annual_monthly_amount'] ?? 'N/A') . "\n";
    echo "Valor Total (Anual): R$ " . ($plan['annual_total_amount'] ?? 'N/A') . "\n";
    echo "\n" . str_repeat('-', 50) . "\n\n";
}
