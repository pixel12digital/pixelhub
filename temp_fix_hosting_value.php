<?php
require_once __DIR__ . '/vendor/autoload.php';

use PixelHub\Core\DB;
use PixelHub\Core\Env;

Env::load();
$db = DB::getConnection();

echo "=== CORRIGINDO VALOR DA HOSPEDAGEM ID 27 ===\n\n";

// Busca o registro atual
$stmt = $db->prepare("
    SELECT ha.id, ha.domain, ha.amount, ha.billing_period_type, ha.hosting_plan_id,
           hp.annual_total_amount, hp.amount as plan_monthly_amount
    FROM hosting_accounts ha
    LEFT JOIN hosting_plans hp ON ha.hosting_plan_id = hp.id
    WHERE ha.id = 27
");
$stmt->execute();
$hosting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$hosting) {
    echo "Hospedagem ID 27 não encontrada!\n";
    exit(1);
}

echo "ANTES DA CORREÇÃO:\n";
echo "Domínio: {$hosting['domain']}\n";
echo "Valor atual no banco: R$ {$hosting['amount']}\n";
echo "Periodicidade: {$hosting['billing_period_type']}\n";
echo "Valor mensal do plano: R$ {$hosting['plan_monthly_amount']}\n";
echo "Valor anual do plano: R$ {$hosting['annual_total_amount']}\n\n";

// Calcula o valor correto baseado na periodicidade
$correctAmount = null;
if ($hosting['billing_period_type'] === 'anual') {
    $correctAmount = $hosting['annual_total_amount'];
    echo "Periodicidade é ANUAL, valor correto deve ser: R$ {$correctAmount}\n";
} else {
    $correctAmount = $hosting['plan_monthly_amount'];
    echo "Periodicidade é MENSAL, valor correto deve ser: R$ {$correctAmount}\n";
}

if ($correctAmount && $correctAmount != $hosting['amount']) {
    echo "\nATUALIZANDO valor de R$ {$hosting['amount']} para R$ {$correctAmount}...\n";
    
    $updateStmt = $db->prepare("UPDATE hosting_accounts SET amount = ? WHERE id = 27");
    $updateStmt->execute([$correctAmount]);
    
    echo "✅ Valor atualizado com sucesso!\n";
} else {
    echo "\n✅ Valor já está correto, nenhuma atualização necessária.\n";
}

echo "\nVERIFICANDO APÓS ATUALIZAÇÃO:\n";
$stmt->execute();
$hostingAfter = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Valor no banco agora: R$ {$hostingAfter['amount']}\n";
