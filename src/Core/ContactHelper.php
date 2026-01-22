<?php

namespace PixelHub\Core;

use PixelHub\Core\DB;
use PDO;

/**
 * Helper para formatação de identificadores de contato
 */
class ContactHelper
{
    /**
     * Cache de existência de tabelas (uma vez por request)
     * @var array|null
     */
    private static ?array $tableExistsCache = null;

    /**
     * Formata um identificador de contato para exibição amigável
     * 
     * Ordem de prioridade:
     * 1. Se tiver tenantPhone, exibe como telefone formatado (XX) XXXXX-XXXX
     * 2. Se for @lid sem resolução, exibe como "ID WhatsApp: XXXX XXXX XXXX" (não como telefone)
     * 3. Se for número normal, formata como telefone brasileiro
     * 
     * IMPORTANTE: Este método é apenas para EXIBIÇÃO. Não altera a lógica interna de identificação.
     * O contactId original continua sendo usado para buscar/envio de mensagens e vinculação.
     * 
     * @param string|null $contactId Identificador do contato (pode ser número ou @lid)
     * @param string|null $tenantPhone Número real do tenant (prioridade - já resolvido)
     * @return string Identificador formatado para exibição
     */
    public static function formatContactId(?string $contactId, ?string $tenantPhone = null): string
    {
        if (empty($contactId)) {
            return 'Número não identificado';
        }

        // Garante que contactId é string
        $contactId = (string) $contactId;

        // Se temos o número real do tenant, usa ele (prioridade)
        if (!empty($tenantPhone)) {
            return self::formatPhoneNumber((string) $tenantPhone);
        }

        // Verifica se é um @lid
        if (strpos($contactId, '@lid') !== false) {
            // Se não temos o número real do tenant (que tem prioridade),
            // exibe como ID WhatsApp (não como telefone) para evitar confusão
            if (empty($tenantPhone)) {
                // Extrai apenas os dígitos do @lid (remove o @lid)
                $lidDigits = preg_replace('/[^0-9]/', '', str_replace('@lid', '', $contactId));
                
                // Se conseguiu extrair dígitos, exibe como ID WhatsApp rotulado
                if (!empty($lidDigits)) {
                    $grouped = trim(chunk_split($lidDigits, 4, ' '));
                    return 'ID WhatsApp: ' . $grouped;
                }
                
                // Se não conseguiu extrair dígitos, exibe mensagem genérica
                return 'ID WhatsApp não disponível';
            }
        }

        // Se não for @lid, tenta formatar como telefone normal
        return self::formatPhoneNumber($contactId);
    }

    /**
     * Formata um número de telefone para exibição
     * 
     * @param string $phone Número de telefone (apenas dígitos)
     * @return string Número formatado
     */
    private static function formatPhoneNumber(string $phone): string
    {
        // Remove tudo que não for dígito
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        if (empty($digits)) {
            return $phone; // Retorna original se não tiver dígitos
        }

        // Remove código do país (55) se presente
        if (strlen($digits) > 11 && substr($digits, 0, 2) === '55') {
            $digits = substr($digits, 2);
        }

        // Formata baseado no tamanho
        if (strlen($digits) === 11) {
            // Celular: (XX) 9XXXX-XXXX
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 5),
                substr($digits, 7)
            );
        } elseif (strlen($digits) === 10) {
            // Fixo: (XX) XXXX-XXXX
            return sprintf(
                '(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6)
            );
        }

        // Se não se encaixar em nenhum padrão, retorna os dígitos agrupados
        // Agrupa de 4 em 4 para facilitar leitura e remove espaço final
        return trim(chunk_split($digits, 4, ' '));
    }

    /**
     * Extrai número de telefone do payload de um evento WhatsApp
     * 
     * Busca o número formatado no payload (ex: formattedName no sender)
     * 
     * @param array|string|null $payload Payload do evento (array ou JSON string)
     * @return string|null Número em formato E.164 ou null se não encontrado
     */
    public static function extractPhoneFromPayload($payload): ?string
    {
        if (empty($payload)) {
            return null;
        }
        
        // Se for string JSON, decodifica
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
            if (!is_array($payload)) {
                return null;
            }
        }
        
        if (!is_array($payload)) {
            return null;
        }
        
        // Tenta extrair do formattedName (caminho mais comum)
        $formattedName = null;
        if (isset($payload['raw']['payload']['sender']['formattedName'])) {
            $formattedName = $payload['raw']['payload']['sender']['formattedName'];
        } elseif (isset($payload['sender']['formattedName'])) {
            $formattedName = $payload['sender']['formattedName'];
        } elseif (isset($payload['message']['sender']['formattedName'])) {
            $formattedName = $payload['message']['sender']['formattedName'];
        }
        
        if (!empty($formattedName)) {
            // Remove tudo que não é dígito ou +, depois normaliza
            $digits = preg_replace('/[^0-9+]/', '', $formattedName);
            if (!empty($digits)) {
                // Remove o + se houver
                $digits = str_replace('+', '', $digits);
                // Normaliza para E.164 (só dígitos)
                if (strlen($digits) >= 10) {
                    return $digits;
                }
            }
        }
        
        return null;
    }

    /**
     * Busca número de telefone nos eventos recentes de uma conversa
     * 
     * @param string $contactId Identificador do contato (ex: "56083800395891@lid")
     * @param string|null $sessionId ID da sessão WhatsApp
     * @return string|null Número em formato E.164 ou null se não encontrado
     */
    private static function resolveLidPhoneFromEvents(string $contactId, ?string $sessionId = null): ?string
    {
        if (empty($contactId) || strpos($contactId, '@lid') === false) {
            return null;
        }
        
        try {
            if (!class_exists('\PixelHub\Core\DB')) {
                return null;
            }
            
            $db = DB::getConnection();
            if (!$db) {
                return null;
            }
            
            // Busca eventos recentes com esse contactId
            $where = ["ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"];
            $params = [];
            
            // Busca por from ou to contendo o contactId
            $contactPattern = "%{$contactId}%";
            $where[] = "(JSON_EXTRACT(ce.payload, '$.from') LIKE ? OR JSON_EXTRACT(ce.payload, '$.to') LIKE ? OR JSON_EXTRACT(ce.payload, '$.message.from') LIKE ? OR JSON_EXTRACT(ce.payload, '$.message.to') LIKE ?)";
            $params = array_merge($params, [$contactPattern, $contactPattern, $contactPattern, $contactPattern]);
            
            // Se tiver sessionId, filtra por ele também
            if (!empty($sessionId)) {
                $where[] = "(JSON_EXTRACT(ce.metadata, '$.channel_id') = ? OR JSON_EXTRACT(ce.payload, '$.session.id') = ? OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?)";
                $params = array_merge($params, [$sessionId, $sessionId, $sessionId]);
            }
            
            $stmt = $db->prepare("
                SELECT ce.payload
                FROM communication_events ce
                WHERE " . implode(" AND ", $where) . "
                ORDER BY ce.created_at DESC
                LIMIT 10
            ");
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Tenta extrair o número de cada evento
            foreach ($events as $event) {
                $phone = self::extractPhoneFromPayload($event['payload']);
                if (!empty($phone)) {
                    // Encontrou! Cria mapeamento automático para próximas buscas
                    $lidBusinessId = $contactId;
                    $lidId = str_replace('@lid', '', $contactId);
                    
                    try {
                        // Tenta criar mapeamento persistente
                        if (self::tableExists($db, 'whatsapp_business_ids')) {
                            $insertStmt = $db->prepare("
                                INSERT IGNORE INTO whatsapp_business_ids (business_id, phone_number)
                                VALUES (?, ?)
                            ");
                            $insertStmt->execute([$lidBusinessId, $phone]);
                        }
                        
                        // Também salva no cache
                        if (!empty($sessionId) && self::tableExists($db, 'wa_pnlid_cache')) {
                            $cacheStmt = $db->prepare("
                                INSERT INTO wa_pnlid_cache (provider, session_id, pnlid, phone_e164)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE phone_e164=VALUES(phone_e164), updated_at=NOW()
                            ");
                            $cacheStmt->execute(['wpp_gateway', $sessionId, $lidId, $phone]);
                        }
                    } catch (\Exception $e) {
                        // Ignora erro de inserção, mas retorna o número encontrado
                        error_log("Erro ao criar mapeamento LID: " . $e->getMessage());
                    }
                    
                    return $phone;
                }
            }
            
        } catch (\Exception $e) {
            // Ignora erro, retorna null
        }
        
        return null;
    }

    /**
     * Resolve um @lid para número de telefone real via cache e mapeamento
     * 
     * Este método consulta as tabelas de cache/mapeamento (sem chamadas HTTP)
     * para tentar obter o número real associado a um @lid.
     * 
     * Ordem de prioridade:
     * 1. whatsapp_business_ids (mapeamento persistente)
     * 2. wa_pnlid_cache (cache de resoluções via API)
     * 3. communication_events (busca no payload dos eventos recentes)
     * 
     * @param string $contactId Identificador do contato (ex: "65111721042059@lid")
     * @param string|null $sessionId ID da sessão WhatsApp (opcional, para consulta no cache)
     * @param string|null $provider Provider do WhatsApp (opcional, padrão: 'wpp_gateway')
     * @return string|null Número de telefone em formato E.164 (ex: "554796474223") ou null se não encontrado
     */
    public static function resolveLidPhone(string $contactId, ?string $sessionId = null, ?string $provider = 'wpp_gateway'): ?string
    {
        // Validação de tipo e conteúdo
        if (empty($contactId)) {
            return null;
        }
        
        // Garante que é string
        $contactId = (string) $contactId;
        
        if (strpos($contactId, '@lid') === false) {
            return null;
        }

        try {
            // Verifica se a classe DB está disponível
            if (!class_exists('\PixelHub\Core\DB')) {
                return null;
            }
            
            try {
                $db = DB::getConnection();
                if (!$db) {
                    return null;
                }
            } catch (\Exception $e) {
                // Se falhar ao conectar, retorna null silenciosamente
                return null;
            } catch (\Throwable $e) {
                // Captura qualquer erro fatal
                return null;
            }
            
            // Extrai o pnLid (sem @lid)
            $lidId = str_replace('@lid', '', $contactId);
            $lidBusinessId = $lidId . '@lid';
            
            // 1. Tenta buscar na tabela whatsapp_business_ids (mapeamento persistente)
            try {
                if (self::tableExists($db, 'whatsapp_business_ids')) {
                    $stmt = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->execute([$lidBusinessId]);
                        $mapping = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($mapping && !empty($mapping['phone_number'])) {
                            return $mapping['phone_number'];
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignora erro, continua para próxima tentativa
            } catch (\Throwable $e) {
                // Ignora erro, continua para próxima tentativa
            }
            
            // 2. Tenta buscar no cache wa_pnlid_cache (se tiver sessionId)
            if (!empty($sessionId) && !empty($provider)) {
                try {
                    if (self::tableExists($db, 'wa_pnlid_cache')) {
                        $stmt = $db->prepare("
                            SELECT phone_e164, updated_at 
                            FROM wa_pnlid_cache 
                            WHERE provider = ? AND session_id = ? AND pnlid = ? 
                            AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            LIMIT 1
                        ");
                        if ($stmt) {
                            $stmt->execute([$provider, $sessionId, $lidId]);
                            $cached = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($cached && !empty($cached['phone_e164'])) {
                                return $cached['phone_e164'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignora erro, retorna null
                } catch (\Throwable $e) {
                    // Ignora erro, retorna null
                }
            }
            
            // 3. NOVA: Tenta buscar nos eventos recentes (extrai do payload)
            try {
                $phoneFromEvents = self::resolveLidPhoneFromEvents($contactId, $sessionId);
                if (!empty($phoneFromEvents)) {
                    return $phoneFromEvents;
                }
            } catch (\Exception $e) {
                // Ignora erro, continua
            } catch (\Throwable $e) {
                // Ignora erro, continua
            }
            
        } catch (\Exception $e) {
            // Em caso de erro geral (ex: conexão DB), retorna null silenciosamente
            // Não queremos quebrar a exibição se o cache falhar
            return null;
        } catch (\Throwable $e) {
            // Captura qualquer erro fatal também
            return null;
        }
        
        return null;
    }

    /**
     * Verifica se uma tabela existe (com cache por request)
     * 
     * @param PDO $db Conexão do banco
     * @param string $tableName Nome da tabela
     * @return bool True se existe, False caso contrário
     */
    private static function tableExists(PDO $db, string $tableName): bool
    {
        // Inicializa cache na primeira chamada
        if (self::$tableExistsCache === null) {
            self::$tableExistsCache = [];
            
            try {
                // Consulta information_schema (mais eficiente que SHOW TABLES)
                $stmt = $db->query("
                    SELECT TABLE_NAME 
                    FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME IN ('whatsapp_business_ids', 'wa_pnlid_cache')
                ");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($tables as $table) {
                    self::$tableExistsCache[$table] = true;
                }
            } catch (\Exception $e) {
                // Em caso de erro, cache vazio (assume que não existem)
                error_log("Erro ao verificar tabelas: " . $e->getMessage());
            }
        }
        
        return self::$tableExistsCache[$tableName] ?? false;
    }

    /**
     * Resolve múltiplos @lid para números de telefone em lote (otimizado)
     * 
     * Este método resolve todos os @lid de uma vez usando queries em lote,
     * evitando o problema N+1 de queries.
     * 
     * @param array $lidData Array de arrays com ['contactId' => string, 'sessionId' => ?string]
     * @param string $provider Provider padrão (opcional, padrão: 'wpp_gateway')
     * @return array Mapa [lidDigits => phoneE164] ou [lidBusinessId => phoneE164]
     */
    public static function resolveLidPhonesBatch(array $lidData, string $provider = 'wpp_gateway'): array
    {
        if (empty($lidData)) {
            return [];
        }

        try {
            // Verifica se a classe DB está disponível
            if (!class_exists('\PixelHub\Core\DB')) {
                return [];
            }
            
            try {
                $db = DB::getConnection();
                if (!$db) {
                    return [];
                }
            } catch (\Exception $e) {
                return [];
            } catch (\Throwable $e) {
                return [];
            }

            // Extrai todos os lidId e businessId únicos
            $lidIds = [];
            $businessIds = [];
            $sessionIds = [];
            
            foreach ($lidData as $item) {
                $contactId = (string) ($item['contactId'] ?? '');
                if (empty($contactId) || strpos($contactId, '@lid') === false) {
                    continue;
                }
                
                $lidId = str_replace('@lid', '', $contactId);
                $businessId = $lidId . '@lid';
                
                $lidIds[$lidId] = true;
                $businessIds[$businessId] = $lidId; // Mapeia businessId -> lidId
                
                $sessionId = $item['sessionId'] ?? null;
                if (!empty($sessionId)) {
                    $sessionIds[$sessionId] = true;
                }
            }
            
            if (empty($lidIds)) {
                return [];
            }
            
            $resultMap = [];
            
            // 1. Busca em whatsapp_business_ids (mapeamento persistente)
            if (self::tableExists($db, 'whatsapp_business_ids') && !empty($businessIds)) {
                try {
                    $placeholders = str_repeat('?,', count($businessIds) - 1) . '?';
                    $stmt = $db->prepare("
                        SELECT business_id, phone_number 
                        FROM whatsapp_business_ids 
                        WHERE business_id IN ({$placeholders})
                    ");
                    $stmt->execute(array_keys($businessIds));
                    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($mappings as $mapping) {
                        if (!empty($mapping['phone_number'])) {
                            $businessId = $mapping['business_id'];
                            $lidId = $businessIds[$businessId] ?? null;
                            if ($lidId) {
                                $resultMap[$lidId] = $mapping['phone_number'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignora erro, continua
                }
            }
            
            // 2. Busca em wa_pnlid_cache (se tiver sessionIds e não tiver resultado ainda)
            if (self::tableExists($db, 'wa_pnlid_cache') && !empty($sessionIds) && !empty($lidIds)) {
                try {
                    // Pega apenas os lidIds que ainda não foram resolvidos
                    $unresolvedLidIds = array_diff_key($lidIds, $resultMap);
                    if (!empty($unresolvedLidIds)) {
                        $lidPlaceholders = str_repeat('?,', count($unresolvedLidIds) - 1) . '?';
                        $sessionPlaceholders = str_repeat('?,', count($sessionIds) - 1) . '?';
                        
                        // TTL de 30 dias
                        $ttlDays = 30;
                        $stmt = $db->prepare("
                            SELECT pnlid, phone_e164, session_id, updated_at
                            FROM wa_pnlid_cache 
                            WHERE provider = ? 
                            AND session_id IN ({$sessionPlaceholders})
                            AND pnlid IN ({$lidPlaceholders})
                            AND updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                        ");
                        $params = array_merge(
                            [$provider],
                            array_keys($sessionIds),
                            array_keys($unresolvedLidIds),
                            [$ttlDays]
                        );
                        $stmt->execute($params);
                        $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($cached as $cache) {
                            if (!empty($cache['phone_e164']) && !isset($resultMap[$cache['pnlid']])) {
                                $resultMap[$cache['pnlid']] = $cache['phone_e164'];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignora erro
                }
            }
            
            // 3. NOVA: Para os não resolvidos, tenta buscar nos eventos (em lote otimizado)
            $unresolvedItems = [];
            foreach ($lidData as $item) {
                $contactId = (string) ($item['contactId'] ?? '');
                if (empty($contactId) || strpos($contactId, '@lid') === false) {
                    continue;
                }
                
                $lidId = str_replace('@lid', '', $contactId);
                // Só adiciona se ainda não foi resolvido
                if (!isset($resultMap[$lidId])) {
                    $unresolvedItems[] = [
                        'contactId' => $contactId,
                        'lidId' => $lidId,
                        'sessionId' => $item['sessionId'] ?? null
                    ];
                }
            }
            
            // Busca nos eventos apenas para os não resolvidos (limitado para performance)
            if (!empty($unresolvedItems) && count($unresolvedItems) <= 50) {
                try {
                    foreach ($unresolvedItems as $item) {
                        $phoneFromEvents = self::resolveLidPhoneFromEvents($item['contactId'], $item['sessionId']);
                        if (!empty($phoneFromEvents)) {
                            $resultMap[$item['lidId']] = $phoneFromEvents;
                        }
                    }
                } catch (\Exception $e) {
                    // Ignora erro, mas loga para debug
                    error_log("Erro ao buscar nos eventos em lote: " . $e->getMessage());
                }
            }
            
            return $resultMap;
            
        } catch (\Exception $e) {
            return [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

