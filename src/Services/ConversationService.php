<?php

namespace PixelHub\Services;

use PixelHub\Services\PhoneNormalizer;

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
            
            // 🔍 LOG: Thread afetada antes de atualizar
            $existingChannelId = $existing['channel_id'] ?? null;
            $newChannelId = $channelInfo['channel_id'] ?? null;
            error_log(sprintf(
                '[HUB_THREAD_UPDATE] thread_id=%d | channel_id_atual=%s | channel_id_novo=%s | from=%s',
                $existing['id'],
                $existingChannelId ?: 'NULL',
                $newChannelId ?: 'NULL',
                $channelInfo['contact_external_id'] ?? 'NULL'
            ));
            
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
        
        // CORREÇÃO: Verifica duplicados por remote_key antes de criar nova conversa
        // Isso previne criação de conversas duplicadas quando o mesmo contato aparece
        // com identificadores diferentes (ex: 169183207809126@lid vs 169183207809126)
        if (!empty($channelInfo['remote_key'])) {
            error_log('[HUB_CONV_MATCH] Query: findDuplicateByRemoteKey remote_key=' . $channelInfo['remote_key']);
            $duplicateByRemoteKey = self::findDuplicateByRemoteKey($channelInfo);
            if ($duplicateByRemoteKey) {
                error_log('[HUB_CONV_MATCH] FOUND_DUPLICATE_BY_REMOTE_KEY id=' . $duplicateByRemoteKey['id'] . ' remote_key=' . $channelInfo['remote_key'] . ' reason=prevent_duplication');
                // Atualiza a conversa existente ao invés de criar nova
                self::updateConversationMetadata($duplicateByRemoteKey['id'], $eventData, $channelInfo);
                return $duplicateByRemoteKey;
            }
        }

        // CORREÇÃO: Verifica se existe conversa com mesmo contact_name no mesmo tenant E MESMO channel_id
        // REGRA DE OURO: Contato NÃO pode ser resolvido só por nome. Identidade = external_id + channel.
        // Só usa match por nome quando external_ids são equivalentes (mesmo contato real).
        if (!empty($channelInfo['contact_name']) && strpos($channelInfo['contact_external_id'] ?? '', '@lid') !== false) {
            $conversationByName = self::findConversationByContactName($channelInfo, $eventData['tenant_id'] ?? null);
            if ($conversationByName) {
                // CRÍTICO: Só aceita match se external_ids são equivalentes (evita 47 vs 11 por nome)
                if (self::contactExternalIdsAreEquivalent(
                    $channelInfo['contact_external_id'],
                    $conversationByName['contact_external_id'],
                    $eventData['tenant_id'] ?? null
                )) {
                    error_log('[HUB_CONV_MATCH] FOUND_BY_CONTACT_NAME id=' . $conversationByName['id'] .
                        ' contact_external_id=' . $conversationByName['contact_external_id'] .
                        ' new_contact=' . $channelInfo['contact_external_id'] . ' reason=same_contact_verified');
                    self::createLidPhoneMapping($channelInfo['contact_external_id'], $conversationByName['contact_external_id'], $eventData['tenant_id'] ?? null);
                    self::updateConversationMetadata($conversationByName['id'], $eventData, $channelInfo);
                    return $conversationByName;
                }
                error_log('[HUB_CONV_MATCH] REJECTED_BY_CONTACT_NAME: external_ids diferentes - new=' .
                    $channelInfo['contact_external_id'] . ' existing=' . $conversationByName['contact_external_id'] .
                    ' - criando nova conversa (nunca mergear por nome quando IDs diferentes)');
            }
        }

        // CORREÇÃO CRÍTICA: Verifica se existe conversa com número E.164 correspondente ao @lid (ou vice-versa)
        // Isso previne duplicidade quando o mesmo contato aparece via @lid e via número E.164
        $conversationByLidPhone = self::findConversationByLidPhoneMapping($channelInfo);
        if ($conversationByLidPhone) {
            error_log('[HUB_CONV_MATCH] FOUND_BY_LID_PHONE_MAPPING id=' . $conversationByLidPhone['id'] . 
                ' contact_external_id=' . $conversationByLidPhone['contact_external_id'] . 
                ' new_contact=' . $channelInfo['contact_external_id'] . ' reason=lid_phone_mapping');
            self::updateConversationMetadata($conversationByLidPhone['id'], $eventData, $channelInfo);
            return $conversationByLidPhone;
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
            } elseif (!empty($equivalentByContact['channel_account_id']) && empty($channelInfo['channel_account_id'])) {
                // CORREÇÃO: Nova mensagem não tem channel_account_id, mas conversa existente tem
                // Isso acontece quando mensagem chega via "shared" mas já existe conversa vinculada
                // Ex: Robson já tem conversa 8 com channel_account_id=4, mas nova msg vem sem channel_account_id
                // Usa a conversa existente ao invés de criar nova
                error_log('[HUB_CONV_MATCH] FOUND_EXISTING_WITH_ACCOUNT id=' . $equivalentByContact['id'] . ' reason=use_existing_vinculada_instead_of_creating_shared');
                self::updateConversationMetadata($equivalentByContact['id'], $eventData, $channelInfo);
                return $equivalentByContact;
            }
            // Se channel_account_id é diferente (ambos definidos e diferentes), cria nova (comportamento esperado para múltiplos tenants)
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
            // IMPORTANTE: WhatsApp Web API pode usar diferentes estruturas dependendo do tipo de mensagem
            $rawFrom = null;
            if ($direction === 'inbound') {
                // Caminhos principais (mais comuns)
                $rawFrom = $payload['message']['from'] 
                    ?? $payload['from'] 
                    ?? $payload['data']['from'] 
                    ?? $payload['raw']['payload']['from']
                    ?? $payload['raw']['from']
                    // Caminhos alternativos: message.key.remoteJid (comum no WhatsApp Web API)
                    ?? $payload['message']['key']['remoteJid']
                    ?? $payload['data']['key']['remoteJid']
                    ?? $payload['raw']['payload']['key']['remoteJid']
                    // Para grupos: message.key.participant
                    ?? $payload['message']['key']['participant']
                    ?? $payload['data']['key']['participant']
                    ?? $payload['raw']['payload']['key']['participant']
                    // Fallback: verifica em message.body se houver
                    ?? null;
                    
                $contactName = $payload['message']['notifyName'] 
                    ?? $payload['raw']['payload']['notifyName'] 
                    ?? $payload['raw']['payload']['sender']['verifiedName']
                    ?? $payload['raw']['payload']['sender']['name']
                    ?? $payload['data']['notifyName']
                    ?? $payload['notifyName'] ?? null;
            } else {
                // OUTBOUND: Para mensagens enviadas (fromMe=true), queremos o DESTINATÁRIO
                // remoteJid é o contato externo (para outbound = destinatário)
                // IMPORTANTE: WPPConnect/onselfmessage usa remoteJid, não 'to'
                $rawFrom = $payload['message']['to'] 
                    ?? $payload['to'] 
                    ?? $payload['data']['to']
                    ?? $payload['raw']['payload']['to']
                    ?? $payload['raw']['to']
                    // CORREÇÃO: remoteJid é o contato externo (destinatário para outbound)
                    ?? $payload['message']['key']['remoteJid']
                    ?? $payload['data']['key']['remoteJid']
                    ?? $payload['raw']['payload']['key']['remoteJid']
                    // Fallback: chatId também é o contato externo
                    ?? $payload['chatId']
                    ?? $payload['message']['chatId']
                    ?? $payload['data']['chatId']
                    ?? $payload['raw']['payload']['chatId']
                    ?? null;
                
                error_log('[CONVERSATION UPSERT] extractChannelInfo: OUTBOUND extraction - to/remoteJid result: ' . ($rawFrom ?: 'NULL'));
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
            
            // ÚLTIMA TENTATIVA: Busca recursivamente campos que podem conter o número
            // Alguns gateways/envios podem ter estrutura diferente
            if (!$rawFrom) {
                $rawFrom = self::findPhoneOrJidRecursively($payload);
                if ($rawFrom) {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Encontrado via busca recursiva: ' . $rawFrom);
                }
            }
            
            // Se não tem from válido, retorna erro específico
            if (!$rawFrom) {
                error_log('[CONVERSATION UPSERT] extractChannelInfo: ERRO - Payload sem from válido. Payload keys: ' . implode(', ', array_keys($payload)));
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Payload completo (primeiros 800 chars): ' . substr(json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 800));
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
            // CORREÇÃO: SEMPRE normalizar através de PhoneNormalizer para evitar duplicatas
            if (!$contactExternalId && $rawFrom) {
                // Remove sufixos e extrai dígitos
                $rawForNorm = preg_replace('/@.*$/', '', $rawFrom);
                $rawForNorm = preg_replace('/[^0-9]/', '', $rawForNorm);
                
                // Tenta normalizar para E.164
                if (strlen($rawForNorm) >= 8) {
                    $normalized = PhoneNormalizer::toE164OrNull($rawForNorm);
                    if ($normalized) {
                        $contactExternalId = $normalized;
                        error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando rawFrom NORMALIZADO como contact_external_id: ' . $contactExternalId . ' (original: ' . $rawFrom . ')');
                    } else {
                        // Se não conseguiu normalizar, usa o rawFrom original (pode ser @lid ou formato não-brasileiro)
                        $contactExternalId = $rawFrom;
                        error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando rawFrom ORIGINAL como contact_external_id (falha normalização): ' . $contactExternalId);
                    }
                } else {
                    // Poucos dígitos, usa original
                    $contactExternalId = $rawFrom;
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Usando rawFrom como contact_external_id (poucos dígitos): ' . $contactExternalId);
                }
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
        // REGRA: Exige channel_id para evitar contaminação entre canais (ex: ImobSites vs pixel12digital)
        // Apenas para atualizar conversa existente, NÃO cria nova conversa
        if (!$contactExternalId && $channelType === 'whatsapp' && $direction === 'inbound') {
            $notifyName = $payload['message']['notifyName'] 
                ?? $payload['raw']['payload']['notifyName'] 
                ?? $payload['raw']['payload']['sender']['verifiedName'] 
                ?? $payload['raw']['payload']['sender']['name'] 
                ?? null;
            
            // Extrai channel_id do payload ANTES do fallback (evita usar contato de outro canal)
            $fallbackChannelId = self::extractChannelIdFromPayload($payload, $metadata);
            
            // Só usa fallback quando temos channel_id - evita contaminação entre canais
            if ($notifyName && $tenantId && $fallbackChannelId) {
                error_log('[CONVERSATION UPSERT] extractChannelInfo: Tentando fallback por nome - notifyName: ' . $notifyName . ', tenant_id: ' . $tenantId . ', channel_id: ' . ($fallbackChannelId ?: 'NULL'));
                
                $db = \PixelHub\Core\DB::getConnection();
                // CORREÇÃO: Filtra por channel_id - só usa conversa do MESMO canal (evita 47 vs 11 entre canais)
                $stmt = $db->prepare("
                    SELECT contact_external_id 
                    FROM conversations 
                    WHERE channel_type = 'whatsapp' 
                    AND tenant_id = ? 
                    AND contact_name = ?
                    AND channel_id IS NOT NULL 
                    AND LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
                    LIMIT 1
                ");
                $stmt->execute([$tenantId, $notifyName, $fallbackChannelId]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existing && !empty($existing['contact_external_id'])) {
                    $contactExternalId = $existing['contact_external_id'];
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Fallback encontrou conversa existente (mesmo canal) - contact_external_id: ' . $contactExternalId);
                } else {
                    error_log('[CONVERSATION UPSERT] extractChannelInfo: Fallback NÃO encontrou conversa existente para nome: ' . $notifyName . ' no canal: ' . ($fallbackChannelId ?: 'NULL'));
                }
            }
        }
        
        if (!$contactExternalId) {
            error_log('[CONVERSATION UPSERT] ERRO: contactExternalId é NULL após extração. Channel type: ' . ($channelType ?: 'NULL') . ', Direction: ' . ($direction ?? 'NULL'));
            return null;
        }

        // Extrai channel_id (session.id) do payload ou metadata
        // Inbound: extrai do payload; Outbound: extrai do metadata (enviado pelo send)
        $channelId = null;
        $channelIdSource = null;
        if ($channelType === 'whatsapp') {
            $dir = $direction ?? 'inbound';
            if ($dir === 'inbound') {
                $channelId = self::extractChannelIdFromPayload($payload, $metadata);
                $channelIdSource = 'extractChannelIdFromPayload';
            } else {
                // Outbound: channel_id vem do metadata (CommunicationHubController::send)
                $channelId = $metadata['channel_id'] ?? $payload['channel_id'] ?? null;
                if ($channelId) {
                    $channelIdSource = 'metadata.channel_id';
                }
            }
        }

        // Resolve channel_account_id usando o channel_id (sessionId) extraído
        // CORREÇÃO: Usa channel_id para buscar o canal correto no tenant_message_channels
        $channelAccountId = null;
        if ($channelType === 'whatsapp') {
            if ($tenantId) {
                $channelAccountId = self::resolveChannelAccountId($tenantId, $channelType, $channelId);
            }
            // Quando tenant_id é null (ex: webhook não resolveu), tenta achar canal só pelo channel_id
            // para que a conversa seja criada com o canal correto (ex: ImobSites)
            if ($channelAccountId === null && !empty($channelId)) {
                $channelAccountId = self::resolveChannelAccountIdByChannelOnly($channelId);
            }
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
            'channel_id_source' => $channelIdSource ?? null, // Para rastreamento/log
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
        // CORREÇÃO CRÍTICA: Prioridade MÁXIMA para sessionId real do gateway
        // NUNCA usar metadata.channel_id primeiro - pode conter valor errado (ex: ImobSites)
        // Ordem de prioridade (sessionId primeiro, sempre):
        // 1. payload.sessionId (mais direto - sessão real do gateway)
        // 2. payload.session.id (estrutura comum do gateway)
        // 3. payload.session.session (alternativa)
        // 4. payload.data.session.id
        // 5. payload.data.session.session
        // 6. payload.metadata.sessionId (se metadata tiver sessionId, não channel_id)
        // 7. payload.channelId (fallback, mas ainda pode ser sessionId)
        // 8. payload.channel (fallback)
        // 9. payload.data.channel
        // 10. payload.metadata.channel_id (ÚLTIMA opção - pode estar errado)
        // NÃO permite fallback para "ImobSites" ou qualquer valor arbitrário
        
        $channelId = null;
        $source = null;
        
        // PRIORIDADE 1-5: sessionId (sessão real do gateway) - SEMPRE PRIMEIRO
        if (isset($payload['sessionId'])) {
            $channelId = (string) $payload['sessionId'];
            $source = 'payload.sessionId';
        } elseif (isset($payload['session']['id'])) {
            $channelId = (string) $payload['session']['id'];
            $source = 'payload.session.id';
        } elseif (isset($payload['session']['session'])) {
            $channelId = (string) $payload['session']['session'];
            $source = 'payload.session.session';
        } elseif (isset($payload['data']['session']['id'])) {
            $channelId = (string) $payload['data']['session']['id'];
            $source = 'payload.data.session.id';
        } elseif (isset($payload['data']['session']['session'])) {
            $channelId = (string) $payload['data']['session']['session'];
            $source = 'payload.data.session.session';
        }
        
        // PRIORIDADE 6: metadata.sessionId (se tiver, não channel_id)
        if (!$channelId && isset($payload['metadata']['sessionId'])) {
            $channelId = (string) $payload['metadata']['sessionId'];
            $source = 'payload.metadata.sessionId';
        }
        
        // PRIORIDADE 7-9: channelId/channel (fallback)
        if (!$channelId && isset($payload['channelId'])) {
            $channelId = (string) $payload['channelId'];
            $source = 'payload.channelId';
        } elseif (!$channelId && isset($payload['channel'])) {
            $channelId = (string) $payload['channel'];
            $source = 'payload.channel';
        } elseif (!$channelId && isset($payload['data']['channel'])) {
            $channelId = (string) $payload['data']['channel'];
            $source = 'payload.data.channel';
        }
        
        // PRIORIDADE 10: metadata.channel_id (ÚLTIMA opção - pode estar errado)
        // Só usa se metadata.channel_id vier de metadata separado (não do payload)
        if (!$channelId && $metadata && isset($metadata['channel_id'])) {
            $channelId = (string) $metadata['channel_id'];
            $source = 'metadata.channel_id (separado)';
        } elseif (!$channelId && isset($payload['metadata']['channel_id'])) {
            $channelId = (string) $payload['metadata']['channel_id'];
            $source = 'payload.metadata.channel_id';
        }
        
        if ($channelId) {
            // ImobSites é sessão válida no gateway; aceitar quando vindo de payload ou metadata.
            error_log(sprintf(
                '[CONVERSATION UPSERT] extractChannelIdFromPayload: channel_id=%s | source=%s',
                $channelId,
                $source
            ));
            return $channelId;
        }
        
        // NÃO permite fallback - retorna NULL e loga erro
        error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: INBOUND_MISSING_CHANNEL_ID - Nenhum sessionId/channelId encontrado');
        error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: payload_keys=' . implode(', ', array_keys($payload)));
        if (isset($payload['session'])) {
            error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: payload[session]_keys=' . implode(', ', array_keys($payload['session'])));
        }
        if (isset($payload['data'])) {
            error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: payload[data]_keys=' . implode(', ', array_keys($payload['data'])));
        }
        if (isset($payload['metadata'])) {
            error_log('[CONVERSATION UPSERT] extractChannelIdFromPayload: payload[metadata]_keys=' . implode(', ', array_keys($payload['metadata'])));
        }
        
        return null;
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
     * Resolve channel_account_id apenas pelo channel_id (sessionId), sem tenant.
     * Usado quando o webhook não conseguiu resolver tenant_id (ex: canal ImobSites).
     *
     * @param string $channelId SessionId do gateway (ex: ImobSites, pixel12digital)
     * @return int|null ID do canal em tenant_message_channels ou null
     */
    private static function resolveChannelAccountIdByChannelOnly(string $channelId): ?int
    {
        $db = DB::getConnection();
        $normalized = strtolower(preg_replace('/\s+/', '', trim($channelId)));
        if ($normalized === '') {
            return null;
        }
        try {
            $stmt = $db->prepare("
                SELECT id FROM tenant_message_channels
                WHERE provider = 'wpp_gateway'
                AND is_enabled = 1
                AND (LOWER(REPLACE(TRIM(channel_id), ' ', '')) = ? OR channel_id = ?)
                LIMIT 1
            ");
            $stmt->execute([$normalized, $channelId]);
            $row = $stmt->fetch();
            if ($row) {
                error_log('[CONVERSATION UPSERT] resolveChannelAccountIdByChannelOnly: canal encontrado id=' . $row['id'] . ' para channel_id=' . $channelId);
                return (int) $row['id'];
            }
            return null;
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao resolver canal por channel_id: " . $e->getMessage());
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
        
        // CORREÇÃO: NÃO resolve tenant_id automaticamente pelo channel_id quando é NULL
        // Números novos sem tenant devem ficar como "Não vinculados" (tenant_id = NULL)
        // e serem marcados como incoming_lead. A resolução automática estava causando
        // vinculação incorreta de números novos ao tenant do canal (ex: SO OBRAS).
        // Se o tenant_id vier NULL do evento, mantém NULL para que apareça em "Não vinculados"
        
        // CORREÇÃO 2: Valida se o telefone do contato corresponde ao telefone do tenant
        // EXCEÇÃO: explicit_tenant_selection (usuário escolheu no modal), 
        //          tenant_resolved_from_phone (webhook já validou pelo telefone),
        //          tenant_resolved_from_channel (legado, mantido por compatibilidade)
        $explicitTenant = !empty($eventData['metadata']['explicit_tenant_selection']);
        $tenantFromPhone = !empty($eventData['metadata']['tenant_resolved_from_phone']);
        $tenantFromChannel = !empty($eventData['metadata']['tenant_resolved_from_channel']);
        $contactExternalId = $channelInfo['contact_external_id'] ?? '';
        if ($tenantId !== null && !empty($contactExternalId) && !$explicitTenant && !$tenantFromPhone && !$tenantFromChannel) {
            if (!self::validatePhoneBelongsToTenant($contactExternalId, $tenantId)) {
                error_log(sprintf(
                    '[CONVERSATION CREATE] Vinculação REJEITADA: contato=%s não pertence ao tenant_id=%d - conversa irá para "Não vinculados"',
                    $contactExternalId,
                    $tenantId
                ));
                $tenantId = null;
            }
        }
        
        // Extrai timestamp da mensagem para last_message_at
        $messageTimestamp = self::extractMessageTimestamp($eventData);

        try {
            // Marca como incoming_lead se tenant_id é NULL (número não cadastrado)
            $isIncomingLead = ($tenantId === null) ? 1 : 0;
            
            // NOVA ARQUITETURA: Usa remote_key, contact_key, thread_key como identidade primária
            $stmt = $db->prepare("
                INSERT INTO conversations 
                (conversation_key, channel_type, channel_account_id, channel_id, session_id,
                 contact_external_id, remote_key, contact_key, thread_key,
                 contact_name, tenant_id, is_incoming_lead, status, last_message_at, last_message_direction, 
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
                $channelInfo['remote_key'] ?? null,
                $channelInfo['contact_key'] ?? null,
                $channelInfo['thread_key'] ?? null,
                $channelInfo['contact_name'],
                $tenantId,
                $isIncomingLead, // Marca como incoming lead se não tem tenant
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
    /**
     * Verifica se a mensagem tem conteúdo real (não é apenas notificação de sistema)
     * 
     * @param array $eventData Dados do evento
     * @return bool True se a mensagem tem conteúdo real, False caso contrário
     */
    private static function hasMessageContent(array $eventData): bool
    {
        $payload = $eventData['payload'] ?? [];
        
        // Extrai conteúdo de texto
        $text = $payload['text'] 
            ?? $payload['body'] 
            ?? $payload['message']['text'] ?? null
            ?? $payload['message']['body'] ?? null;
        
        // Se tem texto não vazio, é mensagem válida
        if (!empty(trim($text ?? ''))) {
            return true;
        }
        
        // Verifica se é notificação de sistema do WhatsApp Business
        $rawPayload = $payload['raw']['payload'] ?? [];
        $type = $rawPayload['type'] ?? $payload['type'] ?? $payload['message']['type'] ?? null;
        $subtype = $rawPayload['subtype'] ?? null;
        
        // Notificações de sistema do WhatsApp Business (ex: biz_account_type_changed_to_hosted)
        if ($type === 'notification_template' || !empty($subtype)) {
            // Verifica se tem mídia processada (mesmo sem texto, mídia conta como conteúdo)
            try {
                $db = DB::getConnection();
                $eventId = $eventData['event_id'] ?? null;
                if ($eventId) {
                    $mediaStmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
                    $mediaStmt->execute([$eventId]);
                    if ($mediaStmt->fetch()) {
                        return true; // Tem mídia, conta como mensagem válida
                    }
                }
            } catch (\Exception $e) {
                // Se der erro ao verificar mídia, assume que não tem
            }
            
            // É notificação de sistema sem conteúdo nem mídia
            return false;
        }
        
        // Verifica se tem mídia (mesmo sem texto)
        try {
            $db = DB::getConnection();
            $eventId = $eventData['event_id'] ?? null;
            if ($eventId) {
                $mediaStmt = $db->prepare("SELECT id FROM communication_media WHERE event_id = ? LIMIT 1");
                $mediaStmt->execute([$eventId]);
                if ($mediaStmt->fetch()) {
                    return true; // Tem mídia, conta como mensagem válida
                }
            }
        } catch (\Exception $e) {
            // Se der erro ao verificar mídia, assume que não tem
        }
        
        // Verifica se tem tipo de mídia no payload (mesmo sem arquivo processado)
        $mediaTypes = ['image', 'video', 'audio', 'document', 'sticker', 'ptt'];
        if (in_array($type, $mediaTypes)) {
            return true; // Tem tipo de mídia, conta como mensagem válida
        }
        
        // Sem conteúdo, sem mídia, não é mensagem válida
        return false;
    }

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
        
        // CORREÇÃO: Verifica se a mensagem tem conteúdo real antes de incrementar contador
        $hasContent = self::hasMessageContent($eventData);
        
        // Extrai timestamp da mensagem do payload, ou usa created_at do evento, ou NOW() como fallback
        $messageTimestamp = self::extractMessageTimestamp($eventData);
        $now = date('Y-m-d H:i:s'); // Para updated_at sempre usa NOW()

        error_log('[CONVERSATION UPSERT] updateConversationMetadata: conversation_id=' . $conversationId . ', direction=' . $direction . ', contact=' . ($channelInfo['contact_external_id'] ?? 'NULL') . ', message_timestamp=' . $messageTimestamp . ', has_content=' . ($hasContent ? 'true' : 'false'));

        try {
            // 🔍 LOG TEMPORÁRIO: Antes do UPDATE SQL
            error_log(sprintf(
                '[DIAGNOSTICO] ConversationService::updateConversationMetadata() - EXECUTANDO UPDATE: conversation_id=%d, direction=%s, message_timestamp=%s, now=%s, has_content=%s',
                $conversationId,
                $direction,
                $messageTimestamp,
                $now,
                $hasContent ? 'true' : 'false'
            ));
            
            // CORREÇÃO: Busca unread_count atual antes de atualizar para log
            $currentUnreadStmt = $db->prepare("SELECT unread_count FROM conversations WHERE id = ?");
            $currentUnreadStmt->execute([$conversationId]);
            $currentUnread = $currentUnreadStmt->fetchColumn() ?: 0;
            
            // Atualiza última mensagem e contador
            // CORREÇÃO: Incrementa message_count apenas se a mensagem tem conteúdo real
            // CORREÇÃO: Garante que unread_count seja incrementado para inbound
            // IMPORTANTE: Se status='ignored', incrementa ignored_unread_count ao invés de unread_count
            // e NÃO altera o status (mantém 'ignored')
            $stmt = $db->prepare("
                UPDATE conversations 
                SET last_message_at = ?,
                    last_message_direction = ?,
                    message_count = CASE 
                        WHEN ? = 1 THEN message_count + 1 
                        ELSE message_count 
                    END,
                    unread_count = CASE 
                        WHEN status = 'ignored' THEN unread_count
                        WHEN ? = 'inbound' THEN unread_count + 1 
                        ELSE unread_count 
                    END,
                    ignored_unread_count = CASE 
                        WHEN status = 'ignored' AND ? = 'inbound' THEN ignored_unread_count + 1
                        ELSE ignored_unread_count
                    END,
                    status = CASE 
                        WHEN status = 'ignored' THEN 'ignored'
                        WHEN status = 'closed' THEN 'open'
                        ELSE status
                    END,
                    updated_at = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([$messageTimestamp, $direction, $hasContent ? 1 : 0, $direction, $direction, $now, $conversationId]);
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

            // CORREÇÃO: NÃO resolve tenant_id automaticamente pelo channel_id quando é NULL
            // Números novos sem tenant devem ficar como "Não vinculados" (tenant_id = NULL)
            // e serem marcados como incoming_lead. A resolução automática estava causando
            // vinculação incorreta de números novos ao tenant do canal (ex: SO OBRAS).
            // Se o tenant_id vier NULL do evento, mantém NULL para que apareça em "Não vinculados"
            $tenantId = $eventData['tenant_id'] ?? null;
            
            // CORREÇÃO 2: Valida se o telefone do contato corresponde ao telefone do tenant
            // EXCEÇÃO: explicit_tenant_selection ou tenant_resolved_from_channel (tenant resolvido pelo canal no webhook)
            $explicitTenant = !empty($eventData['metadata']['explicit_tenant_selection']);
            $tenantFromChannel = !empty($eventData['metadata']['tenant_resolved_from_channel']);
            $contactExternalId = $channelInfo['contact_external_id'] ?? '';
            if ($tenantId !== null && !empty($contactExternalId) && !$explicitTenant && !$tenantFromChannel) {
                if (!self::validatePhoneBelongsToTenant($contactExternalId, $tenantId)) {
                    error_log(sprintf(
                        '[CONVERSATION UPDATE] Vinculação REJEITADA: contato=%s não pertence ao tenant_id=%d - mantendo sem vincular',
                        $contactExternalId,
                        $tenantId
                    ));
                    $tenantId = null;
                }
            }
            
            // Atualiza tenant_id se fornecido/resolvido e ainda não existe
            // Também atualiza is_incoming_lead: se tenant_id é NULL, marca como incoming_lead = 1
            if ($tenantId) {
                $updateTenantStmt = $db->prepare("
                    UPDATE conversations 
                    SET tenant_id = ?,
                        is_incoming_lead = 0
                    WHERE id = ? AND tenant_id IS NULL
                ");
                $updateTenantStmt->execute([$tenantId, $conversationId]);
            } else {
                // Se tenant_id é NULL, marca como incoming_lead se ainda não estiver marcado
                $updateIncomingLeadStmt = $db->prepare("
                    UPDATE conversations 
                    SET is_incoming_lead = 1
                    WHERE id = ? AND tenant_id IS NULL AND is_incoming_lead = 0
                ");
                $updateIncomingLeadStmt->execute([$conversationId]);
            }

            // CORREÇÃO CRÍTICA: Atualiza channel_id SEMPRE para eventos inbound
            // Regra: se channel_id extraído existir, atualizar sempre threads.channel_id (mesmo se já tiver valor)
            // Isso resolve threads "nascidas erradas" (ex: ImobSites quando deveria ser pixel12digital)
            $channelId = $channelInfo['channel_id'] ?? null;
            $direction = $channelInfo['direction'] ?? 'inbound';
            
            if ($direction === 'inbound') {
                // Busca channel_id atual da thread antes de atualizar (para log de comparação)
                $currentChannelIdStmt = $db->prepare("SELECT channel_id FROM conversations WHERE id = ?");
                $currentChannelIdStmt->execute([$conversationId]);
                $currentChannelId = $currentChannelIdStmt->fetchColumn() ?: null;
                
                if ($channelId) {
                    // ImobSites é sessão válida no gateway; não rejeitar por nome.
                    // Sempre atualiza, mesmo se já existir (garante que está correto)
                    // Isso cura threads "nascidas erradas"
                    $updateChannelIdStmt = $db->prepare("
                        UPDATE conversations 
                        SET channel_id = ? 
                        WHERE id = ?
                    ");
                    $updateChannelIdStmt->execute([$channelId, $conversationId]);
                    
                    $rowsUpdated = $updateChannelIdStmt->rowCount();
                    if ($currentChannelId && $currentChannelId !== $channelId) {
                        // Log quando channel_id foi corrigido (thread "curada")
                        error_log(sprintf(
                            '[CONVERSATION UPSERT] updateConversationMetadata: THREAD_CURED conversation_id=%d | channel_id_antigo=%s | channel_id_novo=%s | from=%s | source=%s',
                            $conversationId,
                            $currentChannelId,
                            $channelId,
                            $channelInfo['contact_external_id'] ?? 'NULL',
                            $channelInfo['channel_id_source'] ?? 'unknown'
                        ));
                    } elseif ($rowsUpdated > 0) {
                        // Log quando channel_id foi definido pela primeira vez
                        error_log(sprintf(
                            '[CONVERSATION UPSERT] updateConversationMetadata: channel_id_definido conversation_id=%d | channel_id=%s | from=%s',
                            $conversationId,
                            $channelId,
                            $channelInfo['contact_external_id'] ?? 'NULL'
                        ));
                    } else {
                        // Log quando channel_id já estava correto
                        error_log(sprintf(
                            '[CONVERSATION UPSERT] updateConversationMetadata: channel_id_ok conversation_id=%d | channel_id=%s | from=%s',
                            $conversationId,
                            $channelId,
                            $channelInfo['contact_external_id'] ?? 'NULL'
                        ));
                    }
                } else {
                    // Log de aviso quando evento inbound não trouxe channel_id
                    error_log(sprintf(
                        '[CONVERSATION UPSERT] updateConversationMetadata: INBOUND_MISSING_CHANNEL_ID conversation_id=%d | from=%s | current_channel_id=%s',
                        $conversationId,
                        $channelInfo['contact_external_id'] ?? 'NULL',
                        $currentChannelId ?: 'NULL'
                    ));
                }
            }

            // Atualiza remote_key, contact_key, thread_key se fornecidos (arquitetura nova)
            // Isso garante que conversas existentes sejam atualizadas com os valores corretos
            if (!empty($channelInfo['remote_key']) || !empty($channelInfo['contact_key']) || !empty($channelInfo['thread_key'])) {
                $updateRemoteKeyStmt = $db->prepare("
                    UPDATE conversations
                    SET remote_key = COALESCE(?, remote_key),
                        contact_key = COALESCE(?, contact_key),
                        thread_key = COALESCE(?, thread_key),
                        session_id = COALESCE(?, session_id)
                    WHERE id = ?
                ");
                $updateRemoteKeyStmt->execute([
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

        // Gera variações possíveis do número
        $variants = [];

        // Caso 1: Números com 8 dígitos (após DDD) - adiciona 9º dígito
        if ($numberLen === 8) {
            $variants[] = '55' . $ddd . '9' . $number;
        }
        // Caso 2: Números com 9 dígitos (após DDD) - remove 9º dígito
        elseif ($numberLen === 9) {
            $variants[] = '55' . $ddd . substr($number, 1);
        }
        // Caso 3: Números com 10 dígitos (após DDD) - pode ter 9º dígito extra
        // Exemplo: 5587999884234 (11 dígitos) vs 558799884234 (10 dígitos)
        elseif ($numberLen === 10) {
            // Se começa com 9, tenta remover (pode ser 9º dígito extra)
            if (substr($number, 0, 1) === '9') {
                $variants[] = '55' . $ddd . substr($number, 1);
            }
            // Também tenta adicionar 9 no início (caso contrário)
            $variants[] = '55' . $ddd . '9' . $number;
        }
        // Caso 4: Números com 11 dígitos (após DDD) - pode ter 9º dígito extra
        // Exemplo: 5587999884234 (11 dígitos) - remove primeiro dígito para obter 10
        elseif ($numberLen === 11) {
            // Remove primeiro dígito (pode ser 9 extra)
            $variants[] = '55' . $ddd . substr($number, 1);
            // Se o primeiro dígito não é 9, também tenta adicionar 9 no início
            if (substr($number, 0, 1) !== '9') {
                $variants[] = '55' . $ddd . '9' . $number;
            }
        }

        if (empty($variants)) {
            return null;
        }

        $db = DB::getConnection();

        // CORREÇÃO: Mesmo canal obrigatório - evita misturar sessões (ex: Charles→ImobSites vs Charles→Pixel12).
        // Equivalente só para variação 9º dígito DENTRO da mesma sessão.
        $wantAccountId = $channelInfo['channel_account_id'] ?? null;
        foreach ($variants as $variantContactId) {
            try {
                $stmt = $db->prepare("
                    SELECT * FROM conversations 
                    WHERE channel_type = ? 
                    AND contact_external_id = ?
                    ORDER BY last_message_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$channelInfo['channel_type'], $variantContactId]);
                $found = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($found) {
                    $foundAccountId = isset($found['channel_account_id']) && $found['channel_account_id'] !== '' ? (int) $found['channel_account_id'] : null;
                    $sameChannel = ($wantAccountId === null && $foundAccountId === null)
                        || ($wantAccountId !== null && $foundAccountId === $wantAccountId);
                    if (!$sameChannel) {
                        error_log(sprintf(
                            '[DUPLICATE_PREVENTION] findEquivalentConversation IGNORADO (canal diferente): contact=%s, want_account_id=%s, found_id=%d, found_account_id=%s',
                            $contactExternalId,
                            $wantAccountId === null ? 'NULL' : $wantAccountId,
                            $found['id'],
                            $foundAccountId === null ? 'NULL' : $foundAccountId
                        ));
                        continue;
                    }
                    error_log(sprintf(
                        '[DUPLICATE_PREVENTION] findEquivalentConversation encontrou conversa equivalente: original=%s, variant=%s, found_id=%d, found_account_id=%s',
                        $contactExternalId,
                        $variantContactId,
                        $found['id'],
                        $found['channel_account_id'] ?? 'NULL'
                    ));
                    return $found;
                }
            } catch (\Exception $e) {
                error_log('[DUPLICATE_PREVENTION] Erro ao buscar variante: ' . $e->getMessage());
            }
        }

        return null;
    }

    /**
     * Busca conversa existente usando mapeamento @lid → E.164 do cache wa_pnlid_cache
     * 
     * Isso resolve o problema de duplicidade quando:
     * - Uma mensagem chega via @lid (ex: 103066917425370@lid)
     * - Já existe uma conversa com o número E.164 correspondente (ex: 5511988427530)
     * - Ou vice-versa
     * 
     * @param array $channelInfo Informações do canal
     * @return array|null Conversa encontrada ou null
     */
    private static function findConversationByLidPhoneMapping(array $channelInfo): ?array
    {
        // Aplica apenas para WhatsApp
        if ($channelInfo['channel_type'] !== 'whatsapp') {
            return null;
        }

        $contactId = $channelInfo['contact_external_id'] ?? '';
        if (empty($contactId)) {
            return null;
        }

        $db = DB::getConnection();
        $sessionId = $channelInfo['channel_id'] ?? $channelInfo['session_id'] ?? null;

        try {
            // Caso 1: contato é @lid - busca número E.164 correspondente
            if (strpos($contactId, '@lid') !== false) {
                $pnlid = str_replace('@lid', '', $contactId);
                
                // Busca no cache wa_pnlid_cache
                $cacheStmt = $db->prepare("
                    SELECT phone_e164 FROM wa_pnlid_cache 
                    WHERE pnlid = ? 
                    AND (session_id = ? OR session_id IS NULL)
                    LIMIT 1
                ");
                $cacheStmt->execute([$pnlid, $sessionId]);
                $cacheRow = $cacheStmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($cacheRow && !empty($cacheRow['phone_e164'])) {
                    $phoneE164 = $cacheRow['phone_e164'];
                    error_log("[LID_PHONE_MAPPING] @lid {$contactId} -> E.164 {$phoneE164}");
                    
                    // Busca conversa com esse número E.164
                    $convStmt = $db->prepare("
                        SELECT * FROM conversations 
                        WHERE channel_type = 'whatsapp' 
                        AND contact_external_id = ?
                        LIMIT 1
                    ");
                    $convStmt->execute([$phoneE164]);
                    $found = $convStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($found) {
                        error_log("[LID_PHONE_MAPPING] Encontrada conversa ID={$found['id']} com E.164 {$phoneE164} para @lid {$contactId}");
                        return $found;
                    }
                }
            }
            // Caso 2: contato é número E.164 - busca @lid correspondente
            else {
                $digits = preg_replace('/[^0-9]/', '', $contactId);
                
                // Verifica se parece com número E.164 brasileiro
                if (strlen($digits) >= 12 && strlen($digits) <= 13 && substr($digits, 0, 2) === '55') {
                    // Busca no cache wa_pnlid_cache
                    $cacheStmt = $db->prepare("
                        SELECT pnlid FROM wa_pnlid_cache 
                        WHERE phone_e164 = ? 
                        AND (session_id = ? OR session_id IS NULL)
                        LIMIT 1
                    ");
                    $cacheStmt->execute([$digits, $sessionId]);
                    $cacheRow = $cacheStmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($cacheRow && !empty($cacheRow['pnlid'])) {
                        $pnlidWithSuffix = $cacheRow['pnlid'] . '@lid';
                        error_log("[LID_PHONE_MAPPING] E.164 {$digits} -> @lid {$pnlidWithSuffix}");
                        
                        // Busca conversa com esse @lid
                        $convStmt = $db->prepare("
                            SELECT * FROM conversations 
                            WHERE channel_type = 'whatsapp' 
                            AND contact_external_id = ?
                            LIMIT 1
                        ");
                        $convStmt->execute([$pnlidWithSuffix]);
                        $found = $convStmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($found) {
                            error_log("[LID_PHONE_MAPPING] Encontrada conversa ID={$found['id']} com @lid {$pnlidWithSuffix} para E.164 {$digits}");
                            return $found;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("[LID_PHONE_MAPPING] Erro: " . $e->getMessage());
        }

        return null;
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
        // IMPORTANTE: Timestamps Unix são sempre em UTC, então convertemos explicitamente para UTC
        if ($timestamp !== null && is_numeric($timestamp)) {
            // Salva timezone atual
            $originalTimezone = date_default_timezone_get();
            
            // Define UTC para conversão
            date_default_timezone_set('UTC');
            
            try {
                // Se timestamp está em segundos (formato comum)
                if ($timestamp < 10000000000) {
                    $result = date('Y-m-d H:i:s', (int) $timestamp);
                }
                // Se timestamp está em milissegundos (formato WhatsApp)
                else {
                    $result = date('Y-m-d H:i:s', (int) ($timestamp / 1000));
                }
            } finally {
                // Restaura timezone original
                date_default_timezone_set($originalTimezone);
            }
            
            return $result;
        }
        
        // Fallback: usa NOW() em UTC se não conseguir extrair
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            $result = date('Y-m-d H:i:s');
        } finally {
            date_default_timezone_set($originalTimezone);
        }
        
        return $result;
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
     * Busca conversa duplicada por remote_key
     * 
     * CORREÇÃO: Previne criação de conversas duplicadas quando o mesmo contato aparece
     * com identificadores diferentes (ex: 169183207809126@lid vs 169183207809126).
     * 
     * A função remote_key() normaliza ambos para o mesmo valor (lid:169183207809126),
     * então podemos detectar duplicados mesmo quando contact_external_id é diferente.
     * 
     * @param array $channelInfo Informações do canal
     * @return array|null Conversa duplicada encontrada ou null
     */
    private static function findDuplicateByRemoteKey(array $channelInfo): ?array
    {
        $db = DB::getConnection();
        $threadKey = $channelInfo['thread_key'] ?? null;
        $remoteKey = $channelInfo['remote_key'] ?? null;

        try {
            // CORREÇÃO: Se temos thread_key (sessão+contato), busca só nessa sessão - evita reutilizar conversa de outra sessão (ex: ImobSites vs Pixel12).
            if (!empty($threadKey)) {
                $stmt = $db->prepare("
                    SELECT * FROM conversations 
                    WHERE channel_type = ? AND thread_key = ?
                    LIMIT 1
                ");
                $stmt->execute([$channelInfo['channel_type'], $threadKey]);
                $result = $stmt->fetch();
                return $result ?: null;
            }

            if (!$remoteKey) {
                return null;
            }

            // Fallback: busca por remote_key (sem sessão); prioriza thread_key preenchido
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE channel_type = ? 
                AND remote_key = ?
                ORDER BY 
                    CASE WHEN thread_key IS NOT NULL AND thread_key != '' THEN 0 ELSE 1 END,
                    last_message_at DESC
                LIMIT 1
            ");
            $stmt->execute([$channelInfo['channel_type'], $remoteKey]);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (\Exception $e) {
            error_log("[ConversationService] Erro ao buscar duplicado por remote_key: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca conversa apenas por contato (ignorando channel_account_id)
     * 
     * Usado para encontrar conversas "shared" quando uma nova conversa com tenant específico
     * está sendo criada, ou vice-versa.
     * 
     * MELHORIA: Agora também busca por variações do número (9º dígito, etc.)
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
            // Primeiro tenta busca exata
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE channel_type = ? 
                AND contact_external_id = ?
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            $stmt->execute([$channelInfo['channel_type'], $contactExternalId]);
            $result = $stmt->fetch();
            
            if ($result) {
                return $result;
            }
            
            // Se não encontrou, tenta buscar por variações usando findEquivalentConversation
            // Isso cobre casos como números com 9º dígito extra
            $equivalent = self::findEquivalentConversation($channelInfo, $contactExternalId);
            if ($equivalent) {
                // Verifica se a conversa equivalente não tem channel_account_id (é "shared")
                // ou se tem o mesmo channel_account_id
                if (empty($equivalent['channel_account_id']) || 
                    ($channelInfo['channel_account_id'] && 
                     $equivalent['channel_account_id'] == $channelInfo['channel_account_id'])) {
                    return $equivalent;
                }
            }
            
            return null;
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

    /**
     * Busca recursivamente por campos que podem conter número de telefone ou JID
     * Útil quando o payload tem estrutura não padrão
     * 
     * @param array|mixed $data Dados para buscar
     * @param int $depth Profundidade atual (evita recursão infinita)
     * @return string|null Número/JID encontrado ou null
     */
    private static function findPhoneOrJidRecursively($data, int $depth = 0): ?string
    {
        // Limita profundidade para evitar recursão infinita
        if ($depth > 5) {
            return null;
        }
        
        // Se não é array, verifica se é string que parece número/JID
        if (!is_array($data)) {
            if (is_string($data) && !empty($data)) {
                // Verifica se parece um JID ou número de telefone
                if (strpos($data, '@') !== false || preg_match('/^[0-9]{10,}$/', $data)) {
                    return $data;
                }
            }
            return null;
        }
        
        // Procura por chaves que geralmente contêm número/JID
        $phoneKeys = ['from', 'to', 'remoteJid', 'participant', 'author', 'jid', 'phone', 'number', 'sender'];
        foreach ($phoneKeys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && !empty($data[$key])) {
                $value = $data[$key];
                // Verifica se parece um JID ou número válido
                if (strpos($value, '@') !== false || preg_match('/^[0-9]{10,}$/', $value)) {
                    return $value;
                }
            }
        }
        
        // Busca recursivamente em arrays aninhados
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = self::findPhoneOrJidRecursively($value, $depth + 1);
                if ($found) {
                    return $found;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Normaliza channel_id para comparação (lowercase, remove espaços)
     * 
     * @param string|null $channelId
     * @return string|null
     */
    private static function normalizeChannelId(?string $channelId): ?string
    {
        if (empty($channelId)) {
            return null;
        }
        
        // Remove espaços e converte para lowercase
        $normalized = strtolower(trim($channelId));
        // Remove caracteres não alfanuméricos (mantém apenas letras, números e underscore)
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
        
        return $normalized ?: null;
    }
    
    /**
     * Resolve tenant_id pelo channel_id (com normalização)
     * 
     * @param string|null $channelId
     * @return int|null
     */
    private static function resolveTenantByChannelId(?string $channelId): ?int
    {
        if (empty($channelId)) {
            return null;
        }
        
        $db = DB::getConnection();
        
        // Normaliza channel_id para busca
        $normalized = self::normalizeChannelId($channelId);
        
        // Tenta busca exata primeiro
        $stmt = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND is_enabled = 1
            AND (
                channel_id = ?
                OR LOWER(TRIM(channel_id)) = ?
            )
            LIMIT 1
        ");
        $stmt->execute([$channelId, $normalized]);
        $result = $stmt->fetch();
        
        if ($result && $result['tenant_id']) {
            return (int) $result['tenant_id'];
        }
        
        // Se não encontrou, tenta busca case-insensitive mais flexível
        $stmt2 = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND is_enabled = 1
            AND LOWER(REPLACE(channel_id, ' ', '')) = ?
            LIMIT 1
        ");
        $stmt2->execute([$normalized]);
        $result2 = $stmt2->fetch();
        
        if ($result2 && $result2['tenant_id']) {
            return (int) $result2['tenant_id'];
        }
        
        return null;
    }

    /**
     * Busca conversa existente por contact_name dentro do mesmo tenant
     * 
     * Esta função é crucial para prevenir duplicidades quando:
     * - Uma conversa existe com telefone E.164 (ex: 5553811064884)
     * - Uma nova mensagem chega via @lid para o mesmo contato
     * - O @lid não tem mapeamento em whatsapp_business_ids
     * - Mas o contact_name é idêntico (ex: "Alessandra Karkow")
     * 
     * @param array $channelInfo Informações do canal
     * @param int|null $tenantId ID do tenant
     * @return array|null Conversa encontrada ou null
     */
    private static function findConversationByContactName(array $channelInfo, ?int $tenantId): ?array
    {
        $contactName = $channelInfo['contact_name'] ?? null;
        
        if (empty($contactName)) {
            return null;
        }

        // Aplica apenas para WhatsApp
        if ($channelInfo['channel_type'] !== 'whatsapp') {
            return null;
        }

        $db = DB::getConnection();
        
        try {
            // Busca conversa com mesmo contact_name, tenant_id E channel_id
            // REGRA: Só match no MESMO canal (evita Charles ImobSites vs Charles pixel12digital)
            $channelId = $channelInfo['channel_id'] ?? null;
            $stmt = $db->prepare("
                SELECT * FROM conversations 
                WHERE channel_type = 'whatsapp' 
                AND contact_name = ?
                AND (
                    (tenant_id IS NULL AND ? IS NULL)
                    OR tenant_id = ?
                )
                AND (
                    (channel_id IS NULL AND ? IS NULL)
                    OR (channel_id IS NOT NULL AND LOWER(TRIM(channel_id)) = LOWER(TRIM(?)))
                )
                AND contact_external_id NOT LIKE '%@lid'
                AND contact_external_id REGEXP '^[0-9]+$'
                ORDER BY last_message_at DESC
                LIMIT 1
            ");
            $stmt->execute([$contactName, $tenantId, $tenantId, $channelId, $channelId ?? '']);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($result) {
                error_log(sprintf(
                    '[DUPLICATE_PREVENTION] findConversationByContactName: Encontrada conversa existente! contact_name=%s, conversation_id=%d, existing_contact_id=%s',
                    $contactName,
                    $result['id'],
                    $result['contact_external_id']
                ));
                return $result;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log('[DUPLICATE_PREVENTION] findConversationByContactName: Erro: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verifica se dois contact_external_id representam o mesmo contato real.
     * REGRA: Nunca mergear por nome quando external_ids são diferentes (ex: 47 vs 11).
     *
     * @param string $newId O ID do evento (ex: 208989199560861@lid)
     * @param string $existingId O ID da conversa existente (ex: 5511940863773)
     * @param int|null $tenantId Tenant (opcional, para busca de mapeamento)
     * @return bool True se equivalentes (mesmo contato), False caso contrário
     */
    private static function contactExternalIdsAreEquivalent(string $newId, string $existingId, ?int $tenantId = null): bool
    {
        $newId = trim($newId);
        $existingId = trim($existingId);
        if (empty($newId) || empty($existingId)) {
            return false;
        }
        if ($newId === $existingId) {
            return true;
        }
        // Extrai dígitos para comparação numérica
        $newDigits = preg_replace('/[^0-9]/', '', $newId);
        $existingDigits = preg_replace('/[^0-9]/', '', $existingId);
        // Se ambos são números: variação 9º dígito BR
        if (strlen($newDigits) >= 10 && strlen($existingDigits) >= 10) {
            $normNew = PhoneNormalizer::toE164OrNull($newDigits, 'BR', false);
            $normExisting = PhoneNormalizer::toE164OrNull($existingDigits, 'BR', false);
            if ($normNew && $normExisting && $normNew === $normExisting) {
                return true;
            }
            // Variação 9º dígito: 5547996164699 vs 554796164699
            if ($normNew && $normExisting) {
                $lenNew = strlen($normNew);
                $lenExisting = strlen($normExisting);
                if ($lenNew === 13 && $lenExisting === 12 &&
                    substr($normNew, 0, 4) === substr($normExisting, 0, 4) &&
                    substr($normNew, 4, 1) === '9' &&
                    substr($normNew, 5) === substr($normExisting, 4)) {
                    return true;
                }
                if ($lenNew === 12 && $lenExisting === 13 &&
                    substr($normExisting, 0, 4) === substr($normNew, 0, 4) &&
                    substr($normExisting, 4, 1) === '9' &&
                    substr($normExisting, 5) === substr($normNew, 4)) {
                    return true;
                }
            }
        }
        // Se new é @lid e existing é phone: verifica mapeamento em whatsapp_business_ids
        if (strpos($newId, '@lid') !== false && preg_match('/^[0-9]{10,15}$/', $existingDigits)) {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT 1 FROM whatsapp_business_ids 
                WHERE business_id = ? AND phone_number = ?
                LIMIT 1
            ");
            $stmt->execute([$newId, $existingDigits]);
            if ($stmt->fetch()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cria mapeamento @lid -> telefone na tabela whatsapp_business_ids
     * 
     * Esta função é chamada quando detectamos que um @lid corresponde a um telefone
     * conhecido (via contact_name match). Isso garante que futuras mensagens
     * com esse @lid sejam corretamente associadas à conversa existente.
     * 
     * @param string $lidId O identificador @lid (ex: "253815605489835@lid")
     * @param string $phoneNumber O número E.164 correspondente (ex: "5553811064884")
     * @param int|null $tenantId ID do tenant (opcional)
     * @return bool True se criou/atualizou o mapeamento, False se falhou
     */
    private static function createLidPhoneMapping(string $lidId, string $phoneNumber, ?int $tenantId = null): bool
    {
        // Valida que lidId realmente é um @lid
        if (strpos($lidId, '@lid') === false) {
            return false;
        }

        // Valida que phoneNumber parece um número E.164
        if (!preg_match('/^[0-9]{10,15}$/', $phoneNumber)) {
            return false;
        }

        $db = DB::getConnection();
        
        try {
            // Tenta inserir, ignora se já existe (UNIQUE KEY em business_id)
            $stmt = $db->prepare("
                INSERT IGNORE INTO whatsapp_business_ids 
                (business_id, phone_number, tenant_id, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $result = $stmt->execute([$lidId, $phoneNumber, $tenantId]);
            
            if ($stmt->rowCount() > 0) {
                error_log(sprintf(
                    '[LID_PHONE_MAPPING] Mapeamento CRIADO: %s -> %s (tenant_id=%s)',
                    $lidId,
                    $phoneNumber,
                    $tenantId ?? 'NULL'
                ));
            } else {
                // Já existia - tenta atualizar o phone_number se diferente
                $updateStmt = $db->prepare("
                    UPDATE whatsapp_business_ids 
                    SET phone_number = ?, tenant_id = COALESCE(?, tenant_id), updated_at = NOW()
                    WHERE business_id = ? AND phone_number != ?
                ");
                $updateStmt->execute([$phoneNumber, $tenantId, $lidId, $phoneNumber]);
                
                if ($updateStmt->rowCount() > 0) {
                    error_log(sprintf(
                        '[LID_PHONE_MAPPING] Mapeamento ATUALIZADO: %s -> %s (tenant_id=%s)',
                        $lidId,
                        $phoneNumber,
                        $tenantId ?? 'NULL'
                    ));
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('[LID_PHONE_MAPPING] Erro ao criar mapeamento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Valida se o telefone do contato corresponde ao telefone do tenant
     * 
     * CORREÇÃO: Evita vinculação automática incorreta de conversas a tenants
     * quando o número do contato não pertence ao tenant.
     * 
     * @param string $contactExternalId Telefone/ID do contato (pode conter @lid)
     * @param int $tenantId ID do tenant a validar
     * @return bool True se o telefone corresponde, False caso contrário
     */
    private static function validatePhoneBelongsToTenant(string $contactExternalId, int $tenantId): bool
    {
        try {
            $db = DB::getConnection();
            
            // Busca telefone do tenant
            $stmt = $db->prepare("SELECT phone FROM tenants WHERE id = ? LIMIT 1");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();
            
            if (!$tenant || empty($tenant['phone'])) {
                // Se tenant não tem telefone cadastrado, não valida (permite vincular)
                // Isso mantém comportamento para tenants sem telefone
                error_log(sprintf(
                    '[TENANT_PHONE_VALIDATION] Tenant ID=%d não tem telefone cadastrado - permitindo vinculação',
                    $tenantId
                ));
                return true;
            }
            
            // Normaliza ambos os números
            $normalizePhone = function($phone) {
                if (empty($phone)) return null;
                // Remove @lid e tudo após @
                $cleaned = preg_replace('/@.*$/', '', (string) $phone);
                // Remove tudo exceto dígitos
                return preg_replace('/[^0-9]/', '', $cleaned);
            };
            
            $contactPhone = $normalizePhone($contactExternalId);
            $tenantPhone = $normalizePhone($tenant['phone']);
            
            if (empty($contactPhone)) {
                error_log(sprintf(
                    '[TENANT_PHONE_VALIDATION] Contato sem telefone válido: %s - rejeitando vinculação',
                    $contactExternalId
                ));
                return false;
            }
            
            // Comparação exata
            if ($contactPhone === $tenantPhone) {
                return true;
            }
            
            // Se são números BR (começam com 55 e têm pelo menos 12 dígitos), 
            // tenta comparar com/sem 9º dígito
            if (strlen($contactPhone) >= 12 && strlen($tenantPhone) >= 12 && 
                substr($contactPhone, 0, 2) === '55' && substr($tenantPhone, 0, 2) === '55') {
                
                // Remove 9º dígito de ambos para comparação (13 dígitos = 55 + DDD + 9 + 8 dígitos)
                if (strlen($contactPhone) === 13 && strlen($tenantPhone) === 13) {
                    $contactWithout9th = substr($contactPhone, 0, 4) . substr($contactPhone, 5);
                    $tenantWithout9th = substr($tenantPhone, 0, 4) . substr($tenantPhone, 5);
                    
                    if ($contactWithout9th === $tenantWithout9th) {
                        return true;
                    }
                }
                
                // Tenta adicionar 9º dígito em ambos (12 dígitos = 55 + DDD + 8 dígitos)
                if (strlen($contactPhone) === 12 && strlen($tenantPhone) === 12) {
                    $contactWith9th = substr($contactPhone, 0, 4) . '9' . substr($contactPhone, 4);
                    $tenantWith9th = substr($tenantPhone, 0, 4) . '9' . substr($tenantPhone, 4);
                    
                    if ($contactWith9th === $tenantWith9th) {
                        return true;
                    }
                }
                
                // Comparação cruzada: contato com 13 dígitos vs tenant com 12 (ou vice-versa)
                if (strlen($contactPhone) === 13 && strlen($tenantPhone) === 12) {
                    $contactWithout9th = substr($contactPhone, 0, 4) . substr($contactPhone, 5);
                    if ($contactWithout9th === $tenantPhone) {
                        return true;
                    }
                }
                
                if (strlen($contactPhone) === 12 && strlen($tenantPhone) === 13) {
                    $tenantWithout9th = substr($tenantPhone, 0, 4) . substr($tenantPhone, 5);
                    if ($contactPhone === $tenantWithout9th) {
                        return true;
                    }
                }
            }
            
            // Números não correspondem
            error_log(sprintf(
                '[TENANT_PHONE_VALIDATION] Telefone NÃO corresponde: contato=%s, tenant=%s (tenant_id=%d) - rejeitando vinculação',
                $contactPhone,
                $tenantPhone,
                $tenantId
            ));
            return false;
            
        } catch (\Exception $e) {
            error_log('[TENANT_PHONE_VALIDATION] Erro ao validar: ' . $e->getMessage());
            // Em caso de erro, não vincula (segurança)
            return false;
        }
    }
}


