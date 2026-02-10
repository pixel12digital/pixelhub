<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Fila de envio de cobranças automáticas.
 * 
 * Padrão idêntico a MediaProcessQueueService:
 * - Scheduler enfileira (enqueue) com scheduled_at distribuído na janela da manhã
 * - Worker consome (fetchReady) respeitando scheduled_at <= NOW()
 * - markProcessing/markSent/markFailed para controle de estado
 * 
 * Janela de envio: 08:00–12:00, apenas dias úteis (seg-sex)
 */
class BillingDispatchQueueService
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /** Janela de envio */
    public const WINDOW_START_HOUR = 8;
    public const WINDOW_END_HOUR = 12;

    /**
     * Enfileira um envio de cobrança com horário agendado.
     * Idempotente: não enfileira se já existe job queued/processing para o mesmo tenant+regra hoje.
     * 
     * @param int $tenantId
     * @param array $invoiceIds IDs das faturas elegíveis
     * @param int|null $dispatchRuleId
     * @param string $channel 'whatsapp'|'email'|'both'
     * @param \DateTime $scheduledAt Horário agendado para envio
     * @param string|null $message Mensagem pré-montada (null = montar na hora)
     * @return int|null ID do job criado, ou null se duplicado
     */
    public static function enqueue(
        int $tenantId,
        array $invoiceIds,
        ?int $dispatchRuleId,
        string $channel,
        \DateTime $scheduledAt,
        ?string $message = null
    ): ?int {
        $db = DB::getConnection();

        // Idempotência: verifica se já existe job para este tenant+regra hoje
        $today = (new \DateTime())->format('Y-m-d');
        $stmt = $db->prepare("
            SELECT id FROM billing_dispatch_queue
            WHERE tenant_id = ?
              AND dispatch_rule_id <=> ?
              AND DATE(created_at) = ?
              AND status IN ('queued', 'processing')
        ");
        $stmt->execute([$tenantId, $dispatchRuleId, $today]);
        if ($stmt->fetch()) {
            return null; // Já enfileirado hoje
        }

        $stmt = $db->prepare("
            INSERT INTO billing_dispatch_queue
            (tenant_id, invoice_ids, dispatch_rule_id, channel, message, status, scheduled_at, attempts, max_attempts)
            VALUES (?, ?, ?, ?, ?, 'queued', ?, 0, 3)
        ");
        $stmt->execute([
            $tenantId,
            json_encode($invoiceIds),
            $dispatchRuleId,
            $channel,
            $message,
            $scheduledAt->format('Y-m-d H:i:s'),
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Busca jobs prontos para envio: queued + scheduled_at <= NOW() + dentro da janela horária.
     * 
     * @param int $limit Máximo de jobs por execução
     * @return array Lista de jobs
     */
    public static function fetchReady(int $limit = 10): array
    {
        $db = DB::getConnection();

        // Só busca se estamos dentro da janela de envio
        $currentHour = (int) date('G');
        if ($currentHour < self::WINDOW_START_HOUR || $currentHour >= self::WINDOW_END_HOUR) {
            return []; // Fora da janela
        }

        // Não processa em fins de semana
        $dayOfWeek = (int) date('N'); // 1=seg, 7=dom
        if ($dayOfWeek >= 6) {
            return [];
        }

        $stmt = $db->prepare("
            SELECT *
            FROM billing_dispatch_queue
            WHERE status = 'queued'
              AND scheduled_at <= NOW()
              AND attempts < max_attempts
            ORDER BY scheduled_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marca job como em processamento (lock otimista).
     * 
     * @param int $jobId
     * @return bool true se conseguiu o lock
     */
    public static function markProcessing(int $jobId): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            UPDATE billing_dispatch_queue
            SET status = 'processing', attempts = attempts + 1, last_attempt_at = NOW(), updated_at = NOW()
            WHERE id = ? AND status IN ('queued', 'processing')
        ");
        $stmt->execute([$jobId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Marca job como enviado com sucesso.
     */
    public static function markSent(int $jobId): void
    {
        DB::getConnection()->prepare("
            UPDATE billing_dispatch_queue SET status = 'sent', updated_at = NOW() WHERE id = ?
        ")->execute([$jobId]);
    }

    /**
     * Marca job como falho.
     * Se attempts < max_attempts, volta para 'queued' para retry.
     * Se esgotou tentativas, marca como 'failed' definitivo.
     */
    public static function markFailed(int $jobId, string $errorMessage): void
    {
        $db = DB::getConnection();
        
        // Verifica se ainda tem tentativas
        $stmt = $db->prepare("SELECT attempts, max_attempts FROM billing_dispatch_queue WHERE id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($job && (int) $job['attempts'] < (int) $job['max_attempts']) {
            // Retry: volta para queued com erro registrado
            $stmt = $db->prepare("
                UPDATE billing_dispatch_queue
                SET status = 'queued', error_message = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([mb_substr($errorMessage, 0, 500), $jobId]);
        } else {
            // Esgotou tentativas: failed definitivo
            $stmt = $db->prepare("
                UPDATE billing_dispatch_queue
                SET status = 'failed', error_message = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([mb_substr($errorMessage, 0, 500), $jobId]);
        }
    }

    /**
     * Calcula horários de envio distribuídos na janela da manhã.
     * 
     * Ex: 10 cobranças → distribuídas em intervalos de ~24 min entre 08:00 e 12:00
     * Ex: 2 cobranças → 08:00 e 08:05 (mínimo de 3 min entre envios)
     * 
     * @param int $totalItems Quantidade total de envios a distribuir
     * @param string $date Data no formato Y-m-d (padrão: hoje)
     * @return \DateTime[] Array de horários agendados
     */
    public static function calculateScheduledTimes(int $totalItems, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $windowStart = new \DateTime("{$date} " . str_pad(self::WINDOW_START_HOUR, 2, '0', STR_PAD_LEFT) . ':00:00');
        $windowEnd = new \DateTime("{$date} " . str_pad(self::WINDOW_END_HOUR, 2, '0', STR_PAD_LEFT) . ':00:00');

        if ($totalItems <= 0) {
            return [];
        }

        if ($totalItems === 1) {
            return [$windowStart];
        }

        $windowSeconds = $windowEnd->getTimestamp() - $windowStart->getTimestamp(); // 14400s (4h)
        $minIntervalSeconds = 180; // Mínimo 3 minutos entre envios

        // Calcula intervalo ideal
        $idealInterval = (int) floor($windowSeconds / $totalItems);
        $interval = max($idealInterval, $minIntervalSeconds);

        $times = [];
        for ($i = 0; $i < $totalItems; $i++) {
            $offsetSeconds = $i * $interval;
            
            // Se ultrapassar a janela, acumula no final (não envia fora da janela)
            if ($offsetSeconds > $windowSeconds) {
                $offsetSeconds = $windowSeconds - ($minIntervalSeconds * ($totalItems - $i));
                if ($offsetSeconds < 0) $offsetSeconds = $windowSeconds;
            }

            $time = clone $windowStart;
            $time->modify("+{$offsetSeconds} seconds");
            
            // Garante que não passa da janela
            if ($time > $windowEnd) {
                $time = clone $windowEnd;
                $time->modify('-1 minute');
            }
            
            $times[] = $time;
        }

        return $times;
    }

    /**
     * Verifica se hoje é dia útil (seg-sex)
     */
    public static function isBusinessDay(?\DateTime $date = null): bool
    {
        $date = $date ?? new \DateTime();
        $dayOfWeek = (int) $date->format('N'); // 1=seg, 7=dom
        return $dayOfWeek <= 5;
    }

    /**
     * Verifica se estamos dentro da janela de envio
     */
    public static function isWithinSendWindow(): bool
    {
        $hour = (int) date('G');
        return $hour >= self::WINDOW_START_HOUR && $hour < self::WINDOW_END_HOUR;
    }

    /**
     * Conta jobs pendentes na fila para hoje
     */
    public static function countTodayPending(): int
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT COUNT(*) FROM billing_dispatch_queue
            WHERE DATE(created_at) = CURDATE()
              AND status IN ('queued', 'processing')
        ");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resumo do dia: contadores por status
     */
    public static function todaySummary(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT status, COUNT(*) as cnt
            FROM billing_dispatch_queue
            WHERE DATE(created_at) = CURDATE()
            GROUP BY status
        ");
        $summary = ['queued' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $summary[$row['status']] = (int) $row['cnt'];
        }
        return $summary;
    }
}
