<?php

/**
 * Scheduler de cobranças automáticas (PLANEJADOR)
 * 
 * Executa 1x/dia via cron, às 08:00, apenas dias úteis:
 *   0 8 * * 1-5 cd /path/to/pixelhub && php scripts/billing_auto_dispatch.php >> logs/billing_dispatch.log 2>&1
 * 
 * Fluxo (NÃO envia diretamente):
 * 1. Verifica se é dia útil (seg-sex) — se não, encerra
 * 2. Sync Asaas global para garantir dados atualizados
 * 3. Busca regras de disparo ativas
 * 4. Para cada regra, encontra faturas elegíveis (com filtros anti-spam)
 * 5. ENFILEIRA na billing_dispatch_queue com scheduled_at distribuído na janela 08:00–12:00
 * 6. O worker (billing_queue_worker.php) consome a fila e faz o envio real
 * 
 * Separação de responsabilidades:
 * - Este script: PLANEJA (sync + seleciona + enfileira)
 * - billing_queue_worker.php: EXECUTA (consome fila + envia via gateway)
 * 
 * SEGURANÇA: apenas tenants com is_billing_test=1 são processados durante fase de testes.
 */

// ─── Bootstrap ──────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
if (!class_exists('PixelHub\Core\Env')) {
    spl_autoload_register(function ($class) {
        $prefix = 'PixelHub\\';
        $baseDir = __DIR__ . '/../src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) return;
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) require $file;
    });
}

use PixelHub\Core\Env;
use PixelHub\Core\DB;
use PixelHub\Services\BillingSenderService;
use PixelHub\Services\BillingDispatchQueueService;
use PixelHub\Services\AsaasBillingService;

Env::load();

$startTime = microtime(true);
$L = '[BILLING_SCHEDULER]';

echo "{$L} === Início do planejamento de cobranças === " . date('Y-m-d H:i:s') . "\n";

// ─── 0. Verifica dia útil ───────────────────────────────────────
if (!BillingDispatchQueueService::isBusinessDay()) {
    echo "{$L} Hoje é fim de semana. Nenhum envio agendado.\n";
    exit(0);
}

try {
    $db = DB::getConnection();
} catch (\Exception $e) {
    echo "{$L} FATAL: Banco inacessível: {$e->getMessage()}\n";
    exit(1);
}

// ─── 1. Sync Asaas global ───────────────────────────────────────
echo "{$L} Executando sync Asaas global...\n";
try {
    $syncStats = AsaasBillingService::syncAllCustomersAndInvoices();
    echo "{$L} Sync OK: criados={$syncStats['created_customers']}, atualizados={$syncStats['updated_customers']}, faturas_criadas={$syncStats['total_invoices_created']}, faturas_atualizadas={$syncStats['total_invoices_updated']}\n";
    if (!empty($syncStats['errors'])) {
        echo "{$L} AVISO: " . count($syncStats['errors']) . " erro(s) na sync (continuando mesmo assim)\n";
        foreach ($syncStats['errors'] as $err) {
            echo "{$L}   sync_error: {$err}\n";
        }
    }
} catch (\Exception $e) {
    $errorMsg = "Sync Asaas global FALHOU: {$e->getMessage()}";
    echo "{$L} FATAL: {$errorMsg}\n";
    // Registra falha em auditoria
    BillingSenderService::logDispatchPublic('SYNC_GLOBAL_FAIL', $errorMsg);
    exit(1);
}

// ─── 2. Busca regras de disparo ativas ──────────────────────────
$rules = $db->query("
    SELECT * FROM billing_dispatch_rules
    WHERE is_enabled = 1
    ORDER BY days_offset ASC
")->fetchAll(\PDO::FETCH_ASSOC);

if (empty($rules)) {
    echo "{$L} Nenhuma regra ativa. Encerrando.\n";
    exit(0);
}
echo "{$L} Regras ativas: " . count($rules) . "\n";

$stats = [
    'rules_processed' => 0,
    'invoices_found' => 0,
    'enqueued' => 0,
    'skipped_no_test' => 0,
    'skipped_recently_sent' => 0,
    'skipped_max_repeats' => 0,
    'skipped_channel_mismatch' => 0,
    'skipped_already_queued' => 0,
];

// Coleta todos os envios elegíveis antes de calcular horários
$allEligible = []; // Array de ['tenant_id' => X, 'invoice_ids' => [...], 'rule_id' => Y, 'channel' => Z]

// ─── 3. Itera regras e coleta elegíveis ─────────────────────────
foreach ($rules as $rule) {
    $ruleId = (int) $rule['id'];
    $ruleName = $rule['name'];
    $daysOffset = (int) $rule['days_offset'];
    $stage = $rule['stage'];
    $channels = json_decode($rule['channels'], true) ?: ['whatsapp'];
    $repeatIfOpen = (bool) $rule['repeat_if_open'];
    $repeatIntervalDays = $rule['repeat_interval_days'] ? (int) $rule['repeat_interval_days'] : null;
    $maxRepeats = (int) $rule['max_repeats'];

    echo "\n{$L} ── Regra #{$ruleId}: {$ruleName} (offset={$daysOffset}) ──\n";
    $stats['rules_processed']++;

    // ─── Busca faturas que casam com esta regra ─────────────────
    if ($daysOffset < 0) {
        $absDays = abs($daysOffset);
        $stmt = $db->prepare("
            SELECT bi.*, t.name AS tenant_name, t.phone, t.billing_auto_send, t.billing_auto_channel, t.is_billing_test
            FROM billing_invoices bi
            JOIN tenants t ON t.id = bi.tenant_id
            WHERE bi.status = 'pending'
              AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
              AND DATEDIFF(bi.due_date, CURDATE()) = ?
              AND t.billing_auto_send = 1
            ORDER BY t.id, bi.due_date
        ");
        $stmt->execute([$absDays]);
    } elseif ($daysOffset === 0) {
        $stmt = $db->prepare("
            SELECT bi.*, t.name AS tenant_name, t.phone, t.billing_auto_send, t.billing_auto_channel, t.is_billing_test
            FROM billing_invoices bi
            JOIN tenants t ON t.id = bi.tenant_id
            WHERE bi.status IN ('pending', 'overdue')
              AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
              AND bi.due_date = CURDATE()
              AND t.billing_auto_send = 1
            ORDER BY t.id, bi.due_date
        ");
        $stmt->execute();
    } else {
        if ($repeatIfOpen && $repeatIntervalDays) {
            $stmt = $db->prepare("
                SELECT bi.*, t.name AS tenant_name, t.phone, t.billing_auto_send, t.billing_auto_channel, t.is_billing_test
                FROM billing_invoices bi
                JOIN tenants t ON t.id = bi.tenant_id
                WHERE bi.status = 'overdue'
                  AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
                  AND DATEDIFF(CURDATE(), bi.due_date) >= ?
                  AND MOD(DATEDIFF(CURDATE(), bi.due_date) - ?, ?) = 0
                  AND t.billing_auto_send = 1
                ORDER BY t.id, bi.due_date
            ");
            $stmt->execute([$daysOffset, $daysOffset, $repeatIntervalDays]);
        } else {
            $stmt = $db->prepare("
                SELECT bi.*, t.name AS tenant_name, t.phone, t.billing_auto_send, t.billing_auto_channel, t.is_billing_test
                FROM billing_invoices bi
                JOIN tenants t ON t.id = bi.tenant_id
                WHERE bi.status = 'overdue'
                  AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
                  AND DATEDIFF(CURDATE(), bi.due_date) = ?
                  AND t.billing_auto_send = 1
                ORDER BY t.id, bi.due_date
            ");
            $stmt->execute([$daysOffset]);
        }
    }

    $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    echo "{$L}   Faturas encontradas: " . count($invoices) . "\n";
    if (empty($invoices)) continue;

    // ─── Agrupa por tenant ──────────────────────────────────────
    $byTenant = [];
    foreach ($invoices as $inv) {
        $tid = (int) $inv['tenant_id'];
        if (!isset($byTenant[$tid])) {
            $byTenant[$tid] = [
                'tenant_name' => $inv['tenant_name'],
                'billing_auto_channel' => $inv['billing_auto_channel'],
                'is_billing_test' => (int) $inv['is_billing_test'],
                'invoices' => [],
            ];
        }
        $byTenant[$tid]['invoices'][] = $inv;
    }

    // ─── Filtra elegíveis por tenant ────────────────────────────
    foreach ($byTenant as $tid => $group) {
        $tenantLabel = "{$group['tenant_name']} (id={$tid})";
        $invoiceCount = count($group['invoices']);
        $stats['invoices_found'] += $invoiceCount;

        // Guarda de teste
        if ((int) $group['is_billing_test'] !== 1) {
            echo "{$L}   SKIP [{$tenantLabel}]: não é tenant de teste\n";
            $stats['skipped_no_test'] += $invoiceCount;
            continue;
        }

        // Verificação de canal
        $tenantChannel = $group['billing_auto_channel'] ?? 'whatsapp';
        $channelMatch = false;
        foreach ($channels as $rc) {
            if ($tenantChannel === 'both' || $tenantChannel === $rc) { $channelMatch = true; break; }
        }
        if (!$channelMatch) {
            echo "{$L}   SKIP [{$tenantLabel}]: canal incompatível ({$tenantChannel} vs " . implode(',', $channels) . ")\n";
            $stats['skipped_channel_mismatch'] += $invoiceCount;
            continue;
        }

        // Anti-spam por fatura (filtra pelo canal do tenant para não bloquear cross-canal)
        $eligibleIds = [];
        foreach ($group['invoices'] as $inv) {
            $invId = (int) $inv['id'];
            if (BillingSenderService::wasRecentlySent($db, $invId, $ruleId, 20, $tenantChannel)) {
                $stats['skipped_recently_sent']++;
                continue;
            }
            if ($maxRepeats > 0 && BillingSenderService::countSentForRule($db, $invId, $ruleId) >= $maxRepeats) {
                $stats['skipped_max_repeats']++;
                continue;
            }
            $eligibleIds[] = $invId;
        }

        if (empty($eligibleIds)) {
            echo "{$L}   SKIP [{$tenantLabel}]: nenhuma fatura elegível após filtros\n";
            continue;
        }

        $allEligible[] = [
            'tenant_id' => $tid,
            'tenant_name' => $group['tenant_name'],
            'invoice_ids' => $eligibleIds,
            'rule_id' => $ruleId,
            'channel' => $tenantChannel,
        ];
    }
}

// ─── 4. Calcula horários e enfileira ────────────────────────────
$totalToEnqueue = count($allEligible);
echo "\n{$L} Total de envios a enfileirar: {$totalToEnqueue}\n";

if ($totalToEnqueue > 0) {
    $scheduledTimes = BillingDispatchQueueService::calculateScheduledTimes($totalToEnqueue);

    foreach ($allEligible as $i => $item) {
        $scheduledAt = $scheduledTimes[$i] ?? $scheduledTimes[count($scheduledTimes) - 1];
        $tenantLabel = "{$item['tenant_name']} (id={$item['tenant_id']})";

        $jobId = BillingDispatchQueueService::enqueue(
            $item['tenant_id'],
            $item['invoice_ids'],
            $item['rule_id'],
            $item['channel'],
            $scheduledAt
        );

        if ($jobId) {
            echo "{$L}   ENFILEIRADO [{$tenantLabel}]: job #{$jobId} scheduled_at={$scheduledAt->format('H:i:s')} faturas=" . count($item['invoice_ids']) . "\n";
            $stats['enqueued']++;
        } else {
            echo "{$L}   JÁ ENFILEIRADO [{$tenantLabel}]: duplicado para hoje (regra #{$item['rule_id']})\n";
            $stats['skipped_already_queued']++;
        }
    }
}

// ─── 5. Resumo ──────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 2);
echo "\n{$L} === Resumo do Planejamento ===\n";
echo "{$L} Tempo: {$elapsed}s\n";
echo "{$L} Regras processadas: {$stats['rules_processed']}\n";
echo "{$L} Faturas encontradas: {$stats['invoices_found']}\n";
echo "{$L} Enfileirados: {$stats['enqueued']}\n";
echo "{$L} Já enfileirados (dedup): {$stats['skipped_already_queued']}\n";
echo "{$L} Pulados (não teste): {$stats['skipped_no_test']}\n";
echo "{$L} Pulados (já enviado): {$stats['skipped_recently_sent']}\n";
echo "{$L} Pulados (max repeats): {$stats['skipped_max_repeats']}\n";
echo "{$L} Pulados (canal): {$stats['skipped_channel_mismatch']}\n";
echo "{$L} === Fim === " . date('Y-m-d H:i:s') . "\n";
echo "{$L} Worker (billing_queue_worker.php) consumirá a fila ao longo da manhã.\n";

// Log em arquivo
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$ts = date('Y-m-d H:i:s');
$sj = json_encode($stats, JSON_UNESCAPED_UNICODE);
file_put_contents($logDir . '/billing_dispatch.log', "[{$ts}] [PLAN_SUMMARY] {$sj}\n", FILE_APPEND);
