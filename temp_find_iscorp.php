<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "=== BUSCANDO HOSPEDAGEM iscorp.com.br ===\n\n";

$stmt = $db->prepare("
    SELECT ha.id, ha.domain, ha.amount, ha.billing_period_type, ha.hosting_plan_id, ha.tenant_id,
           hp.name as plan_name, hp.annual_total_amount, hp.amount as plan_monthly_amount,
           t.name as tenant_name
    FROM hosting_accounts ha
    LEFT JOIN hosting_plans hp ON ha.hosting_plan_id = hp.id
    LEFT JOIN tenants t ON ha.tenant_id = t.id
    WHERE ha.domain LIKE '%iscorp%'
");
$stmt->execute();
$hostings = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($hostings)) {
    echo "Nenhuma hospedagem encontrada com 'iscorp' no domínio.\n";
    exit(1);
}

foreach ($hostings as $hosting) {
    echo "ID: {$hosting['id']}\n";
    echo "Domínio: {$hosting['domain']}\n";
    echo "Cliente: {$hosting['tenant_name']} (ID: {$hosting['tenant_id']})\n";
    echo "Plano: {$hosting['plan_name']}\n";
    echo "Valor no banco: R$ {$hosting['amount']}\n";
    echo "Periodicidade: {$hosting['billing_period_type']}\n";
    echo "Valor mensal do plano: R$ {$hosting['plan_monthly_amount']}\n";
    echo "Valor anual do plano: R$ {$hosting['annual_total_amount']}\n";
    
    // Verifica se precisa corrigir
    if ($hosting['billing_period_type'] === 'anual' && $hosting['amount'] != $hosting['annual_total_amount']) {
        echo "\n⚠️ CORRIGINDO valor de R$ {$hosting['amount']} para R$ {$hosting['annual_total_amount']}...\n";
        $updateStmt = $db->prepare("UPDATE hosting_accounts SET amount = ? WHERE id = ?");
        $updateStmt->execute([$hosting['annual_total_amount'], $hosting['id']]);
        echo "✅ Valor atualizado!\n";
    } else {
        echo "\n✅ Valor está correto.\n";
    }
    echo "\n" . str_repeat('-', 60) . "\n\n";
}
