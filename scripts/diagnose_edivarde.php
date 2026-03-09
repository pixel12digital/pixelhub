<?php
/**
 * Diagnóstico: Edivarde Ferreira - 3 mensagens duplicadas em 09/03
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
use PixelHub\Core\Env;
use PixelHub\Core\DB;
Env::load();
$db = DB::getConnection();

echo "=== DIAGNÓSTICO EDIVARDE FERREIRA (09/03) ===\n\n";

// 1. Tenant
$stmt = $db->prepare("SELECT id, name, billing_auto_send, billing_auto_channel, is_billing_test FROM tenants WHERE name LIKE ? LIMIT 5");
$stmt->execute(['%Edivarde%']);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "── Tenants encontrados ──\n";
foreach ($tenants as $t) {
    echo "  id={$t['id']} | {$t['name']} | auto_send={$t['billing_auto_send']} | channel={$t['billing_auto_channel']}\n";
}
if (empty($tenants)) { echo "  NENHUM\n"; exit; }
$tenantId = $tenants[0]['id'];
echo "\n";

// 2. Faturas
$stmt = $db->prepare("SELECT id, asaas_payment_id, due_date, amount, status FROM billing_invoices WHERE tenant_id = ? ORDER BY due_date DESC LIMIT 5");
$stmt->execute([$tenantId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "── Faturas (tenant {$tenantId}) ──\n";
foreach ($invoices as $inv) {
    echo "  id={$inv['id']} | due={$inv['due_date']} | R\${$inv['amount']} | status={$inv['status']} | asaas={$inv['asaas_payment_id']}\n";
}
echo "\n";

// 3. Dispatch queue - TODOS os jobs para este tenant (últimos 7 dias)
$stmt = $db->prepare("
    SELECT id, dispatch_rule_id, invoice_ids, status, scheduled_at, created_at, updated_at, attempts, trigger_source
    FROM billing_dispatch_queue
    WHERE tenant_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
");
$stmt->execute([$tenantId]);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "── Dispatch Queue (últimos 7d) ──\n";
foreach ($jobs as $j) {
    echo "  job#{$j['id']} | rule={$j['dispatch_rule_id']} | status={$j['status']} | scheduled={$j['scheduled_at']} | created={$j['created_at']} | attempts={$j['attempts']} | trigger={$j['trigger_source']}\n";
    echo "           invoices={$j['invoice_ids']}\n";
}
echo "\n";

// 4. Dispatch log
$stmt = $db->prepare("
    SELECT id, invoice_id, channel, template_key, status, sent_at, trigger_source
    FROM billing_dispatch_log
    WHERE tenant_id = ?
      AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY sent_at DESC
");
$stmt->execute([$tenantId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "── Dispatch Log (últimos 7d) ──\n";
foreach ($logs as $l) {
    echo "  id={$l['id']} | inv={$l['invoice_id']} | channel={$l['channel']} | template={$l['template_key']} | status={$l['status']} | sent_at={$l['sent_at']}\n";
}
echo "\n";

// 5. billing_notifications
$stmt = $db->prepare("
    SELECT id, invoice_id, channel, template, status, sent_at, dispatch_rule_id
    FROM billing_notifications
    WHERE tenant_id = ?
      AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY sent_at DESC
");
$stmt->execute([$tenantId]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "── Billing Notifications (últimos 7d) ──\n";
foreach ($notifs as $n) {
    echo "  id={$n['id']} | inv={$n['invoice_id']} | channel={$n['channel']} | status={$n['status']} | sent_at={$n['sent_at']} | rule={$n['dispatch_rule_id']}\n";
}
echo "\n";

// 6. Regras ativas - template_keys
$stmt = $db->query("SELECT id, name, days_offset, template_key, max_repeats, is_active FROM billing_dispatch_rules WHERE is_active = 1 ORDER BY days_offset");
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "── Regras ativas e template_keys ──\n";
foreach ($rules as $r) {
    echo "  id={$r['id']} | offset={$r['days_offset']} | template_key=" . ($r['template_key'] ?: 'NULL') . " | max_repeats={$r['max_repeats']} | {$r['name']}\n";
}
echo "\n";

// 7. Checagem do idempotency para hoje
echo "── Idempotency check para hoje (09/03) ──\n";
$stmt = $db->prepare("
    SELECT id, dispatch_rule_id, status, created_at
    FROM billing_dispatch_queue
    WHERE tenant_id = ?
      AND DATE(created_at) = '2026-03-09'
    ORDER BY id
");
$stmt->execute([$tenantId]);
$todayJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($todayJobs as $j) {
    echo "  job#{$j['id']} | rule={$j['dispatch_rule_id']} | status={$j['status']} | created={$j['created_at']}\n";
}
if (empty($todayJobs)) echo "  nenhum job hoje\n";
echo "\n";

echo "=== FIM ===\n";
