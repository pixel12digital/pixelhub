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

        // Verifica se a tabela existe
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'communication_events'");
            if ($checkStmt->rowCount() === 0) {
                throw new \RuntimeException(
                    'Tabela communication_events não existe. Execute a migration: 20250201_create_communication_events_table'
                );
            }
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'Erro ao verificar se tabela communication_events existe: ' . $e->getMessage()
            );
        }

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

        // 🔍 PASSO 5: DEDUPLICAÇÃO - Log obrigatório quando descartar
        $existing = self::findByIdempotencyKey($idempotencyKey);
        if ($existing) {
            // Evento já processado, retorna event_id existente
            $messageId = $eventData['payload']['id'] 
                ?? $eventData['payload']['messageId'] 
                ?? $eventData['payload']['message_id'] 
                ?? $eventData['payload']['message']['id'] ?? 'NULL';
            $payloadHash = substr(md5(json_encode($eventData['payload'], \JSON_UNESCAPED_UNICODE)), 0, 8);
            
            error_log(sprintf(
                '[HUB_MSG_DROP] DROP_DUPLICATE reason=idempotency_key_match idempotency_key=%s existing_event_id=%s message_id=%s payload_hash=%s',
                $idempotencyKey,
                $existing['event_id'],
                $messageId,
                $payloadHash
            ));
            
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
        
        // Valida tenant_id se fornecido (verifica se existe na tabela tenants)
        if ($tenantId !== null) {
            try {
                $checkTenantStmt = $db->prepare("SELECT id FROM tenants WHERE id = ? LIMIT 1");
                $checkTenantStmt->execute([$tenantId]);
                if ($checkTenantStmt->rowCount() === 0) {
                    // Se tenant não existe, permite continuar mas loga aviso (foreign key permitirá NULL)
                    error_log("[EventIngestionService::ingest] AVISO: tenant_id {$tenantId} não encontrado na tabela tenants. Continuando com tenant_id=NULL.");
                    $tenantId = null; // Define como null para evitar erro de foreign key
                }
            } catch (\PDOException $e) {
                // Se erro ao verificar tenant, loga mas continua (pode ser problema temporário)
                error_log("[EventIngestionService::ingest] AVISO: Erro ao verificar tenant_id {$tenantId}: " . $e->getMessage() . ". Continuando...");
                $tenantId = null;
            }
        }
        
        // Converte payload para JSON com validação
        $payloadJson = json_encode($payload, \JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false) {
            $jsonError = json_last_error_msg();
            throw new \InvalidArgumentException("Erro ao serializar payload para JSON: {$jsonError}");
        }
        
        // Converte metadata para JSON com validação (pode ser null)
        $metadataJson = null;
        if (!empty($eventData['metadata'])) {
            $metadataJson = json_encode($eventData['metadata'], \JSON_UNESCAPED_UNICODE);
            if ($metadataJson === false) {
                $jsonError = json_last_error_msg();
                throw new \InvalidArgumentException("Erro ao serializar metadata para JSON: {$jsonError}");
            }
        }

        // 🔍 PASSO 6: MESSAGE DIRECTION - Garantir que está correta
        $direction = 'unknown';
        if (strpos($eventType, 'inbound') !== false) {
            $direction = 'received';
        } elseif (strpos($eventType, 'outbound') !== false) {
            $direction = 'outbound';
        }
        
        // Se veio do webhook do WhatsApp, default = received (a menos que seja explicitamente outbound)
        if ($sourceSystem === 'wpp_gateway' && $direction === 'unknown') {
            $direction = 'received'; // Default para webhook
        }
        
        error_log(sprintf(
            '[HUB_MSG_DIRECTION] computed=%s source=%s event_type=%s',
            $direction,
            $sourceSystem === 'wpp_gateway' ? 'webhook' : 'send_api',
            $eventType
        ));

        // 🔍 PASSO 7: PERSISTÊNCIA - Log antes e depois do insert
        $messageId = $eventData['payload']['id'] 
            ?? $eventData['payload']['messageId'] 
            ?? $eventData['payload']['message_id'] 
            ?? $eventData['payload']['message']['id'] ?? null;
        $channelId = $eventData['metadata']['channel_id'] ?? null;
        
        error_log(sprintf(
            '[HUB_MSG_SAVE] INSERT_ATTEMPT event_id=%s message_id=%s event_type=%s tenant_id=%s channel_id=%s direction=%s',
            $eventId,
            $messageId ?: 'NULL',
            $eventType,
            $tenantId ?: 'NULL',
            $channelId ?: 'NULL',
            $direction
        ));
        
        // Insere evento
        try {
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
            
            // Busca ID (PK) criado para log completo
            $stmt = $db->prepare("SELECT id, created_at FROM communication_events WHERE event_id = ? LIMIT 1");
            $stmt->execute([$eventId]);
            $createdEvent = $stmt->fetch();
            $idPk = $createdEvent ? $createdEvent['id'] : 'NULL';
            $createdAt = $createdEvent ? $createdEvent['created_at'] : 'NULL';
            
            // 🔍 PASSO 7: PERSISTÊNCIA - Log de sucesso
            error_log(sprintf(
                '[HUB_MSG_SAVE_OK] event_id=%s id_pk=%s message_id=%s conversation_id=verificar_no_resolve channel_id=%s created_at=%s direction=%s',
                $eventId,
                $idPk,
                $messageId ?: 'NULL',
                $channelId ?: 'NULL',
                $createdAt,
                $direction
            ));
            
        } catch (\PDOException $e) {
            // 🔍 PASSO 7: PERSISTÊNCIA - Log de erro (sem engolir)
            error_log(sprintf(
                '[HUB_MSG_SAVE] INSERT_FAILED event_id=%s message_id=%s error=%s sql_state=%s',
                $eventId,
                $messageId ?: 'NULL',
                $e->getMessage(),
                $e->getCode()
            ));
            error_log("[EventIngestionService::ingest] PDOException: " . $e->getMessage());
            error_log("[EventIngestionService::ingest] SQL State: " . $e->getCode());
            error_log("[EventIngestionService::ingest] Payload JSON: " . substr($payloadJson, 0, 200));
            error_log("[EventIngestionService::ingest] Metadata JSON: " . ($metadataJson ? substr($metadataJson, 0, 200) : 'NULL'));
            
            // Re-lança como RuntimeException com mensagem mais clara
            throw new \RuntimeException(
                "Erro ao inserir evento no banco de dados: " . $e->getMessage() . 
                " (SQL State: " . $e->getCode() . ")"
            );
        }

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

        // Etapa 1: Resolve conversa (incremental, não quebra se falhar)
        // 🔍 LOG TEMPORÁRIO: Rastreamento de chamada
        error_log(sprintf(
            '[DIAGNOSTICO] EventIngestion::ingest() - CHAMANDO resolveConversation: event_id=%s, event_type=%s, tenant_id=%s',
            $eventId,
            $eventType,
            $tenantId ?: 'NULL'
        ));
        
        try {
            $conversation = \PixelHub\Services\ConversationService::resolveConversation([
                'event_type' => $eventType,
                'source_system' => $sourceSystem,
                'tenant_id' => $tenantId,
                'payload' => $payload,
                'metadata' => !empty($eventData['metadata']) ? $eventData['metadata'] : null,
            ]);
            
            // 🔍 LOG TEMPORÁRIO: Resultado da chamada
            if ($conversation) {
                error_log(sprintf(
                    '[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU: conversation_id=%d, conversation_key=%s',
                    $conversation['id'],
                    $conversation['conversation_key'] ?? 'NULL'
                ));
            } else {
                error_log(sprintf(
                    '[DIAGNOSTICO] EventIngestion::ingest() - resolveConversation RETORNOU NULL para event_id=%s',
                    $eventId
                ));
            }

            if ($conversation) {
                // 🔍 PASSO 7: PERSISTÊNCIA - Atualiza log com conversation_id
                error_log(sprintf(
                    '[HUB_MSG_SAVE_OK] conversation_id=%d event_id=%s message_id=%s',
                    $conversation['id'],
                    $eventId,
                    $messageId ?: 'NULL'
                ));
                
                if (function_exists('pixelhub_log')) {
                    pixelhub_log(sprintf(
                        '[EventIngestion] Conversa resolvida: conversation_id=%d, conversation_key=%s',
                        $conversation['id'],
                        $conversation['conversation_key']
                    ));
                }
                
                // Marca evento como processado quando conversa foi resolvida com sucesso
                self::updateStatus($eventId, 'processed');
            } else {
                error_log(sprintf(
                    '[HUB_MSG_SAVE_OK] conversation_id=NULL event_id=%s message_id=%s reason=conversation_not_resolved',
                    $eventId,
                    $messageId ?: 'NULL'
                ));
                
                // Marca evento como falho quando conversa não foi resolvida
                self::updateStatus($eventId, 'failed', 'conversation_not_resolved');
            }
        } catch (\Exception $e) {
            // Não quebra fluxo se resolver conversa falhar
            error_log("[EventIngestion] Erro ao resolver conversa (não crítico): " . $e->getMessage());
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
        // Ordena as chaves do payload recursivamente para garantir hash consistente
        $payloadSorted = self::sortArrayKeysRecursive($payload);
        $payloadHash = md5(json_encode($payloadSorted, \JSON_UNESCAPED_UNICODE));
        return sprintf('%s:%s:%s', $sourceSystem, $eventType, $payloadHash);
    }

    /**
     * Ordena recursivamente as chaves de um array para garantir hash consistente
     * 
     * @param array $array Array para ordenar
     * @return array Array com chaves ordenadas
     */
    private static function sortArrayKeysRecursive(array $array): array
    {
        ksort($array);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sortArrayKeysRecursive($value);
            }
        }
        return $array;
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

