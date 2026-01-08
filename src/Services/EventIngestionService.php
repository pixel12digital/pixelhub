<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para ingestão de eventos no sistema centralizado
 */
class EventIngestionService
{
    /**
     * Ingere um evento no sistema
     * 
     * @param array $eventData Dados do evento:
     *   - event_type (string, obrigatório)
     *   - source_system (string, obrigatório)
     *   - payload (array, obrigatório)
     *   - tenant_id (int|null, opcional)
     *   - trace_id (string|null, opcional - gera se não fornecido)
     *   - correlation_id (string|null, opcional)
     *   - metadata (array|null, opcional)
     * @return string Event ID (UUID)
     * @throws \Exception Se evento duplicado (idempotência)
     */
    public static function ingest(array $eventData): string
    {
        $db = DB::getConnection();

        // Valida campos obrigatórios
        $eventType = $eventData['event_type'] ?? null;
        $sourceSystem = $eventData['source_system'] ?? null;
        $payload = $eventData['payload'] ?? null;

        if (empty($eventType) || empty($sourceSystem) || $payload === null) {
            throw new \InvalidArgumentException('event_type, source_system e payload são obrigatórios');
        }

        // Gera IDs
        $eventId = self::generateUuid();
        $traceId = $eventData['trace_id'] ?? self::generateUuid();
        $correlationId = $eventData['correlation_id'] ?? null;

        // Calcula idempotency_key
        $idempotencyKey = self::calculateIdempotencyKey(
            $sourceSystem,
            $eventData['payload'] ?? [],
            $eventType
        );

        // Verifica idempotência
        $existing = self::findByIdempotencyKey($idempotencyKey);
        if ($existing) {
            // Evento já processado, retorna event_id existente
            if (function_exists('pixelhub_log')) {
                pixelhub_log(sprintf(
                    '[EventIngestion] Evento duplicado ignorado (idempotency_key: %s, event_id: %s)',
                    $idempotencyKey,
                    $existing['event_id']
                ));
            }
            return $existing['event_id'];
        }

        // Prepara dados
        $tenantId = !empty($eventData['tenant_id']) ? (int) $eventData['tenant_id'] : null;
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $metadataJson = !empty($eventData['metadata']) 
            ? json_encode($eventData['metadata'], JSON_UNESCAPED_UNICODE) 
            : null;

        // Insere evento
        $stmt = $db->prepare("
            INSERT INTO communication_events 
            (event_id, idempotency_key, event_type, source_system, tenant_id, 
             trace_id, correlation_id, payload, metadata, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued', NOW(), NOW())
        ");

        $stmt->execute([
            $eventId,
            $idempotencyKey,
            $eventType,
            $sourceSystem,
            $tenantId,
            $traceId,
            $correlationId,
            $payloadJson,
            $metadataJson
        ]);

        // Log
        if (function_exists('pixelhub_log')) {
            pixelhub_log(sprintf(
                '[EventIngestion] Evento ingerido: %s (event_id: %s, trace_id: %s, tenant_id: %s)',
                $eventType,
                $eventId,
                $traceId,
                $tenantId ?: 'NULL'
            ));
        }

        return $eventId;
    }

    /**
     * Calcula chave de idempotência
     * 
     * @param string $sourceSystem Sistema de origem
     * @param array $payload Payload do evento
     * @param string $eventType Tipo do evento
     * @return string Chave de idempotência
     */
    private static function calculateIdempotencyKey(string $sourceSystem, array $payload, string $eventType): string
    {
        // Tenta extrair ID externo do payload
        $externalId = $payload['id'] 
            ?? $payload['external_id'] 
            ?? $payload['messageId'] 
            ?? $payload['message_id']
            ?? $payload['payment_id']
            ?? null;

        // Se tiver external_id, usa ele
        if ($externalId !== null) {
            return sprintf('%s:%s:%s', $sourceSystem, $eventType, $externalId);
        }

        // Senão, usa hash do payload
        $payloadHash = md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_SORT_KEYS));
        return sprintf('%s:%s:%s', $sourceSystem, $eventType, $payloadHash);
    }

    /**
     * Busca evento por idempotency_key
     * 
     * @param string $idempotencyKey Chave de idempotência
     * @return array|null Evento encontrado ou null
     */
    private static function findByIdempotencyKey(string $idempotencyKey): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT event_id, status 
            FROM communication_events 
            WHERE idempotency_key = ? 
            LIMIT 1
        ");
        $stmt->execute([$idempotencyKey]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Busca evento por event_id
     * 
     * @param string $eventId UUID do evento
     * @return array|null Evento encontrado ou null
     */
    public static function findByEventId(string $eventId): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM communication_events 
            WHERE event_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Atualiza status do evento
     * 
     * @param string $eventId UUID do evento
     * @param string $status Novo status
     * @param string|null $errorMessage Mensagem de erro (se falhou)
     * @return bool Sucesso
     */
    public static function updateStatus(string $eventId, string $status, ?string $errorMessage = null): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            UPDATE communication_events 
            SET status = ?, 
                processed_at = CASE WHEN ? IN ('processed', 'failed') THEN NOW() ELSE processed_at END,
                error_message = ?,
                updated_at = NOW()
            WHERE event_id = ?
        ");
        return $stmt->execute([$status, $status, $errorMessage, $eventId]);
    }

    /**
     * Gera UUID v4
     * 
     * @return string UUID
     */
    private static function generateUuid(): string
    {
        // PHP 8+ tem random_bytes, mas vamos garantir compatibilidade
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant bits
        
        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}

