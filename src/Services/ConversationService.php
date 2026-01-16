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
        // 🔍 LOG TEMPORÁRIO: Entrada no método
        $payload = $eventData['payload'] ?? [];
        $from = $payload['from'] ?? $payload['message']['from'] ?? 'NULL';
        $to = $payload['to'] ?? $payload['message']['to'] ?? 'NULL';
        error_log(sprintf(
            '[DIAGNOSTICO] ConversationService::resolveConversation() - INICIADO: event_type=%s, from=%s, to=%s',
            $eventData['event_type'] ?? 'NULL',
            $from,
            $to
        ));
        
        // Apenas eventos de mensagem geram conversas
        $eventType = $eventData['event_type'] ?? null;
        if (!$eventType || !self::isMessageEvent($eventType)) {
            error_log('[DIAGNOSTICO] ConversationService::resolveConversation() - EARLY RETURN: não é evento de mensagem');
            return null;
        }

        $db = DB::getConnection();

        // Extrai informações do evento
        $channelInfo = self::extractChannelInfo($eventData);
        if (!$channelInfo) {
            error_log('[CONVERSATION UPSERT] ERRO: extractChannelInfo retornou NULL. Event data: ' . json_encode([
                'event_type' => $eventData['event_type'] ?? null,
                'source_system' => $eventData['source_system'] ?? null,
                'has_payload' => isset($eventData['payload']),
                'payload_keys' => isset($eventData['payload']) ? array_keys($eventData['payload']) : [],
            ], JSON_UNESCAPED_UNICODE));
            return null; // Não é possível identificar canal
        }

        // Gera chave única da conversa
        $conversationKey = self::generateConversationKey(
            $channelInfo['channel_type'],
            $channelInfo['channel_account_id'],
            $channelInfo['contact_external_id']
        );

        // 🔍 PASSO 2: LOG DE RESOLUÇÃO DE CONVERSA
        error_log('[CONVERSATION UPSERT] Iniciando resolução de conversa: ' . json_encode([
            'conversation_key' => $conversationKey,
            'channel_type' => $channelInfo['channel_type'],
            'channel_id' => $channelInfo['channel_id'] ?? null,
            'channel_account_id' => $channelInfo['channel_account_id'] ?? null,
            'contact_external_id' => $channelInfo['contact_external_id'],
            'tenant_id' => $eventData['tenant_id'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        
        // Log específico sobre resolução do canal
        if (!empty($channelInfo['channel_id'])) {
            error_log('[HUB_CHANNEL_RESOLUTION] channel_id resolvido: ' . $channelInfo['channel_id'] . ' -> channel_account_id: ' . ($channelInfo['channel_account_id'] ?? 'NULL'));
        } else {
            error_log('[HUB_CHANNEL_RESOLUTION] AVISO: channel_id não fornecido - usando fallback para primeiro canal');
        }

        // 🔍 PASSO 4: MATCH DE CONVERSA - Log detalhado da query
        $queryParams = [
            'conversation_key' => $conversationKey,
            'channel_type' => $channelInfo['channel_type'],
            'channel_id' => $channelInfo['channel_id'] ?? null,
            'contact_external_id' => $channelInfo['contact_external_id'],
            'tenant_id' => $eventData['tenant_id'] ?? null,
        ];
        error_log('[HUB_CONV_MATCH] Query: findByKey conversation_key=' . $conversationKey . ' channel_type=' . $channelInfo['channel_type'] . ' contact=' . $channelInfo['contact_external_id'] . ' tenant_id=' . ($eventData['tenant_id'] ?? 'NULL'));

        // Busca conversa existente (por chave exata)
        $existing = self::findByKey($conversationKey);
        
        if ($existing) {
            error_log('[HUB_CONV_MATCH] FOUND_CONVERSATION id=' . $existing['id'] . ' conversation_key=' . $conversationKey);
            // 🔍 LOG TEMPORÁRIO: Antes de atualizar
            error_log(sprintf(
                '[DIAGNOSTICO] ConversationService::resolveConversation() - ANTES updateConversationMetadata: conversation_id=%d, last_message_at=%s, unread_count=%d',
                $existing['id'],
                $existing['last_message_at'] ?? 'NULL',
                $existing['unread_count'] ?? 0
            ));
            // Atualiza metadados básicos
            self::updateConversationMetadata($existing['id'], $eventData, $channelInfo);
            // 🔍 LOG TEMPORÁRIO: Depois de atualizar (busca novamente para ver se mudou)
            $afterUpdate = self::findById($existing['id']);
            if ($afterUpdate) {
                error_log(sprintf(
                    '[DIAGNOSTICO] ConversationService::resolveConversation() - DEPOIS updateConversationMetadata: conversation_id=%d, last_message_at=%s, unread_count=%d',
                    $afterUpdate['id'],
                    $afterUpdate['last_message_at'] ?? 'NULL',
                    $afterUpdate['unread_count'] ?? 0
                ));
            }
            return $existing;
        }

        // Se não encontrou por chave exata, tenta encontrar conversa equivalente
        // (para evitar duplicidade por variação do 9º dígito em números BR)
        error_log('[HUB_CONV_MATCH] Query: findEquivalentConversation contact=' . $channelInfo['contact_external_id']);
        $equivalent = self::findEquivalentConversation($channelInfo, $channelInfo['contact_external_id']);
        if ($equivalent) {
            // Encontrou conversa equivalente - atualiza ao invés de criar nova
            error_log('[HUB_CONV_MATCH] FOUND_EQUIVALENT_CONVERSATION id=' . $equivalent['id'] . ' original_contact=' . $equivalent['contact_external_id'] . ' new_contact=' . $channelInfo['contact_external_id'] . ' reason=9th_digit_variation');
            self::updateConversationMetadata($equivalent['id'], $eventData, $channelInfo);
            return $equivalent;
        }
        
        // Se ainda não encontrou, tenta encontrar conversa com mesmo contato mas channel_account_id diferente
        // (ex.: conversa "shared" vs conversa com tenant específico)
        error_log('[HUB_CONV_MATCH] Query: findConversationByContactOnly contact=' . $channelInfo['contact_external_id']);
        $equivalentByContact = self::findConversationByContactOnly($channelInfo);
        if ($equivalentByContact) {
            // Se a conversa encontrada é "shared" (sem channel_account_id) e temos um channel_account_id,
            // atualiza ela ao invés de criar nova
            if (empty($equivalentByContact['channel_account_id']) && $channelInfo['channel_account_id']) {
                error_log('[HUB_CONV_MATCH] FOUND_SHARED_CONVERSATION id=' . $equivalentByContact['id'] . ' reason=updating_shared_with_channel_account_id');
                // Atualiza a conversa existente
                self::updateConversationMetadata($equivalentByContact['id'], $eventData, $channelInfo);
                // Atualiza channel_account_id e conversation_key
                self::updateChannelAccountId($equivalentByContact['id'], $channelInfo['channel_account_id']);
                // Busca novamente para retornar com dados atualizados
                return self::findById($equivalentByContact['id']);
            } elseif ($equivalentByContact['channel_account_id'] && $channelInfo['channel_account_id'] && 
                      $equivalentByContact['channel_account_id'] == $channelInfo['channel_account_id']) {
                // Mesma conversa com mesmo channel_account_id - apenas atualiza
                error_log('[HUB_CONV_MATCH] FOUND_CONVERSATION id=' . $equivalentByContact['id'] . ' reason=same_contact_and_channel_account_id');
                self::updateConversationMetadata($equivalentByContact['id'], $eventData, $channelInfo);
                return $equivalentByContact;
            }
            // Se channel_account_id é diferente, cria nova (comportamento esperado para múltiplos tenants)
        }

        // Cria nova conversa
        error_log('[HUB_CONV_MATCH] CREATED_CONVERSATION conversation_key=' . $conversationKey . ' channel_type=' . $channelInfo['channel_type'] . ' contact=' . $channelInfo['contact_external_id'] . ' channel_id=' . ($channelInfo['channel_id'] ?? 'NULL') . ' channel_account_id=' . ($channelInfo['channel_account_id'] ?? 'NULL'));
        $newConversation = self::createConversation($conversationKey, $eventData, $channelInfo);
        if ($newConversation) {
            error_log('[HUB_CONV_MATCH] CREATED_CONVERSATION id=' . $newConversation['id'] . ' conversation_key=' . $conversationKey . ' channel_id=' . ($newConversation['channel_id'] ?? 'NULL'));
        } else {
            error_log('[HUB_CONV_MATCH] ERROR: Falha ao criar nova conversa conversation_key=' . $conversationKey);
        }
        return $newConversation;
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

        error_log('[CONVERSATION UPSERT] extractChannelInfo: INICIANDO - event_type=' . $eventType . ', has_payload=' . (isset($eventData['payload']) ? 'SIM' : 'NÃO'));

        // Detecta tipo de canal
        $channelType = null;
        if (strpos($eventType, 'whatsapp.') === 0) {
            $channelType = 'whatsapp';
        } elseif (strpos($eventType, 'email.') === 0) {
            $channelType = 'email';
        } elseif (strpos($eventType, 'webchat.') === 0) {
            $channelType = 'webchat';
        }

        error_log('[CONVERSATION UPSERT] extractChannelInfo: channelType detectado=' . ($channelType ?: 'NULL'));

        if (!$channelType) {
            error_log('[CONVERSATION UPSERT] extractChannelInfo: ERRO - channelType é NULL, retornando null');
            return null;
        }

        // Extrai contact_external_id (telefone, e-mail, etc.)
        $contactExternalId = null;
        $contactName = null;

        if ($channelType === 'whatsapp') {
            // WhatsApp: from ou to (depende da direção)
            $direction = strpos($eventType, 'inbound') !== false ? 'inbound' : 'outbound';
            
            // Tenta extrair de múltiplas fontes (ordem de prioridade)
            $rawFrom = null;
            if ($direction === 'inbound') {
                $rawFrom = $payload['message']['from'] 
                    ?? $payload['from'] 
                    ?? $payload['data']['from'] 
                    ?? $payload['raw']['payload']['from']
                    ?? $payload['raw']['from'] ?? null;
                    
                $contactName = $payload['message']['notifyName'] 
                    ?? $payload['raw']['payload']['notifyName'] 
                    ?? $payload['raw']['payload']['sender']['verifiedName']
                    ?? $payload['raw']['payload']['sender']['name']
                    ?? $payload['data']['notifyName']
                    ?? $payload['notifyName'] ?? null;
            } else {
                $rawFrom = $payload['message']['to'] 
                    ?? $payload['to'] 
                    ?? $payload['data']['to']
                    ?? $payload['raw']['payload']['to']
                    ?? $payload['raw']['to'] ?? null;
            }
            
            error_log('[CONVERSATION UPSERT] extractChannelInfo: WhatsApp ' . $direction . ' - rawFrom: ' . ($rawFrom ?: 'NULL'));
            
            // Regra #2: Tratar grupos (@g.us)
            // Se o from termina com @g.us, é um grupo - precisa usar author/participant
            $isGroup = false;
            $groupJid = null;
            if ($rawFrom && strpos($rawFrom, '@g.us') !== false) {
                $isGroup = true;
                $groupJid = $rawFrom;
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Detectado GRUPO - groupJid: ' . $groupJid);
                
                // Tenta extrair participant/author (remetente dentro do grupo)
                $rawFrom = $payload['raw']['payload']['author'] 
                    ?? $payload['raw']['payload']['participant'] 
                    ?? $payload['message']['key']['participant']
                    ?? $payload['data']['participant']
                    ?? $payload['data']['author'] ?? null;
                
                if ($rawFrom) {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Participant extraído do grupo: ' . $rawFrom);
                } else {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: ERRO - Grupo sem participant/author. GroupJid: ' . $groupJid);
                    // Retorna erro específico para grupo sem participant
                    return null; // Será tratado como failed_missing_participant
                }
            }
            
            // Se ainda não encontrou, tenta extrair de mensagens encaminhadas
            if (!$rawFrom && isset($payload['message']['forwardedFrom'])) {
                $rawFrom = $payload['message']['forwardedFrom'];
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando forwardedFrom: ' . $rawFrom);
            }
            
            // Se não tem from válido, retorna erro específico
            if (!$rawFrom) {
                error_log('[CONVERSATION UPSERT] extractChannelInfo: ERRO - Payload sem from válido. Payload keys: ' . implode(', ', array_keys($payload)));
                return null; // Será tratado como failed_missing_from
            }
            
            $contactExternalId = $rawFrom;
            $originalContactId = $rawFrom;
            
            // Regra #3: Fallback por JID numérico (@c.us ou @s.whatsapp.net)
            // Se termina com @c.us ou @s.whatsapp.net, extrai o número diretamente
            $isNumericJid = false;
            if (strpos($rawFrom, '@c.us') !== false || strpos($rawFrom, '@s.whatsapp.net') !== false) {
                $isNumericJid = true;
                // Remove sufixo e extrai apenas dígitos
                $digitsOnly = preg_replace('/@.*$/', '', $rawFrom);
                $digitsOnly = preg_replace('/[^0-9]/', '', $digitsOnly);
                
                if (strlen($digitsOnly) >= 10) {
                    // Normaliza para E.164
                    $contactExternalId = PhoneNormalizer::toE164OrNull($digitsOnly);
                    if ($contactExternalId) {
                        error_log('[CONVERSATION UPSERT] extractChannelInfo: JID numérico extraído e normalizado: ' . $contactExternalId . ' (original: ' . $rawFrom . ')');
                    } else {
                        error_log('[CONVERSATION UPSERT] extractChannelInfo: Falha ao normalizar JID numérico: ' . $digitsOnly);
                        // Continua tentando mapeamento @lid se necessário
                    }
                } else {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: JID numérico com poucos dígitos: ' . $digitsOnly);
                }
            }
            
            // Se não foi JID numérico, verifica se é @lid
            $isLidId = false;
            if (!$isNumericJid && strpos($rawFrom, '@lid') !== false) {
                $isLidId = true;
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Detectado @lid - business_id: ' . $rawFrom);
                
                // Consulta mapeamento whatsapp_business_ids
                $db = \PixelHub\Core\DB::getConnection();
                $stmt = $db->prepare("
                    SELECT phone_number 
                    FROM whatsapp_business_ids 
                    WHERE business_id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$rawFrom]);
                $mapping = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($mapping && !empty($mapping['phone_number'])) {
                    $contactExternalId = $mapping['phone_number'];
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Mapeamento @lid encontrado - phone_number: ' . $contactExternalId);
                } else {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Mapeamento @lid NÃO encontrado para business_id: ' . $rawFrom);
                    // Continua tentando fallback
                }
            }
            
            // Fallback final: tenta normalizar o que sobrou
            if (!$contactExternalId || ($isLidId && !$contactExternalId)) {
                // Tenta normalizar o original
                $digitsOnly = preg_replace('/@.*$/', '', $originalContactId);
                $digitsOnly = preg_replace('/[^0-9]/', '', $digitsOnly);
                
                if (strlen($digitsOnly) >= 10) {
                    $contactExternalId = PhoneNormalizer::toE164OrNull($digitsOnly);
                    if ($contactExternalId) {
                        error_log('[CONVERSATION UPSERT] extractChannelInfo: Fallback final - extraído: ' . $contactExternalId . ' (original: ' . $originalContactId . ')');
                    }
                }
            }
            
            // CORREÇÃO: Se for @lid e não encontrou mapeamento, usa o @lid como contact_external_id
            // Isso permite criar conversa mesmo sem mapeamento (arquitetura remote_key)
            if (!$contactExternalId && $rawFrom && strpos($rawFrom, '@lid') !== false) {
                $contactExternalId = $rawFrom; // Usa @lid direto se não conseguiu mapear
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando @lid como contact_external_id (sem mapeamento): ' . $contactExternalId);
            }
            
            // Validação final: só retorna NULL se realmente não tem nenhum identificador
            if (!$contactExternalId && !$rawFrom) {
                error_log('[CONVERSATION UPSERT] extractChannelInfo: ERRO - Não foi possível extrair contact_external_id válido. RawFrom: ' . ($rawFrom ?: 'NULL') . ', IsGroup: ' . ($isGroup ? 'SIM' : 'NÃO'));
                return null;
            }
            
            // Se ainda não tem contactExternalId mas tem rawFrom, usa rawFrom como fallback final
            if (!$contactExternalId && $rawFrom) {
                $contactExternalId = $rawFrom;
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando rawFrom como contact_external_id (fallback final): ' . $contactExternalId);
            }
            
            error_log('[CONVERSATION UPSERT] extractChannelInfo: contact_external_id final: ' . $contactExternalId . ' (tipo: ' . ($isLidId ? '@lid' : ($isNumericJid ? 'JID numérico' : 'outro')) . ')');
        } elseif ($channelType === 'email') {
            $direction = strpos($eventType, 'inbound') !== false ? 'inbound' : 'outbound';
            if ($direction === 'inbound') {
                $contactExternalId = $payload['from'] ?? null;
                $contactName = $payload['from_name'] ?? null;
            } else {
                $contactExternalId = $payload['to'] ?? null;
            }
        }

        // FIX @lid: Fallback quando não há mapeamento - busca conversa existente por nome
        // Apenas para atualizar conversa existente, NÃO cria nova conversa
        if (!$contactExternalId && $channelType === 'whatsapp' && $direction === 'inbound') {
            $notifyName = $payload['message']['notifyName'] 
                ?? $payload['raw']['payload']['notifyName'] 
                ?? $payload['raw']['payload']['sender']['verifiedName'] 
                ?? $payload['raw']['payload']['sender']['name'] 
                ?? null;
            
            if ($notifyName && $tenantId) {
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Tentando fallback por nome - notifyName: ' . $notifyName . ', tenant_id: ' . $tenantId);
                
                // Busca conversa existente com mesmo nome e tenant (apenas uma)
                $db = \PixelHub\Core\DB::getConnection();
                $stmt = $db->prepare("
                    SELECT contact_external_id 
                    FROM conversations 
                    WHERE channel_type = 'whatsapp' 
                    AND tenant_id = ? 
                    AND contact_name = ?
                    LIMIT 1
                ");
                $stmt->execute([$tenantId, $notifyName]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existing && !empty($existing['contact_external_id'])) {
                    $contactExternalId = $existing['contact_external_id'];
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Fallback encontrou conversa existente - contact_external_id: ' . $contactExternalId);
                } else {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Fallback NÃO encontrou conversa existente para nome: ' . $notifyName);
                }
            }
        }
        
        if (!$contactExternalId) {
            error_log('[CONVERSATION UPSERT] ERRO: contactExternalId é NULL após extração. Channel type: ' . ($channelType ?: 'NULL') . ', Direction: ' . ($direction ?? 'NULL'));
            return null;
        }

        // Extrai channel_id (session.id) do payload para eventos inbound de WhatsApp
        // IMPORTANTE: Extrair ANTES de resolver channel_account_id para usar na busca
        $channelId = null;
        if ($channelType === 'whatsapp' && ($direction ?? 'inbound') === 'inbound') {
            $channelId = self::extractChannelIdFromPayload($payload, $metadata);
        }

        // Resolve channel_account_id usando o channel_id (sessionId) extraído
        // CORREÇÃO: Usa channel_id para buscar o canal correto no tenant_message_channels
        $channelAccountId = null;
        if ($tenantId && $channelType === 'whatsapp') {
            $channelAccountId = self::resolveChannelAccountId($tenantId, $channelType, $channelId);
        }

        // =====================
        // ARQUITETURA: remote_key como identidade primária
        // =====================
        
        // Função canônica: remote_key (nunca tenta converter @lid em telefone)
        $remoteKey = function($id) {
            if (empty($id)) return null;
            $id = trim((string)$id);
            
            // pnLid
            if (preg_match('/^([0-9]+)@lid$/', $id, $m)) {
                return 'lid:' . $m[1];
            }
            
            // JIDs comuns do WA: 5547...@c.us / @s.whatsapp.net etc
            if (strpos($id, '@') !== false) {
                // se começa com dígitos, normaliza para tel:<digits> (para unificar "5547..." e "5547...@c.us")
                $digits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $id));
                if ($digits !== '') {
                    return 'tel:' . $digits;
                }
                return 'jid:' . mb_strtolower($id, 'UTF-8');
            }
            
            // número puro
            $digits = preg_replace('/[^0-9]/', '', $id);
            if ($digits !== '') return 'tel:' . $digits;
            
            return 'raw:' . mb_strtolower($id, 'UTF-8');
        };
        
        // Calcula remote_id_raw e remote_key
        $rawContactId = null;
        if ($channelType === 'whatsapp') {
            $direction = $direction ?? 'inbound';
            if ($direction === 'inbound') {
                $rawContactId = $payload['message']['from'] 
                    ?? $payload['from'] 
                    ?? $payload['data']['from'] 
                    ?? $payload['raw']['payload']['from'] ?? null;
            } else {
                $rawContactId = $payload['message']['to'] 
                    ?? $payload['to'] 
                    ?? $payload['data']['to']
                    ?? $payload['raw']['payload']['to'] ?? null;
            }
            
            // Se for grupo, usa participant/author
            if ($rawContactId && strpos($rawContactId, '@g.us') !== false) {
                $rawContactId = $payload['raw']['payload']['author'] 
                    ?? $payload['raw']['payload']['participant'] 
                    ?? $payload['message']['key']['participant']
                    ?? $payload['data']['participant']
                    ?? $payload['data']['author'] ?? $rawContactId;
            }
        }
        
        // IMPORTANTE: remote_key deve ser calculado do ID ORIGINAL (rawContactId), 
        // não do contactExternalId mapeado. Isso garante que @lid sempre vira lid:xxx
        // mesmo quando há mapeamento para número em whatsapp_business_ids
        $remoteIdRaw = $rawContactId ?: $contactExternalId;
        // Se rawContactId é @lid mas contactExternalId foi mapeado para número,
        // usa rawContactId para remote_key (mantém identidade original)
        if ($rawContactId && strpos($rawContactId, '@lid') !== false && $contactExternalId && strpos($contactExternalId, '@lid') === false) {
            // Tem @lid original mas contactExternalId foi mapeado - usa @lid para remote_key
            $remoteIdRaw = $rawContactId;
            error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando @lid original para remote_key (rawContactId) ao invés de número mapeado: ' . $rawContactId);
        }
        $remoteKeyValue = $remoteKey($remoteIdRaw);
        
        // Calcula contact_key e thread_key
        $provider = 'wpp_gateway'; // ou extrair de source_system se necessário
        $sessionIdForKeys = $channelId ?: ($metadata['channel_id'] ?? null);
        $contactKey = null;
        $threadKey = null;
        
        if ($sessionIdForKeys && $remoteKeyValue) {
            $contactKey = $provider . ':' . $sessionIdForKeys . ':' . $remoteKeyValue;
            $threadKey = $contactKey; // Para WhatsApp, thread_key = contact_key
        }
        
        // Extrai phone_e164 quando disponível (para enriquecimento, não como chave)
        $phoneE164 = null;
        if ($contactExternalId && strpos($contactExternalId, '@lid') === false) {
            // Se não é @lid, tenta extrair número
            if (preg_match('/^[0-9]+$/', $contactExternalId)) {
                $phoneE164 = $contactExternalId; // Já é número
            } elseif (strpos($contactExternalId, '@c.us') !== false || strpos($contactExternalId, '@s.whatsapp.net') !== false) {
                $digits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $contactExternalId));
                if ($digits && strlen($digits) >= 10) {
                    $phoneE164 = $digits; // Extrai número do JID
                }
            }
        }
        
        error_log('[CONVERSATION UPSERT] extractChannelInfo: remote_id_raw=' . ($remoteIdRaw ?: 'NULL') . ', remote_key=' . ($remoteKeyValue ?: 'NULL') . ', contact_key=' . ($contactKey ?: 'NULL') . ', phone_e164=' . ($phoneE164 ?: 'NULL'));

        return [
            'channel_type' => $channelType,
            'channel_account_id' => $channelAccountId,
            'channel_id' => $channelId,
            'contact_external_id' => $contactExternalId, // Mantém para compatibilidade
            'contact_name' => $contactName,
            'direction' => $direction ?? 'inbound',
            // NOVOS CAMPOS
            'remote_id_raw' => $remoteIdRaw,
            'remote_key' => $remoteKeyValue,
            'contact_key' => $contactKey,
            'thread_key' => $threadKey,
            'phone_e164' => $phoneE164,
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
            $channelId = (string) $metadata['channel_id'];
            error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: encontrado em metadata: ' . $channelId);
            return $channelId;
        }
        
        // Tenta obter do payload (session.id ou channel)
        $channelId = $payload['session']['id'] 
            ?? $payload['session']['session'] 
            ?? $payload['channel'] 
            ?? $payload['channelId']
            ?? ($payload['data']['session']['id'] ?? null)
            ?? ($payload['data']['session']['session'] ?? null)
            ?? null;
        
        if ($channelId) {
            error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: encontrado no payload: ' . $channelId);
        } else {
            error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: NÃO encontrado. Payload keys: ' . implode(', ', array_keys($payload)));
            if (isset($payload['session'])) {
                error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: payload[session] keys: ' . implode(', ', array_keys($payload['session'])));
            }
            if (isset($payload['data'])) {
                error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: payload[data] keys: ' . implode(', ', array_keys($payload['data'])));
            }
        }
        
        return $channelId ? (string) $channelId : null;
    }

    /**
     * Resolve channel_account_id a partir de tenant_id, channel_type e channel_id (sessionId)
     * 
     * CORREÇÃO: Agora usa o channel_id (sessionId) para buscar o canal correto,
     * evitando usar o primeiro canal disponível quando há múltiplos canais.
     * 
     * @param int|null $tenantId ID do tenant
     * @param string $channelType Tipo do canal (whatsapp, email, etc.)
     * @param string|null $channelId Channel ID (sessionId) para buscar o canal específico
     * @return int|null ID do channel_account ou null se não encontrado
     */
    private static function resolveChannelAccountId(?int $tenantId, string $channelType, ?string $channelId = null): ?int
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
            // CORREÇÃO: Se channel_id foi fornecido, usa ele para buscar o canal específico
            if (!empty($channelId)) {
                error_log('[CONVERSATION UPSERT] resolveChannelAccountId: buscando canal com channel_id=' . $channelId . ' para tenant_id=' . $tenantId);
                
                $stmt = $db->prepare("
                    SELECT id 
                    FROM tenant_message_channels 
                    WHERE tenant_id = ? 
                    AND provider = ? 
                    AND channel_id = ?
                    AND is_enabled = 1
                    LIMIT 1
                ");
                $stmt->execute([$tenantId, $provider, $channelId]);
                $result = $stmt->fetch();
                
                if ($result) {
                    $channelAccountId = (int) $result['id'];
                    error_log('[CONVERSATION UPSERT] resolveChannelAccountId: canal encontrado! id=' . $channelAccountId . ' para channel_id=' . $channelId);
                    return $channelAccountId;
                } else {
                    error_log('[CONVERSATION UPSERT] resolveChannelAccountId: canal NÃO encontrado para channel_id=' . $channelId . ' tenant_id=' . $tenantId . ' (channel não mapeado ou desabilitado)');
                    // NÃO faz fallback para primeiro canal - retorna null se não encontrou
                    // Isso força erro explícito ao invés de usar canal errado
                    return null;
                }
            }
            
            // Fallback: Se channel_id não foi fornecido, busca qualquer canal habilitado
            // (mantido para compatibilidade, mas deve ser evitado)
            error_log('[CONVERSATION UPSERT] resolveChannelAccountId: channel_id não fornecido, usando fallback (primeiro canal habilitado)');
            
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
            
            $channelAccountId = $result ? (int) $result['id'] : null;
            if ($channelAccountId) {
                error_log('[CONVERSATION UPSERT] resolveChannelAccountId: fallback encontrou canal id=' . $channelAccountId);
            } else {
                error_log('[CONVERSATION UPSERT] resolveChannelAccountId: fallback NÃO encontrou nenhum canal');
            }
            
            return $channelAccountId;
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
        
        // Extrai timestamp da mensagem para last_message_at
        $messageTimestamp = self::extractMessageTimestamp($eventData);

        try {
            // NOVA ARQUITETURA: Usa remote_key, contact_key, thread_key como identidade primária
            $stmt = $db->prepare("
                INSERT INTO conversations 
                (conversation_key, channel_type, channel_account_id, channel_id, session_id,
                 contact_external_id, remote_id_raw, remote_key, contact_key, thread_key,
                 contact_name, tenant_id, status, last_message_at, last_message_direction, 
                 message_count, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, 1, ?, ?)
            ");

            $stmt->execute([
                $conversationKey,
                $channelInfo['channel_type'],
                $channelInfo['channel_account_id'],
                $channelInfo['channel_id'] ?? null,
                $channelInfo['channel_id'] ?? null, // session_id = channel_id para WhatsApp
                $channelInfo['contact_external_id'], // Mantém para compatibilidade
                $channelInfo['remote_id_raw'] ?? null,
                $channelInfo['remote_key'] ?? null,
                $channelInfo['contact_key'] ?? null,
                $channelInfo['thread_key'] ?? null,
                $channelInfo['contact_name'],
                $tenantId,
                $messageTimestamp, // Usa timestamp da mensagem ao invés de NOW()
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
        
        // Normaliza direction para o padrão do banco/UI
        if ($direction === 'received') $direction = 'inbound';
        if ($direction === 'sent') $direction = 'outbound';
        
        // Extrai timestamp da mensagem do payload, ou usa created_at do evento, ou NOW() como fallback
        $messageTimestamp = self::extractMessageTimestamp($eventData);
        $now = date('Y-m-d H:i:s'); // Para updated_at sempre usa NOW()

        error_log('[CONVERSATION UPSERT] updateConversationMetadata: conversation_id=' . $conversationId . ', direction=' . $direction . ', contact=' . ($channelInfo['contact_external_id'] ?? 'NULL') . ', message_timestamp=' . $messageTimestamp);

        try {
            // 🔍 LOG TEMPORÁRIO: Antes do UPDATE SQL
            error_log(sprintf(
                '[DIAGNOSTICO] ConversationService::updateConversationMetadata() - EXECUTANDO UPDATE: conversation_id=%d, direction=%s, message_timestamp=%s, now=%s',
                $conversationId,
                $direction,
                $messageTimestamp,
                $now
            ));
            
            // CORREÇÃO: Busca unread_count atual antes de atualizar para log
            $currentUnreadStmt = $db->prepare("SELECT unread_count FROM conversations WHERE id = ?");
            $currentUnreadStmt->execute([$conversationId]);
            $currentUnread = $currentUnreadStmt->fetchColumn() ?: 0;
            
            // Atualiza última mensagem e contador
            // CORREÇÃO: Garante que unread_count seja incrementado para inbound
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

            $result = $stmt->execute([$messageTimestamp, $direction, $direction, $now, $conversationId]);
            $rowsAffected = $stmt->rowCount();
            
            // CORREÇÃO: Busca unread_count após atualização para confirmar incremento
            $afterUnreadStmt = $db->prepare("SELECT unread_count FROM conversations WHERE id = ?");
            $afterUnreadStmt->execute([$conversationId]);
            $afterUnread = $afterUnreadStmt->fetchColumn() ?: 0;
            
            // 🔍 LOG TEMPORÁRIO: Resultado do UPDATE com unread_count
            error_log(sprintf(
                '[DIAGNOSTICO] ConversationService::updateConversationMetadata() - UPDATE EXECUTADO: success=%s, rows_affected=%d, direction=%s, unread_count: %d -> %d, last_message_at=%s',
                $result ? 'true' : 'false',
                $rowsAffected,
                $direction,
                $currentUnread,
                $afterUnread,
                $messageTimestamp
            ));
            
            error_log('[CONVERSATION UPSERT] updateConversationMetadata: last_message_at atualizado para ' . $messageTimestamp);

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

            // Atualiza channel_id se fornecido (apenas para eventos inbound)
            // CORREÇÃO: Agora atualiza mesmo se já existir, para corrigir channel_id incorreto
            $channelId = $channelInfo['channel_id'] ?? null;
            if ($channelId && ($channelInfo['direction'] ?? 'inbound') === 'inbound') {
                $updateChannelIdStmt = $db->prepare("
                    UPDATE conversations 
                    SET channel_id = ? 
                    WHERE id = ? AND channel_id != ?
                ");
                $updateChannelIdStmt->execute([$channelId, $conversationId, $channelId]);
                
                $rowsUpdated = $updateChannelIdStmt->rowCount();
                if ($rowsUpdated > 0) {
                    error_log('[CONVERSATION UPSERT] updateConversationMetadata: channel_id atualizado de valor incorreto para: ' . $channelId . ' na conversation_id=' . $conversationId);
                }
            }

            // Atualiza remote_key, remote_id_raw, contact_key, thread_key se fornecidos (arquitetura nova)
            // Isso garante que conversas existentes sejam atualizadas com os valores corretos
            if (!empty($channelInfo['remote_key']) || !empty($channelInfo['contact_key']) || !empty($channelInfo['thread_key'])) {
                $updateRemoteKeyStmt = $db->prepare("
                    UPDATE conversations
                    SET remote_id_raw = COALESCE(?, remote_id_raw),
                        remote_key = COALESCE(?, remote_key),
                        contact_key = COALESCE(?, contact_key),
                        thread_key = COALESCE(?, thread_key),
                        session_id = COALESCE(?, session_id)
                    WHERE id = ?
                ");
                $updateRemoteKeyStmt->execute([
                    $channelInfo['remote_id_raw'] ?? null,
                    $channelInfo['remote_key'] ?? null,
                    $channelInfo['contact_key'] ?? null,
                    $channelInfo['thread_key'] ?? null,
                    $channelInfo['channel_id'] ?? null, // session_id
                    $conversationId
                ]);
                error_log('[CONVERSATION UPSERT] updateConversationMetadata: remote_key/contact_key/thread_key atualizados para conversation_id=' . $conversationId . ', remote_key=' . ($channelInfo['remote_key'] ?: 'NULL'));
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
     * Extrai timestamp da mensagem do payload
     * 
     * @param array $eventData Dados do evento
     * @return string Timestamp no formato 'Y-m-d H:i:s'
     */
    private static function extractMessageTimestamp(array $eventData): string
    {
        $payload = $eventData['payload'] ?? [];
        
        // Tenta extrair timestamp de múltiplas fontes (ordem de prioridade)
        $timestamp = null;
        
        // 1. payload.message.timestamp (Unix timestamp)
        if (isset($payload['message']['timestamp'])) {
            $timestamp = $payload['message']['timestamp'];
        }
        // 2. payload.timestamp (Unix timestamp)
        elseif (isset($payload['timestamp'])) {
            $timestamp = $payload['timestamp'];
        }
        // 3. payload.raw.payload.t (Unix timestamp do WhatsApp)
        elseif (isset($payload['raw']['payload']['t'])) {
            $timestamp = $payload['raw']['payload']['t'];
        }
        
        // Converte Unix timestamp para formato MySQL
        if ($timestamp !== null && is_numeric($timestamp)) {
            // Se timestamp está em segundos (formato comum)
            if ($timestamp < 10000000000) {
                return date('Y-m-d H:i:s', (int) $timestamp);
            }
            // Se timestamp está em milissegundos (formato WhatsApp)
            else {
                return date('Y-m-d H:i:s', (int) ($timestamp / 1000));
            }
        }
        
        // Fallback: usa NOW() se não conseguir extrair
        return date('Y-m-d H:i:s');
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

    /**
     * Busca conversa apenas por contato (ignorando channel_account_id)
     * 
     * Usado para encontrar conversas "shared" quando uma nova conversa com tenant específico
     * está sendo criada, ou vice-versa.
     * 
     * @param array $channelInfo Informações do canal
     * @return array|null Conversa encontrada ou null
     */
    private static function findConversationByContactOnly(array $channelInfo): ?array
    {
        // Aplica apenas para WhatsApp
        if ($channelInfo['channel_type'] !== 'whatsapp') {
            return null;
        }

        $contactExternalId = $channelInfo['contact_external_id'] ?? null;
        if (!$contactExternalId) {
            return null;
        }

        $db = DB::getConnection();
        
        try {
            // Busca conversa com mesmo contato e tipo de canal, independente do channel_account_id
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE channel_type = ? 
                AND contact_external_id = ?
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            $stmt->execute([$channelInfo['channel_type'], $contactExternalId]);
            $result = $stmt->fetch();
            
            return $result ?: null;
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao buscar conversa por contato: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Atualiza channel_account_id de uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @param int|null $channelAccountId Novo channel_account_id
     */
    private static function updateChannelAccountId(int $conversationId, ?int $channelAccountId): void
    {
        if ($channelAccountId === null) {
            return; // Não atualiza se for null
        }

        $db = DB::getConnection();
        
        try {
            // Atualiza apenas se ainda não tiver channel_account_id
            $stmt = $db->prepare("
                UPDATE conversations 
                SET channel_account_id = ?,
                    conversation_key = ?
                WHERE id = ? 
                AND (channel_account_id IS NULL OR channel_account_id = 0)
            ");
            
            // Gera nova chave com o channel_account_id
            $conversation = self::findById($conversationId);
            if ($conversation) {
                $newKey = self::generateConversationKey(
                    $conversation['channel_type'],
                    $channelAccountId,
                    $conversation['contact_external_id']
                );
                $stmt->execute([$channelAccountId, $newKey, $conversationId]);
            }
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao atualizar channel_account_id: " . $e->getMessage());
            // Não quebra fluxo se falhar
        }
    }
}


