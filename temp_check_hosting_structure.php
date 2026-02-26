<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "=== ESTRUTURA DA TABELA hosting_plans ===\n\n";
$stmt = $db->query("SHOW COLUMNS FROM hosting_plans");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "{$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']} - {$col['Default']}\n";
}

echo "\n\n=== PLANOS CADASTRADOS ===\n\n";
$stmt = $db->query("SELECT id, name, amount, billing_cycle, annual_enabled, annual_monthly_amount, annual_total_amount FROM hosting_plans WHERE is_active = 1");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($plans as $plan) {
    echo "ID: {$plan['id']}\n";
    echo "Nome: {$plan['name']}\n";
    echo "Valor Mensal: R$ {$plan['amount']}\n";
    echo "Ciclo: {$plan['billing_cycle']}\n";
    echo "Anual Habilitado: " . ($plan['annual_enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "Valor Mensal (Anual): R$ " . ($plan['annual_monthly_amount'] ?? 'N/A') . "\n";
    echo "Valor Total (Anual): R$ " . ($plan['annual_total_amount'] ?? 'N/A') . "\n";
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

echo "\n=== ESTRUTURA DA TABELA hosting_accounts ===\n\n";
$stmt = $db->query("SHOW COLUMNS FROM hosting_accounts");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "{$col['Field']} - {$col['Type']}\n";
}
