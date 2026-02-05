<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Fila de processamento de mídia de eventos WhatsApp.
 * Enfileira eventos inbound; worker consome e chama WhatsAppMediaService.
 */
class MediaProcessQueueService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /**
     * Enfileira evento para processamento de mídia.
     * Ignora se já existir (idempotente).
     *
     * @param string $eventId UUID do evento em communication_events
     * @return bool true se enfileirado, false se já existia
     */
    public static function enqueue(string $eventId): bool
    {
        $db = DB::getConnection();
        try {
            $db->prepare("
                INSERT INTO media_process_queue (event_id, status, attempts, max_attempts)
                VALUES (?, 'pending', 0, 3)
            ")->execute([$eventId]);
            return true;
        } catch (\PDOException $e) {
            // 1062 = Duplicate entry (UNIQUE violation)
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                return false; // Já enfileirado
            }
            throw $e;
        }
    }

    /**
     * Busca próximos jobs pendentes para processar.
     *
     * @param int $limit Máximo de jobs por execução
     * @param int $minMinutesBetweenAttempts Minutos entre tentativas (backoff)
     * @return array Lista de registros da fila
     */
    public static function fetchPending(int $limit = 20, int $minMinutesBetweenAttempts = 1): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT id, event_id, attempts, max_attempts
            FROM media_process_queue
            WHERE status IN ('pending', 'processing')
            AND attempts < max_attempts
            AND (
                last_attempt_at IS NULL
                OR last_attempt_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            )
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$minMinutesBetweenAttempts, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marca job como em processamento (evita dupla execução por workers paralelos).
     *
     * @param int $jobId ID do registro na fila
     * @return bool true se marcou, false se outro worker pegou
     */
    public static function markProcessing(int $jobId): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            UPDATE media_process_queue
            SET status = 'processing', attempts = attempts + 1, last_attempt_at = NOW()
            WHERE id = ? AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$jobId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Marca job como concluído.
     */
    public static function markDone(int $jobId): void
    {
        DB::getConnection()->prepare("
            UPDATE media_process_queue SET status = 'done' WHERE id = ?
        ")->execute([$jobId]);
    }

    /**
     * Marca job como falho (após exceder max_attempts ou erro definitivo).
     */
    public static function markFailed(int $jobId, string $errorMessage = null): void
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            UPDATE media_process_queue
            SET status = 'failed', error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([substr($errorMessage ?? '', 0, 500), $jobId]);
    }

    /**
     * Retorna job ao estado pending para retry (quando attempts < max_attempts).
     */
    public static function resetToPending(int $jobId): void
    {
        DB::getConnection()->prepare("
            UPDATE media_process_queue SET status = 'pending' WHERE id = ?
        ")->execute([$jobId]);
    }
}
