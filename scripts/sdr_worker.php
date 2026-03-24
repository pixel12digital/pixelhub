<?php

/**
 * SDR Worker — Executor da Fila de Disparo
 *
 * Executa a cada 5 min via cron, seg–sáb, 09:00–17:00:
 *   *\/5 9-17 * * 1-6  cd ~/hub.pixel12digital.com.br && php scripts/sdr_worker.php >> logs/sdr_worker.log 2>&1
 *
 * Fluxo:
 * 1. Verifica janela de envio e pausa manual
 * 2. Busca jobs com scheduled_at <= NOW() e status = queued
 * 3. Envia via Whapi (sessão SDR/Orsegups) com sleep entre envios
 * 4. Registra resultado em sdr_dispatch_queue + communication_events
 */

// ─── Bootstrap ──────────────────────────────────────────────────
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
spl_autoload_register(function ($class) {
    $prefix  = 'PixelHub\\';
    $baseDir = __DIR__ . '/../src/';
    $len     = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

use PixelHub\Core\Env;
use PixelHub\Services\SdrDispatchService;

Env::load();

$L = '[SDR_WORKER]';
echo "{$L} === Início === " . date('Y-m-d H:i:s') . "\n";

// ─── 0. Verifica pausa manual ────────────────────────────────────
if (SdrDispatchService::isPaused()) {
    echo "{$L} SDR pausado. Nenhum envio. Encerrando.\n";
    exit(0);
}

// ─── 1. Verifica janela de envio (09:00-17:00) ───────────────────
$hour = (int) date('G');
if ($hour < 9 || $hour >= 17) {
    echo "{$L} Fora da janela de envio (09:00-17:00). Hora atual: {$hour}h. Encerrando.\n";
    exit(0);
}

// ─── 2. Busca jobs prontos ───────────────────────────────────────
$jobs = SdrDispatchService::fetchReadyJobs(5);

if (empty($jobs)) {
    echo "{$L} Nenhum job pronto para envio agora.\n";
    exit(0);
}

echo "{$L} Jobs prontos: " . count($jobs) . "\n";

$sent   = 0;
$failed = 0;

foreach ($jobs as $job) {
    $label = "[#{$job['id']} — {$job['establishment_name']}]";
    echo "{$L} Processando {$label}...\n";

    SdrDispatchService::markProcessing($job['id']);

    try {
        $result = SdrDispatchService::sendOpeningMessage($job);

        if ($result['success'] ?? false) {
            $msgId = $result['message_id'] ?? ($result['id'] ?? 'unknown');
            SdrDispatchService::markSent($job['id'], $msgId);
            echo "{$L}   ✓ Enviado {$label} → msg_id={$msgId}\n";
            $sent++;
        } else {
            $err = $result['error'] ?? 'Erro desconhecido';
            SdrDispatchService::markFailed($job['id'], $err);
            echo "{$L}   ✗ Falha {$label}: {$err}\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        SdrDispatchService::markFailed($job['id'], $e->getMessage());
        echo "{$L}   ✗ Exceção {$label}: {$e->getMessage()}\n";
        error_log("{$L} Exceção job #{$job['id']}: " . $e->getMessage());
        $failed++;
    }

    // Sleep humanizado entre envios: 8-22s (além do intervalo do scheduled_at)
    if (count($jobs) > 1) {
        $sleep = rand(8, 22);
        echo "{$L}   Aguardando {$sleep}s antes do próximo...\n";
        sleep($sleep);
    }
}

// ─── 3. Resumo ───────────────────────────────────────────────────
echo "{$L} === Resumo: enviados={$sent} falhas={$failed} ===\n";
echo "{$L} === Fim === " . date('Y-m-d H:i:s') . "\n";

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents(
    $logDir . '/sdr_worker.log',
    "[" . date('Y-m-d H:i:s') . "] sent={$sent} failed={$failed}\n",
    FILE_APPEND
);
