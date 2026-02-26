<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== REGRAS DE DISPARO ===\n\n";
$rules = $db->query("
    SELECT id, name, days_offset, stage, is_enabled, channels, repeat_if_open, repeat_interval_days, max_repeats
    FROM billing_dispatch_rules
    ORDER BY days_offset
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rules as $rule) {
    echo "ID: {$rule['id']}\n";
    echo "Nome: {$rule['name']}\n";
    echo "Offset: {$rule['days_offset']} dias\n";
    echo "Stage: {$rule['stage']}\n";
    echo "Ativo: " . ($rule['is_enabled'] ? 'SIM' : 'NÃO') . "\n";
    echo "Canais: {$rule['channels']}\n";
    echo "Repetir: " . ($rule['repeat_if_open'] ? 'SIM' : 'NÃO') . "\n";
    echo "Intervalo: {$rule['repeat_interval_days']} dias\n";
    echo "Max repeats: {$rule['max_repeats']}\n";
    echo str_repeat('-', 50) . "\n";
}

echo "\n=== FILA DE HOJE ===\n\n";
$queue = $db->query("
    SELECT id, tenant_id, invoice_ids, dispatch_rule_id, channel, status, scheduled_at, created_at
    FROM billing_dispatch_queue
    WHERE DATE(created_at) = CURDATE()
    ORDER BY scheduled_at
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($queue)) {
    echo "Nenhum job enfileirado hoje.\n";
} else {
    foreach ($queue as $job) {
        echo "Job ID: {$job['id']}\n";
        echo "Tenant: {$job['tenant_id']}\n";
        echo "Faturas: {$job['invoice_ids']}\n";
        echo "Regra: {$job['dispatch_rule_id']}\n";
        echo "Canal: {$job['channel']}\n";
        echo "Status: {$job['status']}\n";
        echo "Agendado: {$job['scheduled_at']}\n";
        echo "Criado: {$job['created_at']}\n";
        echo str_repeat('-', 50) . "\n";
    }
}

echo "\n=== TENANT 25 (Charles Dietrich) ===\n\n";
$tenant = $db->query("
    SELECT id, name, billing_auto_send, billing_auto_channel, is_billing_test
    FROM tenants
    WHERE id = 25
")->fetch(PDO::FETCH_ASSOC);

if ($tenant) {
    echo "ID: {$tenant['id']}\n";
    echo "Nome: {$tenant['name']}\n";
    echo "Auto Send: " . ($tenant['billing_auto_send'] ? 'SIM' : 'NÃO') . "\n";
    echo "Canal: {$tenant['billing_auto_channel']}\n";
    echo "Teste: " . ($tenant['is_billing_test'] ? 'SIM' : 'NÃO') . "\n";
}

echo "\n=== FATURAS DO TENANT 25 ===\n\n";
$invoices = $db->query("
    SELECT id, asaas_invoice_number, due_date, status, value, DATEDIFF(CURDATE(), due_date) as days_overdue
    FROM billing_invoices
    WHERE tenant_id = 25
      AND status IN ('pending', 'overdue')
      AND (is_deleted IS NULL OR is_deleted = 0)
    ORDER BY due_date
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($invoices)) {
    echo "Nenhuma fatura pendente/vencida.\n";
} else {
    foreach ($invoices as $inv) {
        echo "ID: {$inv['id']}\n";
        echo "Número: {$inv['asaas_invoice_number']}\n";
        echo "Vencimento: {$inv['due_date']}\n";
        echo "Status: {$inv['status']}\n";
        echo "Valor: R$ {$inv['value']}\n";
        echo "Dias vencido: {$inv['days_overdue']}\n";
        echo str_repeat('-', 50) . "\n";
    }
}
