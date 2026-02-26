<?php
require_once __DIR__ . '/src/Core/Env.php';
require_once __DIR__ . '/src/Core/DB.php';

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

echo "=== TENANT 25 (Charles Dietrich) ===\n\n";
$tenant = $db->query("
    SELECT id, name, billing_auto_send, billing_auto_channel, is_billing_test, asaas_customer_id
    FROM tenants
    WHERE id = 25
")->fetch(PDO::FETCH_ASSOC);

if ($tenant) {
    echo "ID: {$tenant['id']}\n";
    echo "Nome: {$tenant['name']}\n";
    echo "Auto Send: " . ($tenant['billing_auto_send'] ? 'SIM' : 'NÃO') . "\n";
    echo "Canal: {$tenant['billing_auto_channel']}\n";
    echo "Teste: " . ($tenant['is_billing_test'] ? 'SIM' : 'NÃO') . "\n";
    echo "Asaas Customer ID: {$tenant['asaas_customer_id']}\n";
}

echo "\n=== TODAS AS FATURAS DO TENANT 25 ===\n\n";
$invoices = $db->query("
    SELECT id, asaas_payment_id, due_date, amount, status, 
           DATEDIFF(CURDATE(), due_date) as days_diff,
           is_deleted, created_at
    FROM billing_invoices
    WHERE tenant_id = 25
    ORDER BY due_date DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($invoices)) {
    echo "Nenhuma fatura encontrada.\n";
} else {
    foreach ($invoices as $inv) {
        echo "ID: {$inv['id']}\n";
        echo "Asaas Payment ID: {$inv['asaas_payment_id']}\n";
        echo "Vencimento: {$inv['due_date']}\n";
        echo "Status: {$inv['status']}\n";
        echo "Valor: R$ {$inv['amount']}\n";
        echo "Dias (hoje - vencimento): {$inv['days_diff']}\n";
        echo "Deletada: " . ($inv['is_deleted'] ? 'SIM' : 'NÃO') . "\n";
        echo "Criada em: {$inv['created_at']}\n";
        echo str_repeat('-', 50) . "\n";
    }
}

echo "\n=== HISTÓRICO DE NOTIFICAÇÕES DO TENANT 25 ===\n\n";
$notifications = $db->query("
    SELECT bn.id, bn.invoice_id, bn.channel, bn.status, bn.sent_at, bn.dispatch_rule_id,
           bi.due_date, bi.status as invoice_status
    FROM billing_notifications bn
    LEFT JOIN billing_invoices bi ON bi.id = bn.invoice_id
    WHERE bn.tenant_id = 25
    ORDER BY bn.sent_at DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifications)) {
    echo "Nenhuma notificação enviada.\n";
} else {
    foreach ($notifications as $notif) {
        echo "Notif ID: {$notif['id']}\n";
        echo "Fatura ID: {$notif['invoice_id']}\n";
        echo "Canal: {$notif['channel']}\n";
        echo "Status: {$notif['status']}\n";
        echo "Enviado em: {$notif['sent_at']}\n";
        echo "Regra: {$notif['dispatch_rule_id']}\n";
        echo "Vencimento fatura: {$notif['due_date']}\n";
        echo "Status fatura: {$notif['invoice_status']}\n";
        echo str_repeat('-', 50) . "\n";
    }
}
