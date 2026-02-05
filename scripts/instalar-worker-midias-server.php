<?php
/**
 * Script único: cria MediaProcessQueueService e worker no servidor.
 * Uso: php scripts/instalar-worker-midias-server.php
 */
$base = dirname(__DIR__);
$serviceFile = $base . '/src/Services/MediaProcessQueueService.php';
$workerFile = $base . '/scripts/worker_processar_midias.php';

$serviceCode = <<<'PHP'
<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

class MediaProcessQueueService
{
    public static function enqueue(string $eventId): bool
    {
        $db = DB::getConnection();
        try {
            $db->prepare("INSERT INTO media_process_queue (event_id, status, attempts, max_attempts) VALUES (?, 'pending', 0, 3)")->execute([$eventId]);
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) return false;
            throw $e;
        }
    }

    public static function fetchPending(int $limit = 20, int $minMinutesBetweenAttempts = 1): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("SELECT id, event_id, attempts, max_attempts FROM media_process_queue WHERE status IN ('pending','processing') AND attempts < max_attempts AND (last_attempt_at IS NULL OR last_attempt_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)) ORDER BY created_at ASC LIMIT ?");
        $stmt->execute([$minMinutesBetweenAttempts, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function markProcessing(int $jobId): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("UPDATE media_process_queue SET status = 'processing', attempts = attempts + 1, last_attempt_at = NOW() WHERE id = ? AND status IN ('pending','processing')");
        $stmt->execute([$jobId]);
        return $stmt->rowCount() > 0;
    }

    public static function markDone(int $jobId): void
    {
        DB::getConnection()->prepare("UPDATE media_process_queue SET status = 'done' WHERE id = ?")->execute([$jobId]);
    }

    public static function markFailed(int $jobId, ?string $errorMessage = null): void
    {
        DB::getConnection()->prepare("UPDATE media_process_queue SET status = 'failed', error_message = ? WHERE id = ?")->execute([substr($errorMessage ?? '', 0, 500), $jobId]);
    }

    public static function resetToPending(int $jobId): void
    {
        DB::getConnection()->prepare("UPDATE media_process_queue SET status = 'pending' WHERE id = ?")->execute([$jobId]);
    }
}
PHP;

$workerCode = <<<'PHP'
<?php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) require_once __DIR__ . '/../vendor/autoload.php';
else {
    spl_autoload_register(function ($c) {
        if (strncmp('PixelHub\\', $c, 9) !== 0) return;
        $f = __DIR__ . '/../src/' . str_replace('\\', '/', substr($c, 9)) . '.php';
        if (file_exists($f)) require $f;
    });
}
\PixelHub\Core\Env::load();
$opt = getopt('', ['limit:', 'backoff:']);
$limit = isset($opt['limit']) ? max(1, (int)$opt['limit']) : 20;
$backoff = isset($opt['backoff']) ? max(1, (int)$opt['backoff']) : 1;
$db = \PixelHub\Core\DB::getConnection();
if ($db->query("SHOW TABLES LIKE 'media_process_queue'")->rowCount() === 0) exit(0);
$jobs = \PixelHub\Services\MediaProcessQueueService::fetchPending($limit, $backoff);
if (empty($jobs)) exit(0);
foreach ($jobs as $j) {
    $id = (int)$j['id'];
    if (!\PixelHub\Services\MediaProcessQueueService::markProcessing($id)) continue;
    try {
        $ev = \PixelHub\Services\EventIngestionService::findByEventId($j['event_id']);
        if (!$ev) { \PixelHub\Services\MediaProcessQueueService::markFailed($id, 'Evento não encontrado'); continue; }
        \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($ev);
        \PixelHub\Services\MediaProcessQueueService::markDone($id);
    } catch (\Throwable $e) {
        if ((int)$j['attempts'] + 1 >= (int)$j['max_attempts']) \PixelHub\Services\MediaProcessQueueService::markFailed($id, $e->getMessage());
        else \PixelHub\Services\MediaProcessQueueService::resetToPending($id);
    }
}
PHP;

@mkdir(dirname($serviceFile), 0755, true);
file_put_contents($serviceFile, $serviceCode);
file_put_contents($workerFile, $workerCode);
echo "OK: MediaProcessQueueService e worker criados.\n";
echo "Teste: /usr/local/bin/php $workerFile --limit=5\n";
echo "Cron (caminhos absolutos): * * * * * /usr/local/bin/php $workerFile --limit=20 >> /tmp/pixelhub-worker-midias.log 2>&1\n";
