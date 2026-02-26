<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "=== VERIFICANDO HOSPEDAGEM ID 41 (iscorp.com.br) ===\n\n";

// Busca o registro
$stmt = $db->prepare("
    SELECT ha.id, ha.domain, ha.amount, ha.billing_period_type, ha.hosting_plan_id,
           hp.name as plan_name, hp.annual_total_amount, hp.amount as plan_monthly_amount
    FROM hosting_accounts ha
    LEFT JOIN hosting_plans hp ON ha.hosting_plan_id = hp.id
    WHERE ha.id = 41
");
$stmt->execute();
$hosting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hosting) {
    echo "Hospedagem ID 41 não encontrada!\n";
    exit(1);
}

echo "DADOS ATUAIS:\n";
echo "Domínio: {$hosting['domain']}\n";
echo "Plano: {$hosting['plan_name']}\n";
echo "Valor no banco (amount): R$ {$hosting['amount']}\n";
echo "Periodicidade (billing_period_type): {$hosting['billing_period_type']}\n";
echo "Valor mensal do plano: R$ {$hosting['plan_monthly_amount']}\n";
echo "Valor anual do plano: R$ {$hosting['annual_total_amount']}\n\n";

// Verifica se precisa corrigir
if ($hosting['billing_period_type'] === 'anual' && $hosting['amount'] != $hosting['annual_total_amount']) {
    echo "⚠️ PROBLEMA DETECTADO!\n";
    echo "Periodicidade é ANUAL mas o valor está INCORRETO.\n";
    echo "Valor atual: R$ {$hosting['amount']}\n";
    echo "Valor correto (anual): R$ {$hosting['annual_total_amount']}\n\n";
    
    echo "CORRIGINDO...\n";
    $updateStmt = $db->prepare("UPDATE hosting_accounts SET amount = ? WHERE id = 41");
    $updateStmt->execute([$hosting['annual_total_amount']]);
    echo "✅ Valor atualizado de R$ {$hosting['amount']} para R$ {$hosting['annual_total_amount']}\n";
} else {
    echo "✅ Valor está correto!\n";
}
