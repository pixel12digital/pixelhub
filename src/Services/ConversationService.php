<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para gerenciar conversas (núcleo conversacional central)
 * 
 * Etapa 1: Resolvedor de conversa - identifica, cria e atualiza conversas
 * sem alterar fluxos existentes.
 */
class ConversationService
{
    /**
     * Resolve ou cria uma conversa baseado em um evento
     * 
     * Este método é o "resolvedor de conversa" - identifica se já existe
     * uma conversa ou cria uma nova, sem aplicar regras de negócio.
     * 
     * @param array $eventData Dados do evento (após ingestão):
     *   - event_type (string)
     *   - source_system (string)
     *   - tenant_id (int|null)
     *   - payload (array)
     *   - metadata (array|null)
     * @return array|null Conversa encontrada/criada ou null se não aplicável
     */
    public static function resolveConversation(array $eventData): ?array
    {
        // Apenas eventos de mensagem geram conversas
        $eventType = $eventData['event_type'] ?? null;
        if (!$eventType || !self::isMessageEvent($eventType)) {
            return null;
        }

        $db = DB::getConnection();

        // Extrai informações do evento
        $channelInfo = self::extractChannelInfo($eventData);
        if (!$channelInfo) {
            return null; // Não é possível identificar canal
        }

        // Gera chave única da conversa
        $conversationKey = self::generateConversationKey(
            $channelInfo['channel_type'],
            $channelInfo['channel_account_id'],
            $channelInfo['contact_external_id']
        );

        // Busca conversa existente (por chave exata)
        $existing = self::findByKey($conversationKey);
        
        if ($existing) {
            // Atualiza metadados básicos
            self::updateConversationMetadata($existing['id'], $eventData, $channelInfo);
            return $existing;
        }

        // Se não encontrou por chave exata, tenta encontrar conversa equivalente
        // (para evitar duplicidade por variação do 9º dígito em números BR)
        $equivalent = self::findEquivalentConversation($channelInfo, $channelInfo['contact_external_id']);
        if ($equivalent) {
            // Encontrou conversa equivalente - atualiza ao invés de criar nova
            error_log(sprintf(
                "[ConversationService] Conversa equivalente encontrada: conversation_id=%d (contact_external_id: %s -> %s)",
                $equivalent['id'],
                $equivalent['contact_external_id'],
                $channelInfo['contact_external_id']
            ));
            self::updateConversationMetadata($equivalent['id'], $eventData, $channelInfo);
            return $equivalent;
        }

        // Cria nova conversa
        return self::createConversation($conversationKey, $eventData, $channelInfo);
    }

    /**
     * Verifica se evento é de mensagem (inbound ou outbound)
     */
    private static function isMessageEvent(string $eventType): bool
    {
        $messageEvents = [
            'whatsapp.inbound.message',
            'whatsapp.outbound.message',
            'email.inbound.message',
            'email.outbound.message',
            'webchat.inbound.message',
            'webchat.outbound.message',
        ];

        return in_array($eventType, $messageEvents, true);
    }

    /**
     * Extrai informações do canal a partir do evento
     */
    private static function extractChannelInfo(array $eventData): ?array
    {
        $eventType = $eventData['event_type'] ?? '';
        $payload = $eventData['payload'] ?? [];
        $metadata = $eventData['metadata'] ?? [];
        $tenantId = $eventData['tenant_id'] ?? null;

        // Detecta tipo de canal
        $channelType = null;
        if (strpos($eventType, 'whatsapp.') === 0) {
            $channelType = 'whatsapp';
        } elseif (strpos($eventType, 'email.') === 0) {
            $channelType = 'email';
        } elseif (strpos($eventType, 'webchat.') === 0) {
            $channelType = 'webchat';
        }

        if (!$channelType) {
            return null;
        }

        // Extrai contact_external_id (telefone, e-mail, etc.)
        $contactExternalId = null;
        $contactName = null;

        if ($channelType === 'whatsapp') {
            // WhatsApp: from ou to (depende da direção)
            $direction = strpos($eventType, 'inbound') !== false ? 'inbound' : 'outbound';
            if ($direction === 'inbound') {
                $contactExternalId = $payload['from'] ?? $payload['message']['from'] ?? null;
                $contactName = $payload['message']['notifyName'] ?? $payload['raw']['payload']['notifyName'] ?? null;
            } else {
                $contactExternalId = $payload['to'] ?? $payload['message']['to'] ?? null;
            }
            
            // Normaliza contact_external_id usando PhoneNormalizer (NÃO força "9")
            // Remove sufixo @c.us, @lid, etc. antes de normalizar
            if ($contactExternalId && strpos($contactExternalId, '@') !== false) {
                $contactExternalId = preg_replace('/@.*$/', '', $contactExternalId);
            }
            
            // Normaliza para E.164 (apenas dígitos, sem forçar "9")
            $contactExternalId = PhoneNormalizer::toE164OrNull($contactExternalId);
        } elseif ($channelType === 'email') {
            $direction = strpos($eventType, 'inbound') !== false ? 'inbound' : 'outbound';
            if ($direction === 'inbound') {
                $contactExternalId = $payload['from'] ?? null;
                $contactName = $payload['from_name'] ?? null;
            } else {
                $contactExternalId = $payload['to'] ?? null;
            }
        }

        if (!$contactExternalId) {
            return null;
        }

        // Resolve channel_account_id (se tenant_id disponível)
        $channelAccountId = null;
        if ($tenantId && $channelType === 'whatsapp') {
            $channelAccountId = self::resolveChannelAccountId($tenantId, $channelType);
        }

        // Extrai channel_id (session.id) do payload para eventos inbound de WhatsApp
        $channelId = null;
        if ($channelType === 'whatsapp' && ($direction ?? 'inbound') === 'inbound') {
            $channelId = self::extractChannelIdFromPayload($payload, $metadata);
        }

        return [
            'channel_type' => $channelType,
            'channel_account_id' => $channelAccountId,
            'channel_id' => $channelId,
            'contact_external_id' => $contactExternalId,
            'contact_name' => $contactName,
            'direction' => $direction ?? 'inbound',
        ];
    }

    /**
     * Extrai channel_id (session.id) do payload
     * 
     * @param array $payload Payload do evento
     * @param array|null $metadata Metadados do evento (pode conter channel_id)
     * @return string|null Channel ID ou null se não encontrado
     */
    private static function extractChannelIdFromPayload(array $payload, ?array $metadata = null): ?string
    {
        // Tenta obter de metadata primeiro (já normalizado)
        if ($metadata && isset($metadata['channel_id'])) {
            return (string) $metadata['channel_id'];
        }
        
        // Tenta obter do payload (session.id ou channel)
        $channelId = $payload['session']['id'] 
            ?? $payload['session']['session'] 
            ?? $payload['channel'] 
            ?? $payload['channelId'] 
            ?? null;
        
        return $channelId ? (string) $channelId : null;
    }

    /**
     * Resolve channel_account_id a partir de tenant_id e channel_type
     */
    private static function resolveChannelAccountId(?int $tenantId, string $channelType): ?int
    {
        if (!$tenantId) {
            return null;
        }

        $db = DB::getConnection();
        
        $provider = 'wpp_gateway'; // Por enquanto só WhatsApp
        if ($channelType !== 'whatsapp') {
            return null; // Outros canais ainda não mapeados
        }

        try {
            $stmt = $db->prepare("
                SELECT id 
                FROM tenant_message_channels 
                WHERE tenant_id = ? 
                AND provider = ? 
                AND is_enabled = 1
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $provider]);
            $result = $stmt->fetch();
            
            return $result ? (int) $result['id'] : null;
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao resolver channel_account_id: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gera chave única da conversa
     */
    private static function generateConversationKey(
        string $channelType,
        ?int $channelAccountId,
        string $contactExternalId
    ): string {
        $accountPart = $channelAccountId ?: 'shared';
        return sprintf('%s_%s_%s', $channelType, $accountPart, $contactExternalId);
    }

    /**
     * Busca conversa por chave
     */
    private static function findByKey(string $conversationKey): ?array
    {
        $db = DB::getConnection();
        
        try {
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE conversation_key = ? 
                LIMIT 1
            ");
            $stmt->execute([$conversationKey]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            // Tabela pode não existir ainda (migration não executada)
            error_log("[ConversationService] Erro ao buscar conversa: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria nova conversa
     */
    private static function createConversation(
        string $conversationKey,
        array $eventData,
        array $channelInfo
    ): ?array {
        $db = DB::getConnection();

        // Verifica se tabela existe
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'conversations'");
            if ($checkStmt->rowCount() === 0) {
                // Tabela não existe ainda - retorna null (não quebra fluxo)
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }

        $tenantId = $eventData['tenant_id'] ?? null;
        $direction = $channelInfo['direction'] ?? 'inbound';
        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $db->prepare("
                INSERT INTO conversations 
                (conversation_key, channel_type, channel_account_id, channel_id, contact_external_id, 
                 contact_name, tenant_id, status, last_message_at, last_message_direction,
                 message_count, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, 1, ?, ?)
            ");

            $stmt->execute([
                $conversationKey,
                $channelInfo['channel_type'],
                $channelInfo['channel_account_id'],
                $channelInfo['channel_id'] ?? null,
                $channelInfo['contact_external_id'],
                $channelInfo['contact_name'],
                $tenantId,
                $now,
                $direction,
                $now,
                $now
            ]);

            $conversationId = (int) $db->lastInsertId();
            return self::findById($conversationId);
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao criar conversa: " . $e->getMessage());
            return null; // Não quebra fluxo se falhar
        }
    }

    /**
     * Atualiza metadados básicos da conversa
     */
    private static function updateConversationMetadata(
        int $conversationId,
        array $eventData,
        array $channelInfo
    ): void {
        $db = DB::getConnection();
        $direction = $channelInfo['direction'] ?? 'inbound';
        $now = date('Y-m-d H:i:s');

        try {
            // Atualiza última mensagem e contador
            $stmt = $db->prepare("
                UPDATE conversations 
                SET last_message_at = ?,
                    last_message_direction = ?,
                    message_count = message_count + 1,
                    unread_count = CASE 
                        WHEN ? = 'inbound' THEN unread_count + 1 
                        ELSE unread_count 
                    END,
                    status = CASE 
                        WHEN status = 'closed' THEN 'open'
                        ELSE status
                    END,
                    updated_at = ?
                WHERE id = ?
            ");

            $stmt->execute([$now, $direction, $direction, $now, $conversationId]);

            // Atualiza contato name se fornecido e ainda não existe
            if (!empty($channelInfo['contact_name'])) {
                $updateNameStmt = $db->prepare("
                    UPDATE conversations 
                    SET contact_name = ? 
                    WHERE id = ? AND (contact_name IS NULL OR contact_name = '')
                ");
                $updateNameStmt->execute([$channelInfo['contact_name'], $conversationId]);
            }

            // Atualiza tenant_id se fornecido e ainda não existe
            $tenantId = $eventData['tenant_id'] ?? null;
            if ($tenantId) {
                $updateTenantStmt = $db->prepare("
                    UPDATE conversations 
                    SET tenant_id = ? 
                    WHERE id = ? AND tenant_id IS NULL
                ");
                $updateTenantStmt->execute([$tenantId, $conversationId]);
            }

            // Atualiza channel_id se fornecido e ainda não existe (apenas para eventos inbound)
            $channelId = $channelInfo['channel_id'] ?? null;
            if ($channelId && ($channelInfo['direction'] ?? 'inbound') === 'inbound') {
                $updateChannelIdStmt = $db->prepare("
                    UPDATE conversations 
                    SET channel_id = ? 
                    WHERE id = ? AND (channel_id IS NULL OR channel_id = '')
                ");
                $updateChannelIdStmt->execute([$channelId, $conversationId]);
            }
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao atualizar conversa: " . $e->getMessage());
            // Não quebra fluxo se falhar
        }
    }

    /**
     * Busca conversa equivalente (para evitar duplicidade por variação do 9º dígito)
     * 
     * Aplica apenas para números BR (DDD 55) e apenas quando o padrão bate
     * (55 + DDD + 8/9 dígitos). Tenta encontrar conversa removendo/adicionando o 9º dígito.
     * 
     * @param array $channelInfo Informações do canal
     * @param string $contactExternalId ID externo do contato (E.164 normalizado)
     * @return array|null Conversa equivalente ou null se não encontrada
     */
    private static function findEquivalentConversation(array $channelInfo, string $contactExternalId): ?array
    {
        // Aplica apenas para WhatsApp e números BR
        if ($channelInfo['channel_type'] !== 'whatsapp') {
            return null;
        }

        // Verifica se é número BR (começa com 55)
        if (strlen($contactExternalId) < 12 || substr($contactExternalId, 0, 2) !== '55') {
            return null;
        }

        // Extrai DDD e número
        $ddd = substr($contactExternalId, 2, 2);
        $number = substr($contactExternalId, 4);
        $numberLen = strlen($number);

        // Aplica apenas para números com 8 ou 9 dígitos (após DDD)
        if ($numberLen !== 8 && $numberLen !== 9) {
            return null;
        }

        // Gera variação do número (adiciona ou remove 9º dígito)
        $variantContactId = null;
        if ($numberLen === 8) {
            // Número com 8 dígitos: tenta adicionar 9º dígito (9 + número)
            $variantContactId = '55' . $ddd . '9' . $number;
        } elseif ($numberLen === 9) {
            // Número com 9 dígitos: tenta remover 9º dígito (remove primeiro dígito)
            $variantContactId = '55' . $ddd . substr($number, 1);
        }

        if (!$variantContactId) {
            return null;
        }

        // Gera chave de conversa equivalente
        $variantKey = self::generateConversationKey(
            $channelInfo['channel_type'],
            $channelInfo['channel_account_id'],
            $variantContactId
        );

        // Busca conversa com a chave variante
        return self::findByKey($variantKey);
    }

    /**
     * Busca conversa por ID
     */
    public static function findById(int $conversationId): ?array
    {
        $db = DB::getConnection();
        
        try {
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE id = ? 
                LIMIT 1
            ");
            $stmt->execute([$conversationId]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Busca conversa por chave
     */
    public static function findByConversationKey(string $conversationKey): ?array
    {
        return self::findByKey($conversationKey);
    }
}

