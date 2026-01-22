<?php

namespace PixelHub\Core;

use PixelHub\Core\DB;
use PixelHub\Services\PhoneNormalizer;
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
     * @param string $contactId Identificador do contato (ex: "56083800395891@lid" ou "56083800395891@lid" quando digits-only)
     * @param string|null $sessionId ID da sessão WhatsApp
     * @return string|null Número em formato E.164 ou null se não encontrado
     */
    private static function resolveLidPhoneFromEvents(string $contactId, ?string $sessionId = null): ?string
    {
        // Aceita tanto @lid quanto businessId (que sempre tem @lid)
        if (empty($contactId) || (strpos($contactId, '@lid') === false && !preg_match('/^[0-9]{14,20}$/', $contactId))) {
            return null;
        }
        
        // Se for digits-only, converte para businessId
        if (strpos($contactId, '@lid') === false && preg_match('/^[0-9]{14,20}$/', $contactId)) {
            $contactId = $contactId . '@lid';
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
     * Detecta se um identificador é um pnlid (LID) mesmo sem sufixo @lid
     * 
     * Regras:
     * - Digits-only com 14-20 caracteres
     * - NÃO começa com "55" (não é E.164 brasileiro)
     * - Não contém @ (não é JID)
     * 
     * @param string $contactId Identificador a verificar
     * @return array|null ['lidId' => string, 'businessId' => string] ou null se não for pnlid
     */
    private static function detectLidWithoutSuffix(string $contactId): ?array
    {
        if (empty($contactId)) {
            return null;
        }
        
        $contactId = (string) $contactId;
        
        // Se já tem @lid, não precisa detectar
        if (strpos($contactId, '@lid') !== false) {
            return null;
        }
        
        // Se tem @ (é JID), não é pnlid digits-only
        if (strpos($contactId, '@') !== false) {
            return null;
        }
        
        // Remove tudo que não é dígito
        $digits = preg_replace('/[^0-9]/', '', $contactId);
        
        // Verifica se é apenas dígitos e tem comprimento suspeito de pnlid (14-20)
        if ($digits === $contactId && strlen($digits) >= 14 && strlen($digits) <= 20) {
            // Se começa com 55 e tem 12-13 dígitos, é E.164 brasileiro, não pnlid
            if (strlen($digits) <= 13 && substr($digits, 0, 2) === '55') {
                return null;
            }
            
            // É pnlid digits-only
            return [
                'lidId' => $digits,
                'businessId' => $digits . '@lid'
            ];
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
     * 4. resolvePnLidViaProvider (API do gateway)
     * 
     * @param string $contactId Identificador do contato (ex: "65111721042059@lid" ou "169183207809126")
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
        
        // Detecta se é @lid com sufixo ou pnlid digits-only
        $lidInfo = null;
        if (strpos($contactId, '@lid') !== false) {
            // Tem sufixo @lid
            $lidId = str_replace('@lid', '', $contactId);
            $lidInfo = [
                'lidId' => $lidId,
                'businessId' => $lidId . '@lid'
            ];
        } else {
            // Tenta detectar como pnlid digits-only
            $lidInfo = self::detectLidWithoutSuffix($contactId);
        }
        
        // Se não é LID, retorna null
        if (!$lidInfo) {
            return null;
        }
        
        $lidId = $lidInfo['lidId'];
        $lidBusinessId = $lidInfo['businessId'];

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
            
            // 2. Tenta buscar no cache wa_pnlid_cache (com sessionId se disponível, depois sem sessionId como fallback)
            if (!empty($provider)) {
                try {
                    if (self::tableExists($db, 'wa_pnlid_cache')) {
                        // Primeiro tenta com sessionId (se disponível)
                        if (!empty($sessionId)) {
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
                        
                        // Fallback: tenta sem sessionId (apenas provider + pnlid)
                        $stmt = $db->prepare("
                            SELECT phone_e164, updated_at 
                            FROM wa_pnlid_cache 
                            WHERE provider = ? AND pnlid = ? 
                            AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            ORDER BY updated_at DESC
                            LIMIT 1
                        ");
                        if ($stmt) {
                            $stmt->execute([$provider, $lidId]);
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
            
            // 3. Tenta buscar nos eventos recentes (extrai do payload)
            // Usa contactId original se tiver @lid, senão usa businessId
            $contactIdForEvents = strpos($contactId, '@lid') !== false ? $contactId : $lidBusinessId;
            try {
                $phoneFromEvents = self::resolveLidPhoneFromEvents($contactIdForEvents, $sessionId);
                if (!empty($phoneFromEvents)) {
                    return $phoneFromEvents;
                }
            } catch (\Exception $e) {
                // Ignora erro, continua
            } catch (\Throwable $e) {
                // Ignora erro, continua
            }
            
            // 4. ÚLTIMA CAMADA: Tenta resolver via API do provider
            try {
                $phoneFromProvider = self::resolvePnLidViaProvider($provider, $sessionId, $lidId);
                if (!empty($phoneFromProvider)) {
                    // Garante persistência do mapeamento encontrado
                    self::saveLidMapping($db, $lidBusinessId, $phoneFromProvider, $sessionId, $provider, $lidId);
                    return $phoneFromProvider;
                }
            } catch (\Exception $e) {
                // Log discreto apenas quando falhar (para troubleshooting)
                error_log("[ContactHelper::resolveLidPhone] Erro ao resolver via provider: " . $e->getMessage());
            } catch (\Throwable $e) {
                error_log("[ContactHelper::resolveLidPhone] Erro fatal ao resolver via provider: " . $e->getMessage());
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
     * Resolve @lid via API do provider (gateway)
     * 
     * @param string $provider Provider do WhatsApp (ex: 'wpp_gateway')
     * @param string|null $sessionId ID da sessão WhatsApp (pode ser null)
     * @param string $pnLid ID do @lid sem sufixo (ex: "56083800395891")
     * @return string|null Número de telefone em formato E.164 ou null se não encontrado
     */
    private static function resolvePnLidViaProvider(string $provider, ?string $sessionId, string $pnLid): ?string
    {
        // Apenas para wpp_gateway por enquanto
        if ($provider !== 'wpp_gateway') {
            return null;
        }
        
        // Se não tem sessionId, não pode chamar API (endpoint requer sessionId)
        if (empty($sessionId)) {
            return null;
        }
        
        try {
            // Endpoint do gateway: /api/{sessionId}/contact/pn-lid/{pnLid}
            $baseUrl = \PixelHub\Core\Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
            if (empty($baseUrl)) {
                return null;
            }
            
            // Obtém secret para autenticação
            try {
                $secret = \PixelHub\Services\GatewaySecret::getDecrypted();
            } catch (\Exception $e) {
                return null;
            }
            
            if (empty($secret)) {
                return null;
            }
            
            $url = rtrim($baseUrl, '/') . '/api/' . rawurlencode($sessionId) . '/contact/pn-lid/' . rawurlencode($pnLid);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'X-Gateway-Secret: ' . $secret
                ],
            ]);
            
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($code < 200 || $code >= 300 || !$raw) {
                // Log discreto apenas quando falhar (para troubleshooting)
                // 404 é esperado quando não encontra, não precisa logar
                if ($code !== 404 && $code !== 0) {
                    error_log("[ContactHelper::resolvePnLidViaProvider] HTTP {$code} para pnLid={$pnLid}, sessionId={$sessionId}" . ($curlError ? ", curl_error={$curlError}" : ""));
                }
                return null;
            }
            
            $j = json_decode($raw, true);
            if (!is_array($j)) {
                return null;
            }
            
            // Tenta extrair telefone em campos comuns
            $candidates = [
                $j['phone'] ?? null,
                $j['number'] ?? null,
                $j['wid'] ?? null,
                $j['id']['user'] ?? null,
                $j['user'] ?? null,
                $j['contact']['number'] ?? null,
                $j['contact']['phone'] ?? null,
                $j['data']['phone'] ?? null,
                $j['data']['number'] ?? null,
            ];
            
            foreach ($candidates as $cand) {
                if ($cand) {
                    $e164 = PhoneNormalizer::toE164OrNull($cand, 'BR', false);
                    if ($e164) {
                        return $e164;
                    }
                }
            }
            
            // Se vier no formato JID:
            if (!empty($j['jid'])) {
                $e164 = PhoneNormalizer::toE164OrNull($j['jid'], 'BR', false);
                if ($e164) {
                    return $e164;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            // Log discreto apenas quando falhar (para troubleshooting)
            error_log("[ContactHelper::resolvePnLidViaProvider] Exceção: " . $e->getMessage());
            return null;
        } catch (\Throwable $e) {
            error_log("[ContactHelper::resolvePnLidViaProvider] Erro fatal: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Salva mapeamento @lid → telefone nas tabelas de cache/mapeamento
     * 
     * @param PDO $db Conexão do banco
     * @param string $businessId ID completo com @lid (ex: "56083800395891@lid")
     * @param string $phoneE164 Número em formato E.164
     * @param string|null $sessionId ID da sessão (opcional)
     * @param string $provider Provider (padrão: 'wpp_gateway')
     * @param string $lidId ID sem @lid (ex: "56083800395891")
     * @return void
     */
    private static function saveLidMapping(PDO $db, string $businessId, string $phoneE164, ?string $sessionId, string $provider, string $lidId): void
    {
        try {
            // Salva em whatsapp_business_ids (mapeamento persistente)
            if (self::tableExists($db, 'whatsapp_business_ids')) {
                $insertStmt = $db->prepare("
                    INSERT IGNORE INTO whatsapp_business_ids (business_id, phone_number)
                    VALUES (?, ?)
                ");
                $insertStmt->execute([$businessId, $phoneE164]);
            }
            
            // Salva no cache wa_pnlid_cache (se tiver sessionId)
            if (!empty($sessionId) && self::tableExists($db, 'wa_pnlid_cache')) {
                $cacheStmt = $db->prepare("
                    INSERT INTO wa_pnlid_cache (provider, session_id, pnlid, phone_e164)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE phone_e164=VALUES(phone_e164), updated_at=NOW()
                ");
                $cacheStmt->execute([$provider, $sessionId, $lidId, $phoneE164]);
            }
        } catch (\Exception $e) {
            // Ignora erro de inserção, mas loga para debug
            error_log("[ContactHelper::saveLidMapping] Erro ao salvar mapeamento: " . $e->getMessage());
        }
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
                if (empty($contactId)) {
                    continue;
                }
                
                // Detecta se é @lid com sufixo ou pnlid digits-only
                $lidInfo = null;
                if (strpos($contactId, '@lid') !== false) {
                    // Tem sufixo @lid
                    $lidId = str_replace('@lid', '', $contactId);
                    $lidInfo = [
                        'lidId' => $lidId,
                        'businessId' => $lidId . '@lid'
                    ];
                } else {
                    // Tenta detectar como pnlid digits-only
                    $lidInfo = self::detectLidWithoutSuffix($contactId);
                }
                
                if (!$lidInfo) {
                    continue;
                }
                
                $lidId = $lidInfo['lidId'];
                $businessId = $lidInfo['businessId'];
                
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
            
            // 2. Busca em wa_pnlid_cache (com sessionIds se disponível, depois sem sessionId como fallback)
            if (self::tableExists($db, 'wa_pnlid_cache') && !empty($lidIds)) {
                try {
                    // Pega apenas os lidIds que ainda não foram resolvidos
                    $unresolvedLidIds = array_diff_key($lidIds, $resultMap);
                    if (!empty($unresolvedLidIds)) {
                        // Primeiro tenta com sessionIds (se disponível)
                        if (!empty($sessionIds)) {
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
                            
                            // Atualiza lista de não resolvidos após consulta com sessionId
                            $unresolvedLidIds = array_diff_key($unresolvedLidIds, $resultMap);
                        }
                        
                        // Fallback: tenta sem sessionId (apenas provider + pnlid) para os que ainda não foram resolvidos
                        if (!empty($unresolvedLidIds)) {
                            $lidPlaceholders = str_repeat('?,', count($unresolvedLidIds) - 1) . '?';
                            $ttlDays = 30;
                            $stmt = $db->prepare("
                                SELECT pnlid, phone_e164, updated_at
                                FROM wa_pnlid_cache 
                                WHERE provider = ? 
                                AND pnlid IN ({$lidPlaceholders})
                                AND updated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                                ORDER BY updated_at DESC
                            ");
                            $params = array_merge(
                                [$provider],
                                array_keys($unresolvedLidIds),
                                [$ttlDays]
                            );
                            $stmt->execute($params);
                            $cached = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Agrupa por pnlid e pega o mais recente (já ordenado)
                            $cachedByPnlid = [];
                            foreach ($cached as $cache) {
                                if (!empty($cache['phone_e164']) && !isset($cachedByPnlid[$cache['pnlid']])) {
                                    $cachedByPnlid[$cache['pnlid']] = $cache['phone_e164'];
                                }
                            }
                            
                            foreach ($cachedByPnlid as $pnlid => $phone) {
                                if (!isset($resultMap[$pnlid])) {
                                    $resultMap[$pnlid] = $phone;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignora erro
                }
            }
            
            // 3. Para os não resolvidos, tenta buscar nos eventos (em lote otimizado)
            $unresolvedItems = [];
            foreach ($lidData as $item) {
                $contactId = (string) ($item['contactId'] ?? '');
                if (empty($contactId)) {
                    continue;
                }
                
                // Detecta se é @lid com sufixo ou pnlid digits-only
                $lidInfo = null;
                if (strpos($contactId, '@lid') !== false) {
                    $lidId = str_replace('@lid', '', $contactId);
                    $lidInfo = [
                        'lidId' => $lidId,
                        'businessId' => $lidId . '@lid'
                    ];
                } else {
                    $lidInfo = self::detectLidWithoutSuffix($contactId);
                }
                
                if (!$lidInfo) {
                    continue;
                }
                
                $lidId = $lidInfo['lidId'];
                // Só adiciona se ainda não foi resolvido
                if (!isset($resultMap[$lidId])) {
                    $unresolvedItems[] = [
                        'contactId' => strpos($contactId, '@lid') !== false ? $contactId : $lidInfo['businessId'],
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
            
            // 4. ÚLTIMA CAMADA: Tenta resolver via API do provider (limitado para performance)
            // Apenas para os que ainda não foram resolvidos e limitado a 10 itens para não sobrecarregar
            $unresolvedForProvider = [];
            foreach ($lidData as $item) {
                $contactId = (string) ($item['contactId'] ?? '');
                if (empty($contactId) || strpos($contactId, '@lid') === false) {
                    continue;
                }
                
                $lidId = str_replace('@lid', '', $contactId);
                // Só adiciona se ainda não foi resolvido
                if (!isset($resultMap[$lidId]) && count($unresolvedForProvider) < 10) {
                    $unresolvedForProvider[] = [
                        'lidId' => $lidId,
                        'businessId' => $lidId . '@lid',
                        'sessionId' => $item['sessionId'] ?? null
                    ];
                }
            }
            
            if (!empty($unresolvedForProvider)) {
                try {
                    foreach ($unresolvedForProvider as $item) {
                        $phoneFromProvider = self::resolvePnLidViaProvider($provider, $item['sessionId'], $item['lidId']);
                        if (!empty($phoneFromProvider)) {
                            $resultMap[$item['lidId']] = $phoneFromProvider;
                            // Garante persistência do mapeamento encontrado
                            self::saveLidMapping($db, $item['businessId'], $phoneFromProvider, $item['sessionId'], $provider, $item['lidId']);
                        }
                    }
                } catch (\Exception $e) {
                    // Log discreto apenas quando falhar (para troubleshooting)
                    error_log("[ContactHelper::resolveLidPhonesBatch] Erro ao resolver via provider: " . $e->getMessage());
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

