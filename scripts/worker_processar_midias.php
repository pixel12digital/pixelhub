<?php
/**
 * Worker da fila de processamento de mídia WhatsApp.
 *
 * Consome jobs de media_process_queue e chama WhatsAppMediaService::processMediaFromEvent.
 * Retry com backoff: até 3 tentativas, mínimo 1 minuto entre tentativas.
 *
 * Uso: php scripts/worker_processar_midias.php [--limit=20] [--backoff=1]
 *
 * Cron sugerido (a cada 1 minuto):
 * * * * * * cd /caminho/para/pixelhub && php scripts/worker_processar_midias.php --limit=20 >> /var/log/pixelhub-worker-midias.log 2>&1
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
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

\PixelHub\Core\Env::load();

$options = getopt('', ['limit:', 'backoff:']);
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 20;
$backoffMinutes = isset($options['backoff']) ? max(1, (int) $options['backoff']) : 1;

$db = \PixelHub\Core\DB::getConnection();

// Verifica se tabela existe
$check = $db->query("SHOW TABLES LIKE 'media_process_queue'");
if ($check->rowCount() === 0) {
    echo "Tabela media_process_queue não existe. Execute: php database/migrate.php\n";
    exit(0); // Sai silenciosamente para não poluir cron
}

$jobs = \PixelHub\Services\MediaProcessQueueService::fetchPending($limit, $backoffMinutes);
if (empty($jobs)) {
    exit(0);
}

$done = 0;
$failed = 0;

foreach ($jobs as $job) {
    $jobId = (int) $job['id'];
    $eventId = $job['event_id'];
    $attempts = (int) $job['attempts'];
    $maxAttempts = (int) $job['max_attempts'];

    if (!\PixelHub\Services\MediaProcessQueueService::markProcessing($jobId)) {
        continue; // Outro worker pegou
    }

    $attempts++; // Acabamos de incrementar em markProcessing
    echo "  [id={$jobId}] event_id={$eventId} attempt={$attempts}/{$maxAttempts} ... ";

    try {
        $event = \PixelHub\Services\EventIngestionService::findByEventId($eventId);
        if (!$event) {
            \PixelHub\Services\MediaProcessQueueService::markFailed($jobId, 'Evento não encontrado');
            echo "FAIL (evento não encontrado)\n";
            $failed++;
            continue;
        }

        $result = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
        \PixelHub\Services\MediaProcessQueueService::markDone($jobId);
        echo "OK\n";
        $done++;
    } catch (\Throwable $e) {
        $err = $e->getMessage();
        if ($attempts >= $maxAttempts) {
            \PixelHub\Services\MediaProcessQueueService::markFailed($jobId, $err);
            echo "FAIL (max tentativas: $err)\n";
            $failed++;
        } else {
            \PixelHub\Services\MediaProcessQueueService::resetToPending($jobId);
            echo "RETRY ($err)\n";
        }
    }
}

if ($done > 0 || $failed > 0) {
    echo "Worker: $done concluídos, $failed falhas.\n";
}
