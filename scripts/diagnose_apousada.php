<?php
/**
 * Diagnóstico: A POUSADA - cus_000087736349
 * Verifica por que mensagens de due_day/overdue não foram enviadas após 04/03
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();
$db = DB::getConnection();

$asaasId = 'cus_000087736349';
echo "=== DIAGNÓSTICO A POUSADA ({$asaasId}) ===\n\n";

// ─── 1. Encontra o tenant ────────────────────────────────────────
$stmt = $db->prepare("SELECT id, name, phone, billing_auto_send, billing_auto_channel, is_billing_test FROM tenants WHERE asaas_customer_id = ?");
$stmt->execute([$asaasId]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    echo "[ERRO] Tenant não encontrado para asaas_id={$asaasId}\n";
    exit(1);
}
$tenantId = (int) $tenant['id'];
echo "--- TENANT ---\n";
echo "  id:                 {$tenant['id']}\n";
echo "  name:               {$tenant['name']}\n";
echo "  phone:              {$tenant['phone']}\n";
echo "  billing_auto_send:  {$tenant['billing_auto_send']}\n";
echo "  billing_auto_channel: {$tenant['billing_auto_channel']}\n";
echo "  is_billing_test:    {$tenant['is_billing_test']}\n\n";

// ─── 2. Faturas do período (fev/mar 2026) ───────────────────────
echo "--- FATURAS (jan–mar 2026) ---\n";
$stmt = $db->prepare("
    SELECT id, asaas_payment_id, due_date, amount, status, invoice_url, is_deleted
    FROM billing_invoices
    WHERE tenant_id = ?
      AND due_date BETWEEN '2026-01-01' AND '2026-03-31'
    ORDER BY due_date ASC
");
$stmt->execute([$tenantId]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($invoices)) {
    echo "  Nenhuma fatura encontrada.\n\n";
} else {
    foreach ($invoices as $inv) {
        echo "  id={$inv['id']} | asaas={$inv['asaas_payment_id']} | due={$inv['due_date']} | status={$inv['status']} | amount={$inv['amount']} | deleted={$inv['is_deleted']} | url=" . ($inv['invoice_url'] ? substr($inv['invoice_url'], -20) : 'NULL') . "\n";
    }
    echo "\n";
}

// ─── 3. Regras de disparo ativas ────────────────────────────────
echo "--- REGRAS ATIVAS (billing_dispatch_rules) ---\n";
$rules = $db->query("
    SELECT id, name, days_offset, stage, is_enabled, repeat_if_open, repeat_interval_days, max_repeats
    FROM billing_dispatch_rules
    ORDER BY days_offset ASC
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rules as $r) {
    $enabled = $r['is_enabled'] ? 'ON' : 'OFF';
    $repeat = $r['repeat_if_open'] ? "repeat_every={$r['repeat_interval_days']}d" : 'no_repeat';
    echo "  id={$r['id']} | offset={$r['days_offset']} | stage={$r['stage']} | max_repeats={$r['max_repeats']} | {$repeat} | {$enabled}\n";
}
echo "\n";

// ─── 4. Fila de despacho para este tenant ────────────────────────
echo "--- BILLING_DISPATCH_QUEUE (tenant_id={$tenantId}, últimos 30 dias) ---\n";
$stmt = $db->prepare("
    SELECT id, invoice_ids, dispatch_rule_id, channel, status, scheduled_at, attempts, created_at, sent_at, error_message
    FROM billing_dispatch_queue
    WHERE tenant_id = ?
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC
    LIMIT 30
");
$stmt->execute([$tenantId]);
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($queue)) {
    echo "  Nenhum job na fila nos últimos 30 dias.\n\n";
} else {
    foreach ($queue as $q) {
        echo "  job#{$q['id']} | rule={$q['dispatch_rule_id']} | status={$q['status']} | inv={$q['invoice_ids']} | scheduled={$q['scheduled_at']} | sent={$q['sent_at']} | attempts={$q['attempts']} | created={$q['created_at']}\n";
        if ($q['error_message']) echo "    ERROR: {$q['error_message']}\n";
    }
    echo "\n";
}

// ─── 5. Log de despacho para este tenant ────────────────────────
echo "--- BILLING_DISPATCH_LOG (tenant_id={$tenantId}, últimos 30 dias) ---\n";
$stmt = $db->prepare("
    SELECT id, invoice_id, channel, status, sent_at, dispatch_rule_id, queue_job_id
    FROM billing_dispatch_log
    WHERE tenant_id = ?
      AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY sent_at DESC
    LIMIT 30
");
$stmt->execute([$tenantId]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($logs)) {
    echo "  Nenhum log de despacho nos últimos 30 dias.\n\n";
} else {
    foreach ($logs as $l) {
        echo "  log#{$l['id']} | inv={$l['invoice_id']} | rule={$l['dispatch_rule_id']} | status={$l['status']} | channel={$l['channel']} | sent={$l['sent_at']} | job={$l['queue_job_id']}\n";
    }
    echo "\n";
}

// ─── 6. Notificações registradas ─────────────────────────────────
echo "--- BILLING_NOTIFICATIONS (tenant_id={$tenantId}, últimos 30 dias) ---\n";
$stmt = $db->prepare("
    SELECT id, invoice_id, channel, status, sent_at, template, dispatch_rule_id
    FROM billing_notifications
    WHERE tenant_id = ?
      AND sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY sent_at DESC
    LIMIT 30
");
$stmt->execute([$tenantId]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($notifs)) {
    echo "  Nenhuma notificação nos últimos 30 dias.\n\n";
} else {
    foreach ($notifs as $n) {
        echo "  notif#{$n['id']} | inv={$n['invoice_id']} | rule={$n['dispatch_rule_id']} | status={$n['status']} | template={$n['template']} | channel={$n['channel']} | sent={$n['sent_at']}\n";
    }
    echo "\n";
}

// ─── 7. Simula wasRecentlySent para fatura do dia 05/03 ──────────
echo "--- SIMULAÇÃO wasRecentlySent (fatura due=2026-03-05) ---\n";
// Pega a fatura de 05/03
$stmt = $db->prepare("SELECT id FROM billing_invoices WHERE tenant_id = ? AND due_date = '2026-03-05' LIMIT 1");
$stmt->execute([$tenantId]);
$inv0305 = $stmt->fetch(PDO::FETCH_ASSOC);

if ($inv0305) {
    $invId = (int) $inv0305['id'];
    echo "  Fatura id={$invId} (due 2026-03-05)\n";

    // Conta envios em billing_dispatch_log (sem filtro de regra - como está hoje)
    $stmt2 = $db->prepare("
        SELECT COUNT(*), MIN(sent_at), MAX(sent_at)
        FROM billing_dispatch_log
        WHERE invoice_id = ?
          AND status = 'sent'
    ");
    $stmt2->execute([$invId]);
    $row = $stmt2->fetch(PDO::FETCH_NUM);
    echo "  billing_dispatch_log total_sent={$row[0]} | primeiro={$row[1]} | último={$row[2]}\n";

    // Simula para cada regra: seria bloqueada no dia seguinte?
    $stmt3 = $db->prepare("SELECT id, name, days_offset FROM billing_dispatch_rules WHERE is_enabled = 1 ORDER BY days_offset");
    $stmt3->execute();
    $activeRules = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    foreach ($activeRules as $ar) {
        // Calcula quando esta regra dispararia
        $fireDate = date('Y-m-d', strtotime('2026-03-05 + ' . $ar['days_offset'] . ' days'));
        $fireDateTime = $fireDate . ' 08:00:00';

        // Verifica se haveria envio nos últimas 20h antes deste horário
        $stmtCheck = $db->prepare("
            SELECT COUNT(*), MAX(sent_at)
            FROM billing_dispatch_log
            WHERE invoice_id = ?
              AND status = 'sent'
              AND sent_at >= DATE_SUB(?, INTERVAL 20 HOUR)
              AND sent_at < ?
        ");
        $stmtCheck->execute([$invId, $fireDateTime, $fireDateTime]);
        $checkRow = $stmtCheck->fetch(PDO::FETCH_NUM);
        $blocked = (int)$checkRow[0] > 0 ? "BLOQUEADO (último envio: {$checkRow[1]})" : "OK - passaria";
        echo "  Regra #{$ar['id']} '{$ar['name']}' (offset={$ar['days_offset']}) → dispara em {$fireDate}: {$blocked}\n";
    }
} else {
    echo "  Fatura com due_date=2026-03-05 não encontrada para este tenant.\n";
}
echo "\n";

// ─── 8. Log do billing_dispatch.log (arquivo) ────────────────────
echo "--- ARQUIVO billing_dispatch.log (últimas 50 linhas) ---\n";
$logFile = __DIR__ . '/../logs/billing_dispatch.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last50 = array_slice($lines, -50);
    foreach ($last50 as $line) {
        echo "  {$line}\n";
    }
} else {
    echo "  Arquivo não encontrado: {$logFile}\n";
}
echo "\n";

echo "=== FIM DO DIAGNÓSTICO ===\n";
