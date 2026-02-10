<?php

/**
 * Worker de envio de cobranças (EXECUTOR)
 * 
 * Consome jobs da billing_dispatch_queue cujo scheduled_at <= NOW().
 * Envia via BillingSenderService::send() e marca como sent ou failed.
 * 
 * Cron sugerido (a cada 5 min, apenas dias úteis, janela 08:00–12:00):
 *   [star]/5 8-11 [star] [star] 1-5 cd /path/to/pixelhub && php scripts/billing_queue_worker.php >> logs/billing_worker.log 2>&1
 *   (substituir [star] por asterisco)
 * 
 * Regras:
 * - Só executa dentro da janela 08:00–12:00
 * - Só executa em dias úteis (seg-sex)
 * - Processa até --limit jobs por execução (default: 5)
 * - Sleep de --delay segundos entre envios para rate-limit (default: 30s)
 * - Retry automático: até max_attempts (3), com status voltando para 'queued'
 * - Falhas definitivas ficam como 'failed' e são visíveis na auditoria
 * 
 * Uso: php scripts/billing_queue_worker.php [--limit=5] [--delay=30] [--dry-run]
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

Env::load();

$options = getopt('', ['limit:', 'delay:', 'dry-run']);
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 5;
$delaySec = isset($options['delay']) ? max(5, (int) $options['delay']) : 30;
$dryRun = isset($options['dry-run']);

$L = '[BILLING_WORKER]';

// ─── 0. Guardas de janela ───────────────────────────────────────
if (!BillingDispatchQueueService::isBusinessDay()) {
    exit(0); // Silencioso em fins de semana
}

if (!BillingDispatchQueueService::isWithinSendWindow()) {
    exit(0); // Fora da janela 08-12
}

$db = DB::getConnection();

// Verifica se tabela existe
$check = $db->query("SHOW TABLES LIKE 'billing_dispatch_queue'");
if ($check->rowCount() === 0) {
    exit(0);
}

// ─── 1. Busca jobs prontos ──────────────────────────────────────
$jobs = BillingDispatchQueueService::fetchReady($limit);
if (empty($jobs)) {
    exit(0); // Nada a processar
}

echo "{$L} " . date('Y-m-d H:i:s') . " — {$limit} max, {$delaySec}s delay, " . count($jobs) . " job(s) prontos" . ($dryRun ? ' [DRY-RUN]' : '') . "\n";

$sent = 0;
$failed = 0;

// ─── 2. Processa cada job ───────────────────────────────────────
foreach ($jobs as $job) {
    $jobId = (int) $job['id'];
    $tenantId = (int) $job['tenant_id'];
    $invoiceIds = json_decode($job['invoice_ids'], true) ?: [];
    $dispatchRuleId = $job['dispatch_rule_id'] ? (int) $job['dispatch_rule_id'] : null;
    $channel = $job['channel'] ?? 'whatsapp';
    $message = $job['message'] ?? null; // null = montar na hora
    $attempts = (int) $job['attempts'];
    $maxAttempts = (int) $job['max_attempts'];

    // Lock otimista
    if (!BillingDispatchQueueService::markProcessing($jobId)) {
        echo "{$L}   [job#{$jobId}] SKIP: outro worker pegou\n";
        continue;
    }

    $attempts++; // markProcessing incrementou

    echo "{$L}   [job#{$jobId}] tenant={$tenantId} faturas=" . count($invoiceIds) . " canal={$channel} attempt={$attempts}/{$maxAttempts}";

    if ($dryRun) {
        echo " → DRY-RUN (não enviado)\n";
        // Volta para queued
        BillingDispatchQueueService::markFailed($jobId, 'dry-run: não enviado');
        continue;
    }

    // ─── Verificação dupla da janela (segurança) ────────────────
    if (!BillingDispatchQueueService::isWithinSendWindow()) {
        echo " → ABORT: saiu da janela de envio\n";
        // Volta para queued sem consumir tentativa
        $db->prepare("UPDATE billing_dispatch_queue SET status = 'queued', attempts = attempts - 1, updated_at = NOW() WHERE id = ?")->execute([$jobId]);
        break; // Para o worker inteiro
    }

    // ─── Envia via BillingSenderService ─────────────────────────
    try {
        $result = BillingSenderService::send([
            'tenant_id' => $tenantId,
            'invoice_ids' => $invoiceIds,
            'channel' => $channel,
            'triggered_by' => 'scheduler',
            'dispatch_rule_id' => $dispatchRuleId,
            'message_override' => $message,
            'skip_asaas_sync' => true, // Sync já foi feita pelo scheduler
        ]);
    } catch (\Exception $e) {
        $result = ['success' => false, 'error' => 'Exception: ' . $e->getMessage()];
    }

    if ($result['success']) {
        BillingDispatchQueueService::markSent($jobId);
        $gwId = $result['gateway_message_id'] ?? '-';
        echo " → SENT (msg_id={$gwId})\n";
        $sent++;
    } else {
        $error = $result['error'] ?? 'Erro desconhecido';
        BillingDispatchQueueService::markFailed($jobId, $error);
        echo " → FAIL: {$error}\n";
        $failed++;

        // Se é uma falha que sugere problema sistêmico (gateway down), para o worker
        if (stripos($error, 'gateway') !== false || stripos($error, 'conectar') !== false) {
            echo "{$L}   CIRCUIT-BREAK: erro de gateway detectado, parando worker para evitar spam\n";
            BillingSenderService::logDispatchPublic('WORKER_CIRCUIT_BREAK', "Worker parado após erro de gateway: {$error}", [
                'job_id' => $jobId,
                'tenant_id' => $tenantId,
            ]);
            break;
        }
    }

    // ─── Rate limit: sleep entre envios ─────────────────────────
    if ($delaySec > 0 && next($jobs) !== false) {
        echo "{$L}   (aguardando {$delaySec}s para rate-limit...)\n";
        sleep($delaySec);
    }
}

// ─── 3. Resumo ──────────────────────────────────────────────────
echo "{$L} Resultado: sent={$sent} failed={$failed}\n";

// Log resumo
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$ts = date('Y-m-d H:i:s');
$summary = json_encode(['sent' => $sent, 'failed' => $failed, 'jobs_found' => count($jobs)], JSON_UNESCAPED_UNICODE);
file_put_contents($logDir . '/billing_dispatch.log', "[{$ts}] [WORKER] {$summary}\n", FILE_APPEND);

// Se houve falhas, registra para badge na UI
if ($failed > 0) {
    BillingSenderService::logDispatchPublic('WORKER_HAS_FAILURES', "Worker concluiu com {$failed} falha(s)", [
        'sent' => $sent,
        'failed' => $failed,
    ]);
}
