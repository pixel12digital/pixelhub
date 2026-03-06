<?php
/**
 * Diagnóstico do sistema de cobrança automática — READ ONLY
 * Uso: php scripts/diagnose_billing.php
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    $prefix = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use PixelHub\Core\Env;
use PixelHub\Core\DB;

Env::load();

$db = DB::getConnection();
$sep = str_repeat('=', 60);

echo "\n{$sep}\n";
echo "DIAGNÓSTICO DO SISTEMA DE COBRANÇA AUTOMÁTICA\n";
echo "Data/hora local: " . date('Y-m-d H:i:s') . "\n";
echo "Dia da semana (N): " . date('N') . " (1=seg, 7=dom)\n";
echo "{$sep}\n";

// ─── 1. Regras de disparo ────────────────────────────────────────
echo "\n[1] REGRAS DE DISPARO (billing_dispatch_rules)\n";
$rules = $db->query("SELECT id, name, stage, days_offset, is_enabled, repeat_if_open, repeat_interval_days, max_repeats FROM billing_dispatch_rules ORDER BY days_offset ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rules as $r) {
    $status = $r['is_enabled'] ? 'ATIVA' : 'INATIVA';
    echo "  #{$r['id']} [{$status}] {$r['name']} | offset={$r['days_offset']} | max_repeats={$r['max_repeats']} | repeat_if_open={$r['repeat_if_open']}\n";
}

// ─── 2. Tenants com billing_auto_send ───────────────────────────
echo "\n[2] TENANTS COM COBRANÇA AUTOMÁTICA (billing_auto_send=1)\n";
$tenants = $db->query("
    SELECT id, name, phone, billing_auto_send, billing_auto_channel, is_billing_test
    FROM tenants
    WHERE billing_auto_send = 1
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);
if (empty($tenants)) {
    echo "  *** NENHUM tenant com billing_auto_send=1 ***\n";
} else {
    foreach ($tenants as $t) {
        $test = $t['is_billing_test'] ? '[TESTE]' : '[REAL]';
        $phone = $t['phone'] ?: '(sem telefone)';
        echo "  #{$t['id']} {$test} {$t['name']} | canal={$t['billing_auto_channel']} | phone={$phone}\n";
    }
}

// ─── 3. Faturas pendentes/vencidas (últimos 30 dias) ────────────
echo "\n[3] FATURAS ELEGÍVEIS (due_date nos últimos 30 dias + próximos 5 dias, pending/overdue)\n";
$invoices = $db->query("
    SELECT bi.id, bi.tenant_id, t.name AS tenant_name, bi.due_date, bi.status, bi.amount,
           DATEDIFF(CURDATE(), bi.due_date) AS days_overdue,
           t.billing_auto_send, t.is_billing_test
    FROM billing_invoices bi
    JOIN tenants t ON t.id = bi.tenant_id
    WHERE bi.status IN ('pending', 'overdue')
      AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
      AND bi.due_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)
    ORDER BY bi.due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($invoices)) {
    echo "  (nenhuma fatura elegível no período)\n";
} else {
    echo sprintf("  %-6s %-5s %-25s %-12s %-10s %-8s %-9s %-9s\n", 
        "INV_ID", "TEN", "TENANT", "DUE_DATE", "STATUS", "DIAS+", "AUTO_SEND", "TEST");
    foreach ($invoices as $inv) {
        $overdue = (int)$inv['days_overdue'];
        $overdueStr = $overdue >= 0 ? "+{$overdue}d" : "{$overdue}d";
        echo sprintf("  %-6s %-5s %-25s %-12s %-10s %-8s %-9s %-9s\n",
            $inv['id'],
            $inv['tenant_id'],
            substr($inv['tenant_name'], 0, 25),
            $inv['due_date'],
            $inv['status'],
            $overdueStr,
            $inv['billing_auto_send'] ? 'SIM' : 'NAO',
            $inv['is_billing_test'] ? 'TESTE' : '-'
        );
    }
}

// ─── 4. Fila de despacho (billing_dispatch_queue) últimos 7 dias ─
echo "\n[4] FILA DE DESPACHO (billing_dispatch_queue) — últimos 7 dias\n";
$queue = $db->query("
    SELECT bdq.id, bdq.tenant_id, t.name AS tenant_name, bdq.dispatch_rule_id, bdr.name AS rule_name,
           bdq.channel, bdq.status, bdq.scheduled_at, bdq.sent_at, bdq.attempts, bdq.max_attempts,
           bdq.error_message, bdq.created_at
    FROM billing_dispatch_queue bdq
    LEFT JOIN tenants t ON t.id = bdq.tenant_id
    LEFT JOIN billing_dispatch_rules bdr ON bdr.id = bdq.dispatch_rule_id
    WHERE bdq.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY bdq.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($queue)) {
    echo "  *** FILA VAZIA nos últimos 7 dias — scheduler pode não ter rodado! ***\n";
} else {
    foreach ($queue as $q) {
        $err = $q['error_message'] ? " | ERRO: " . substr($q['error_message'], 0, 80) : '';
        $sentAt = $q['sent_at'] ? " sent={$q['sent_at']}" : '';
        echo "  #{$q['id']} [{$q['status']}] {$q['tenant_name']} | regra='{$q['rule_name']}' | canal={$q['channel']} | scheduled={$q['scheduled_at']}{$sentAt} | attempts={$q['attempts']}/{$q['max_attempts']}{$err}\n";
    }
}

// ─── 5. Resumo da fila por data/status ──────────────────────────
echo "\n[5] RESUMO DA FILA POR DIA/STATUS (últimos 10 dias)\n";
$summary = $db->query("
    SELECT DATE(created_at) as dia, status, COUNT(*) as cnt
    FROM billing_dispatch_queue
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 DAY)
    GROUP BY DATE(created_at), status
    ORDER BY dia DESC, status
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($summary)) {
    echo "  (sem dados)\n";
} else {
    foreach ($summary as $s) {
        echo "  {$s['dia']} | {$s['status']}: {$s['cnt']}\n";
    }
}

// ─── 6. Log de despacho (billing_dispatch_log) ──────────────────
echo "\n[6] LOG DE ENVIOS EXECUTADOS (billing_dispatch_log) — últimos 7 dias\n";
$logs = $db->query("
    SELECT bdl.id, bdl.tenant_id, t.name AS tenant_name, bdl.invoice_id, bdl.channel,
           bdl.template_key, bdl.status, bdl.sent_at, bdl.trigger_source, bdl.error_message
    FROM billing_dispatch_log bdl
    LEFT JOIN tenants t ON t.id = bdl.tenant_id
    WHERE bdl.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY bdl.sent_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($logs)) {
    echo "  *** LOG VAZIO nos últimos 7 dias ***\n";
} else {
    foreach ($logs as $l) {
        echo "  #{$l['id']} [{$l['status']}] {$l['tenant_name']} | inv={$l['invoice_id']} | {$l['channel']} | {$l['template_key']} | {$l['sent_at']} | trigger={$l['trigger_source']}\n";
    }
}

// ─── 7. billing_notifications (últimos 7 dias) ──────────────────
echo "\n[7] NOTIFICAÇÕES REGISTRADAS (billing_notifications) — últimos 7 dias\n";
$notifs = $db->query("
    SELECT bn.id, bn.tenant_id, t.name AS tenant_name, bn.invoice_id, bn.channel,
           bn.status, bn.sent_at, bn.triggered_by
    FROM billing_notifications bn
    LEFT JOIN tenants t ON t.id = bn.tenant_id
    WHERE bn.sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY bn.sent_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($notifs)) {
    echo "  *** SEM NOTIFICAÇÕES nos últimos 7 dias ***\n";
} else {
    foreach ($notifs as $n) {
        echo "  #{$n['id']} [{$n['status']}] {$n['tenant_name']} | inv={$n['invoice_id']} | {$n['channel']} | {$n['sent_at']} | by={$n['triggered_by']}\n";
    }
}

// ─── 8. Verificação de faturas específicas do dia 5 ─────────────
echo "\n[8] ANÁLISE: Faturas com vencimento no dia 5 do mês atual/anterior\n";
$day5invoices = $db->query("
    SELECT bi.id, bi.tenant_id, t.name AS tenant_name, bi.due_date, bi.status,
           t.billing_auto_send, t.is_billing_test, t.phone,
           (SELECT COUNT(*) FROM billing_notifications bn WHERE bn.invoice_id = bi.id) AS notif_count,
           (SELECT COUNT(*) FROM billing_dispatch_log bdl WHERE bdl.invoice_id = bi.id) AS log_count
    FROM billing_invoices bi
    JOIN tenants t ON t.id = bi.tenant_id
    WHERE DAY(bi.due_date) = 5
      AND bi.due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
      AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
    ORDER BY bi.due_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($day5invoices)) {
    echo "  (nenhuma fatura com vencimento no dia 5 nos últimos 60 dias)\n";
} else {
    foreach ($day5invoices as $i) {
        $auto = $i['billing_auto_send'] ? 'SIM' : 'NAO';
        $phone = $i['phone'] ?: '(sem tel)';
        echo "  inv#{$i['id']} {$i['tenant_name']} | due={$i['due_date']} | status={$i['status']} | auto_send={$auto} | notifs={$i['notif_count']} | logs={$i['log_count']} | phone={$phone}\n";
    }
}

// ─── 9. Falhas recentes ──────────────────────────────────────────
echo "\n[9] JOBS COM FALHA (billing_dispatch_queue status=failed, últimos 30 dias)\n";
$failed = $db->query("
    SELECT bdq.id, t.name AS tenant_name, bdq.dispatch_rule_id, bdq.scheduled_at, 
           bdq.attempts, bdq.error_message, bdq.created_at
    FROM billing_dispatch_queue bdq
    LEFT JOIN tenants t ON t.id = bdq.tenant_id
    WHERE bdq.status = 'failed'
      AND bdq.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY bdq.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($failed)) {
    echo "  (nenhum job falhado)\n";
} else {
    foreach ($failed as $f) {
        echo "  #{$f['id']} {$f['tenant_name']} | sched={$f['scheduled_at']} | attempts={$f['attempts']} | err=" . substr($f['error_message'], 0, 100) . "\n";
    }
}

// ─── 10. Tabelas existentes (verificação) ───────────────────────
echo "\n[10] VERIFICAÇÃO DE TABELAS\n";
$tables = ['billing_dispatch_queue', 'billing_dispatch_rules', 'billing_dispatch_log', 'billing_notifications', 'billing_invoices'];
foreach ($tables as $tbl) {
    $r = $db->query("SHOW TABLES LIKE '{$tbl}'")->fetch();
    $exists = $r ? 'OK' : '*** NÃO EXISTE ***';
    if ($r) {
        $cnt = $db->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        echo "  {$tbl}: {$exists} | total={$cnt}\n";
    } else {
        echo "  {$tbl}: {$exists}\n";
    }
}

// ─── 11. Estrutura billing_dispatch_log (colunas) ───────────────
echo "\n[11] ESTRUTURA billing_dispatch_log\n";
$cols = $db->query("SHOW COLUMNS FROM billing_dispatch_log")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) {
    echo "  {$c['Field']} | {$c['Type']} | null={$c['Null']} | default={$c['Default']}\n";
}

// ─── 12. Estrutura billing_dispatch_queue (colunas) ─────────────
echo "\n[12] ESTRUTURA billing_dispatch_queue\n";
$cols2 = $db->query("SHOW COLUMNS FROM billing_dispatch_queue")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) {
    echo "  {$c['Field']} | {$c['Type']} | null={$c['Null']} | default={$c['Default']}\n";
}

// ─── 13. Verificação de jobs 'queued' antigos (possível stuck) ───
echo "\n[13] JOBS QUEUED ANTIGOS (scheduled_at < AGORA, podem estar presos)\n";
$stuck = $db->query("
    SELECT bdq.id, t.name AS tenant_name, bdq.status, bdq.scheduled_at, bdq.created_at, bdq.attempts
    FROM billing_dispatch_queue bdq
    LEFT JOIN tenants t ON t.id = bdq.tenant_id
    WHERE bdq.status = 'queued'
      AND bdq.scheduled_at < NOW()
    ORDER BY bdq.scheduled_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($stuck)) {
    echo "  (nenhum job queued com scheduled_at no passado)\n";
} else {
    echo "  *** ATENÇÃO: Jobs queued com scheduled_at no passado (nunca foram processados!) ***\n";
    foreach ($stuck as $s) {
        echo "  #{$s['id']} {$s['tenant_name']} | sched={$s['scheduled_at']} | criado={$s['created_at']} | attempts={$s['attempts']}\n";
    }
}

echo "\n{$sep}\n";
echo "FIM DO DIAGNÓSTICO — " . date('Y-m-d H:i:s') . "\n";
echo "{$sep}\n\n";
