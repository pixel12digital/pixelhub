<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\WhatsAppBillingService;
use PDO;

/**
 * Controller para testes do WhatsApp Gateway
 */
class WhatsAppGatewayTestController extends Controller
{
    /**
     * Obtém o secret descriptografado do gateway
     * 
     * @return array ['secret' => string, 'baseUrl' => string]
     * @throws \RuntimeException Se o secret não estiver configurado ou não puder ser descriptografado
     */
    private function getGatewayConfig(): array
    {
        $secretRaw = Env::get('WPP_GATEWAY_SECRET', '');
        $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
        $baseUrl = rtrim($baseUrl, '/');
        
        if (empty($secretRaw)) {
            throw new \RuntimeException('WPP_GATEWAY_SECRET não configurado');
        }
        
        // Descriptografa o secret se estiver criptografado (lógica similar ao AsaasConfig)
        $secretDecrypted = '';
        $isLikelyEncrypted = false;
        
        // Detecta se parece ser uma chave criptografada
        if (strlen($secretRaw) > 100) {
            // Testa se é base64 válido
            $decoded = @base64_decode($secretRaw, true);
            if ($decoded !== false && strlen($decoded) > 16) {
                $isLikelyEncrypted = true;
            }
        }
        
        if ($isLikelyEncrypted) {
            try {
                // Tenta descriptografar
                $decrypted = \PixelHub\Core\CryptoHelper::decrypt($secretRaw);
                if (!empty($decrypted)) {
                    $secretDecrypted = $decrypted;
                } else {
                    // Descriptografou mas retornou vazio - INFRA_SECRET_KEY pode estar errada
                    throw new \RuntimeException(
                        'Secret parece criptografado mas descriptografia retornou vazio. ' .
                        'Possível causa: INFRA_SECRET_KEY incorreta. ' .
                        'SOLUÇÃO: Acesse as configurações do WhatsApp Gateway e cole o secret novamente.'
                    );
                }
            } catch (\RuntimeException $e) {
                // Re-lança RuntimeExceptions (erros de descriptografia críticos)
                throw $e;
            } catch (\Exception $e) {
                // Outros erros: tenta usar como texto plano
                error_log("[WhatsAppGatewayTest::getGatewayConfig] AVISO: Falha ao descriptografar secret, tentando como texto plano: " . $e->getMessage());
                $secretDecrypted = $secretRaw;
            }
        } else {
            // Não parece criptografada, usa como texto plano
            $secretDecrypted = $secretRaw;
        }
        
        if (empty($secretDecrypted)) {
            throw new \RuntimeException('WPP_GATEWAY_SECRET está vazio após descriptografia');
        }
        
        return [
            'secret' => $secretDecrypted,
            'baseUrl' => $baseUrl
        ];
    }

    /**
     * Página de testes
     * 
     * GET /settings/whatsapp-gateway/test
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Busca canais configurados no banco
        $channelsStmt = $db->query("
            SELECT tmc.*, t.name as tenant_name, t.email as tenant_email
            FROM tenant_message_channels tmc
            LEFT JOIN tenants t ON tmc.tenant_id = t.id
            WHERE tmc.provider = 'wpp_gateway'
            ORDER BY tmc.created_at DESC
            LIMIT 20
        ");
        $dbChannels = $channelsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Busca canais diretamente do gateway (usando a mesma lógica do teste de conexão que funciona)
        $gatewayChannels = [];
        try {
            $gateway = new WhatsAppGatewayClient();
            $result = $gateway->listChannels();
            
            // Usa exatamente a mesma lógica do teste de conexão que funciona
            if ($result['success']) {
                $channelsFromApi = $result['raw']['channels'] ?? [];
                $channelsCount = is_array($channelsFromApi) ? count($channelsFromApi) : 0;
                
                // Log para debug
                if (function_exists('pixelhub_log')) {
                    pixelhub_log("[WhatsAppGatewayTest] Canais recebidos da API: " . $channelsCount);
                }
                
                if ($channelsCount > 0) {
                    foreach ($channelsFromApi as $index => $gatewayChannel) {
                        // A) Fallback para id (NUNCA pode ser null)
                        $channelId = $gatewayChannel['id'] 
                            ?? $gatewayChannel['channel_id'] 
                            ?? $gatewayChannel['session'] 
                            ?? $gatewayChannel['name'] 
                            ?? "channel_{$index}";
                        
                        // B) Fallback para name (NUNCA pode ser null)
                        $channelName = $gatewayChannel['name'] 
                            ?? $gatewayChannel['session'] 
                            ?? $gatewayChannel['id'] 
                            ?? $gatewayChannel['channel_id'] 
                            ?? "Canal " . ($index + 1);
                        
                        // C) Garante que sempre temos id e name (não descarta nenhum item)
                        // Verifica se já está no banco
                        $foundInDb = false;
                        foreach ($dbChannels as $dbChannel) {
                            if ($dbChannel['channel_id'] === $channelId) {
                                $foundInDb = true;
                                break;
                            }
                        }
                        
                        // Se não está no banco, adiciona da API com campos normalizados
                        if (!$foundInDb) {
                            $gatewayChannels[] = [
                                'channel_id' => $channelId, // SEMPRE presente
                                'name' => $channelName,     // SEMPRE presente
                                'tenant_id' => null,
                                'tenant_name' => null,
                                'status' => $gatewayChannel['status'] ?? $gatewayChannel['connected'] ?? 'unknown',
                                'from_gateway' => true
                            ];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Se falhar ao buscar do gateway, continua com os do banco
            error_log("[WhatsAppGatewayTest] Erro ao buscar canais do gateway: " . $e->getMessage());
        }

        // Normaliza canais do banco também (garante campos id e name sempre presentes)
        foreach ($dbChannels as &$dbChannel) {
            if (empty($dbChannel['name'])) {
                $dbChannel['name'] = $dbChannel['channel_id'] ?? 'Canal sem nome';
            }
        }
        unset($dbChannel);

        // Combina canais do banco e do gateway
        $channels = array_merge($dbChannels, $gatewayChannels);
        
        // Log final para debug
        if (function_exists('pixelhub_log')) {
            pixelhub_log("[WhatsAppGatewayTest] Canais finais exibidos: " . count($channels) . " (DB: " . count($dbChannels) . ", Gateway: " . count($gatewayChannels) . ")");
        }

        // Busca últimos eventos de comunicação relacionados ao WhatsApp
        $eventsStmt = $db->query("
            SELECT ce.*, t.name as tenant_name
            FROM communication_events ce
            LEFT JOIN tenants t ON ce.tenant_id = t.id
            WHERE ce.event_type LIKE 'whatsapp.%'
            ORDER BY ce.created_at DESC
            LIMIT 50
        ");
        $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Busca últimos logs genéricos
        $logsStmt = $db->query("
            SELECT wgl.*, t.name as tenant_name
            FROM whatsapp_generic_logs wgl
            LEFT JOIN tenants t ON wgl.tenant_id = t.id
            ORDER BY wgl.sent_at DESC
            LIMIT 30
        ");
        $logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('settings.whatsapp_gateway_test', [
            'channels' => $channels,
            'events' => $events,
            'logs' => $logs,
        ]);
    }

    /**
     * Testa envio de mensagem
     * 
     * POST /settings/whatsapp-gateway/test/send
     */
    public function sendTest(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $channelId = trim($_POST['channel_id'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

        if (empty($channelId) || empty($phone) || empty($message)) {
            $this->json(['success' => false, 'error' => 'channel_id, phone e message são obrigatórios'], 400);
            return;
        }

        try {
            // Normaliza telefone
            $phoneNormalized = WhatsAppBillingService::normalizePhone($phone);
            if (empty($phoneNormalized)) {
                $this->json(['success' => false, 'error' => 'Telefone inválido'], 400);
                return;
            }

            // Obtém secret descriptografado usando helper (mesma lógica do listChannels)
            $gatewayConfig = $this->getGatewayConfig();
            $baseUrl = $gatewayConfig['baseUrl'];
            $secretDecrypted = $gatewayConfig['secret'];
            
            // LOG TEMPORÁRIO: configurações para envio (sem expor secret inteiro)
            $secretPreview = substr($secretDecrypted, 0, 4) . '...' . substr($secretDecrypted, -4) . ' (len=' . strlen($secretDecrypted) . ')';
            error_log("[WhatsAppGatewayTest::sendTest] Enviando mensagem - Channel: {$channelId}, Phone: {$phoneNormalized}");
            error_log("[WhatsAppGatewayTest::sendTest] Secret configurado: SIM - Preview: {$secretPreview}");

            // Instancia cliente com secret descriptografado
            $gateway = new WhatsAppGatewayClient($baseUrl, $secretDecrypted);
            
            // Envia via gateway
            $result = $gateway->sendText($channelId, $phoneNormalized, $message, [
                'test' => true,
                'sent_by' => Auth::user()['id'] ?? null,
                'sent_by_name' => Auth::user()['name'] ?? null
            ]);
            
            // LOG TEMPORÁRIO: resultado do envio
            $raw = $result['raw'] ?? null;
            error_log("[WhatsAppGatewayTest::sendTest] Resultado do gateway: " . json_encode([
                'success' => $result['success'] ?? false,
                'status' => $result['status'] ?? null,
                'error' => $result['error'] ?? null,
                'message_id' => $result['message_id'] ?? null,
                'raw' => $raw
            ], JSON_UNESCAPED_UNICODE));

            if ($result['success']) {
                // Extrai correlationId (já normalizado pelo WhatsAppGatewayClient ou do raw)
                $correlationId = $result['correlationId'] ?? null;
                if (!$correlationId && $raw) {
                    $correlationId = $raw['correlationId'] 
                        ?? $raw['correlation_id'] 
                        ?? $raw['trace_id'] 
                        ?? $raw['traceId']
                        ?? $raw['request_id']
                        ?? $raw['requestId']
                        ?? null;
                }
                
                // Extrai message_id (normalizado pelo WhatsAppGatewayClient ou do raw)
                $messageId = $result['message_id'] ?? null;
                if (!$messageId && $raw) {
                    $messageId = $raw['message_id'] 
                        ?? $raw['messageId'] 
                        ?? $raw['id'] 
                        ?? null;
                }
                
                // Registra evento (event_id será gerado aqui, não no gateway)
                $eventId = null;
                if ($tenantId) {
                    $eventId = EventIngestionService::ingest([
                        'event_type' => 'whatsapp.outbound.message',
                        'source_system' => 'pixelhub_test',
                        'payload' => [
                            'to' => $phoneNormalized,
                            'text' => $message,
                            'channel_id' => $channelId
                        ],
                        'tenant_id' => $tenantId,
                        'metadata' => [
                            'test' => true,
                            'sent_by' => Auth::user()['id'] ?? null,
                            'sent_by_name' => Auth::user()['name'] ?? null,
                            'message_id' => $messageId,
                            'correlation_id' => $correlationId
                        ]
                    ]);
                }

                // Resposta padronizada conforme especificação
                $response = [
                    'success' => true,
                    'status' => $result['status'] ?? 200,
                    'raw' => $raw,
                    'correlationId' => $correlationId,
                    'message_id' => $messageId,  // null é esperado (WPPConnect/Baileys não entrega ID síncrono)
                    'event_id' => $eventId,      // null se tenant_id não foi fornecido
                    'error' => null
                ];

                $this->json($response);
            } else {
                // Em caso de erro, também retorna estrutura padronizada
                $raw = $result['raw'] ?? null;
                $correlationId = null;
                if ($raw) {
                    $correlationId = $raw['correlationId'] 
                        ?? $raw['correlation_id'] 
                        ?? $raw['trace_id'] 
                        ?? $raw['traceId']
                        ?? null;
                }
                
                $response = [
                    'success' => false,
                    'status' => $result['status'] ?? 500,
                    'raw' => $raw,
                    'correlationId' => $correlationId,
                    'message_id' => null,
                    'event_id' => null,
                    'error' => $result['error'] ?? 'Erro ao enviar mensagem'
                ];
                
                $this->json($response, $response['status']);
            }
        } catch (\Exception $e) {
            error_log("[WhatsAppGatewayTest] Erro ao enviar mensagem de teste: " . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista canais disponíveis (endpoint AJAX)
     * 
     * GET /settings/whatsapp-gateway/test/channels
     * 
     * D) Proxy server-to-server: browser chama PixelHub, PixelHub chama gateway com X-Gateway-Secret
     */
    public function listChannels(): void
    {
        Auth::requireInternal();
        
        // Limpa qualquer output anterior que possa corromper o JSON
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        
        header('Content-Type: application/json; charset=utf-8');

        try {
            $db = DB::getConnection();
            
            // Busca canais do banco
            $channelsStmt = $db->query("
                SELECT tmc.*, t.name as tenant_name
                FROM tenant_message_channels tmc
                LEFT JOIN tenants t ON tmc.tenant_id = t.id
                WHERE tmc.provider = 'wpp_gateway'
                ORDER BY tmc.created_at DESC
            ");
            $dbChannels = $channelsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Normaliza canais do banco (garante campos id e name sempre presentes)
            foreach ($dbChannels as &$dbChannel) {
                // Garante campo 'id' (conforme especificação)
                if (!isset($dbChannel['id'])) {
                    $dbChannel['id'] = $dbChannel['channel_id'] ?? '';
                }
                // Garante campo 'name'
                if (empty($dbChannel['name'])) {
                    $dbChannel['name'] = $dbChannel['channel_id'] ?? 'Canal sem nome';
                }
            }
            unset($dbChannel);

            // D) Proxy server-to-server: PixelHub chama gateway (não expõe X-Gateway-Secret no browser)
            // Obtém secret descriptografado usando helper
            $gatewayConfig = $this->getGatewayConfig();
            $baseUrl = $gatewayConfig['baseUrl'];
            $secretDecrypted = $gatewayConfig['secret'];
            
            // LOG TEMPORÁRIO: configurações (sem expor secret inteiro)
            $secretPreview = substr($secretDecrypted, 0, 4) . '...' . substr($secretDecrypted, -4) . ' (len=' . strlen($secretDecrypted) . ')';
            error_log("[WhatsAppGatewayTest::listChannels] URL: {$baseUrl}/api/channels");
            error_log("[WhatsAppGatewayTest::listChannels] Secret configurado: SIM - Preview: {$secretPreview}");
            
            // Instancia cliente com secret descriptografado
            try {
                $gateway = new \PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient($baseUrl, $secretDecrypted);
            } catch (\Exception $e) {
                error_log("[WhatsAppGatewayTest::listChannels] ERRO ao instanciar cliente: " . $e->getMessage());
                throw new \RuntimeException('Erro ao configurar cliente do gateway: ' . $e->getMessage());
            }
            
            // Chama o gateway
            $result = $gateway->listChannels();

            // LOG TEMPORÁRIO: retorno bruto do gateway
            error_log("[WhatsAppGatewayTest::listChannels] Gateway response completo: " . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            $gatewayChannels = [];
            
            // LOG TEMPORÁRIO: resposta completa do gateway
            $gatewayStatus = $result['status'] ?? 'N/A';
            $gatewaySuccess = $result['success'] ?? false;
            $gatewayError = $result['error'] ?? null;
            $raw = $result['raw'] ?? null;
            
            error_log("[WhatsAppGatewayTest::listChannels] Gateway response - Status: {$gatewayStatus}, Success: " . ($gatewaySuccess ? 'true' : 'false'));
            if ($gatewayError) {
                error_log("[WhatsAppGatewayTest::listChannels] Gateway error: {$gatewayError}");
            }
            error_log("[WhatsAppGatewayTest::listChannels] Gateway raw (estrutura completa): " . json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // NÃO "engolir" erro do gateway: se gateway retornou erro ou != 200, retornar erro
            if (!$gatewaySuccess || ($gatewayStatus && ($gatewayStatus < 200 || $gatewayStatus >= 300))) {
                $errorMsg = $gatewayError ?? "Gateway retornou status {$gatewayStatus}";
                error_log("[WhatsAppGatewayTest::listChannels] ERRO DO GATEWAY - retornando erro para frontend. Error: {$errorMsg}");
                
                // Retorna erro, mas ainda tenta retornar canais do banco como fallback
                $response = [
                    'success' => false,
                    'error' => $errorMsg,
                    'channels' => $dbChannels, // Fallback: canais do banco
                    'total' => count($dbChannels),
                    'correlationId' => uniqid('err_', true)
                ];
                
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(502); // Bad Gateway
                echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            
            // Gateway retornou sucesso - processa canais
            if ($gatewaySuccess && isset($raw)) {
                // Os canais vêm em $result['raw']['channels'] conforme a documentação e método index()
                $channelsFromApi = $raw['channels'] ?? [];
                $channelsCount = is_array($channelsFromApi) ? count($channelsFromApi) : 0;
                
                // LOG TEMPORÁRIO: canais brutos do gateway
                error_log("[WhatsAppGatewayTest::listChannels] Canais brutos do gateway ({$channelsCount}): " . json_encode($channelsFromApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                if ($channelsCount > 0) {
                    foreach ($channelsFromApi as $index => $gatewayChannel) {
                        // Se não é um array, pula
                        if (!is_array($gatewayChannel)) {
                            error_log("[WhatsAppGatewayTest::listChannels] Canal #{$index} não é array: " . gettype($gatewayChannel));
                            continue;
                        }
                        
                        // A) Fallback para id (NUNCA pode ser null) - mesma lógica do index()
                        $channelId = $gatewayChannel['id'] 
                            ?? $gatewayChannel['channel_id'] 
                            ?? $gatewayChannel['session'] 
                            ?? $gatewayChannel['name'] 
                            ?? "channel_{$index}";
                        
                        // B) Fallback para name (NUNCA pode ser null) - mesma lógica do index()
                        $channelName = $gatewayChannel['name'] 
                            ?? $gatewayChannel['session'] 
                            ?? $gatewayChannel['id'] 
                            ?? $gatewayChannel['channel_id'] 
                            ?? "Canal " . ($index + 1);
                        
                        // C) Garante que sempre temos id e name (não descarta nenhum item)
                        // Verifica se já está no banco - mesma lógica do index()
                        $foundInDb = false;
                        foreach ($dbChannels as $dbChannel) {
                            $dbChannelId = $dbChannel['channel_id'] ?? $dbChannel['id'] ?? '';
                            if ($dbChannelId === $channelId) {
                                $foundInDb = true;
                                break;
                            }
                        }
                        
                        // Se não está no banco, adiciona da API com campos normalizados
                        if (!$foundInDb) {
                            $gatewayChannels[] = [
                                'id' => $channelId,         // Campo principal (conforme especificação)
                                'channel_id' => $channelId, // Fallback/legacy
                                'name' => $channelName,     // SEMPRE presente
                                'tenant_id' => null,
                                'tenant_name' => null,
                                'status' => $gatewayChannel['status'] ?? $gatewayChannel['connected'] ?? 'unknown',
                                'from_gateway' => true
                            ];
                        }
                    }
                } else {
                    error_log("[WhatsAppGatewayTest::listChannels] AVISO: Gateway retornou success=true mas nenhum canal encontrado em raw['channels']. Raw keys: " . json_encode(array_keys($raw), JSON_PRETTY_PRINT));
                }
            } else {
                error_log("[WhatsAppGatewayTest::listChannels] AVISO: Gateway retornou success=true mas 'raw' não está definido ou vazio");
            }

            // Combina canais: prioriza gateway quando banco estiver vazio
            // Se banco estiver vazio mas gateway tiver canais, usa os do gateway
            if (empty($dbChannels) && !empty($gatewayChannels)) {
                $allChannels = $gatewayChannels;
            } elseif (!empty($dbChannels) && !empty($gatewayChannels)) {
                // Combina ambos, evitando duplicatas
                $allChannels = array_merge($dbChannels, $gatewayChannels);
            } elseif (!empty($dbChannels)) {
                // Apenas do banco
                $allChannels = $dbChannels;
            } else {
                // Nenhum canal encontrado (nem banco nem gateway)
                $allChannels = [];
            }
            
            // Garante que $allChannels é sempre um array
            if (!is_array($allChannels)) {
                $allChannels = [];
            }
            
            // Log para debug
            $totalChannels = count($allChannels);
            error_log("[WhatsAppGatewayTest::listChannels] Total final: {$totalChannels} canais (DB: " . count($dbChannels) . ", Gateway: " . count($gatewayChannels) . ")");
            error_log("[WhatsAppGatewayTest::listChannels] Canais finais: " . json_encode($allChannels, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // SEMPRE retorna a estrutura correta, mesmo se vazio
            // Garante que 'channels' é sempre um array (nunca null ou undefined)
            $response = [
                'success' => true,
                'channels' => is_array($allChannels) ? $allChannels : [],
                'total' => $totalChannels
            ];
            
            // Usa json_encode direto para garantir formato correto com flags Unicode
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            error_log("[WhatsAppGatewayTest::listChannels] Exception: " . $e->getMessage());
            error_log("[WhatsAppGatewayTest::listChannels] Stack trace: " . $e->getTraceAsString());
            
            // Retorna erro mas ainda tenta retornar canais do banco se houver
            $dbChannelsFallback = [];
            try {
                $db = DB::getConnection();
                $channelsStmt = $db->query("
                    SELECT tmc.*, t.name as tenant_name
                    FROM tenant_message_channels tmc
                    LEFT JOIN tenants t ON tmc.tenant_id = t.id
                    WHERE tmc.provider = 'wpp_gateway'
                    ORDER BY tmc.created_at DESC
                ");
                $dbChannelsFallback = $channelsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                
                // Normaliza
                foreach ($dbChannelsFallback as &$channel) {
                    if (!isset($channel['id'])) {
                        $channel['id'] = $channel['channel_id'] ?? '';
                    }
                    if (empty($channel['name'])) {
                        $channel['name'] = $channel['channel_id'] ?? 'Canal sem nome';
                    }
                }
                unset($channel);
            } catch (\Exception $e2) {
                // Ignora erro secundário
            }
            
            // Retorna estrutura correta mesmo em erro
            // Garante que 'channels' é sempre um array (nunca null ou undefined)
            $response = [
                'success' => false,
                'error' => $e->getMessage(),
                'channels' => is_array($dbChannelsFallback) ? $dbChannelsFallback : [],
                'total' => count($dbChannelsFallback)
            ];
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    /**
     * Busca eventos recentes
     * 
     * GET /settings/whatsapp-gateway/test/events
     */
    public function getEvents(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        $eventType = $_GET['event_type'] ?? null;

        $db = DB::getConnection();

        $where = ["ce.event_type LIKE 'whatsapp.%'"];
        $params = [];

        if ($eventType) {
            $where[] = "ce.event_type = ?";
            $params[] = $eventType;
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        $stmt = $db->prepare("
            SELECT ce.*, t.name as tenant_name
            FROM communication_events ce
            LEFT JOIN tenants t ON ce.tenant_id = t.id
            {$whereClause}
            ORDER BY ce.created_at DESC
            LIMIT ?
        ");
        $params[] = $limit;
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->json([
            'success' => true,
            'events' => $events
        ]);
    }

    /**
     * Busca logs recentes
     * 
     * GET /settings/whatsapp-gateway/test/logs
     */
    public function getLogs(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;

        $db = DB::getConnection();

        $where = [];
        $params = [];

        if ($tenantId) {
            $where[] = "wgl.tenant_id = ?";
            $params[] = $tenantId;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $db->prepare("
            SELECT wgl.*, t.name as tenant_name
            FROM whatsapp_generic_logs wgl
            LEFT JOIN tenants t ON wgl.tenant_id = t.id
            {$whereClause}
            ORDER BY wgl.sent_at DESC
            LIMIT ?
        ");
        $params[] = $limit;
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->json([
            'success' => true,
            'logs' => $logs
        ]);
    }

    /**
     * Simula recebimento de webhook (para testes)
     * 
     * POST /settings/whatsapp-gateway/test/webhook
     * 
     * IMPORTANTE: Este endpoint NÃO valida assinatura real do gateway.
     * Apenas valida payload mínimo e insere evento fake na tabela de eventos.
     * Usado apenas para testes internos do sistema.
     */
    public function simulateWebhook(): void
    {
        // Limpa qualquer output anterior que possa corromper o JSON
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        try {
            Auth::requireInternal();
        } catch (\Exception $e) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Não autorizado',
                'code' => 'UNAUTHORIZED'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Sempre retorna JSON, mesmo em erro
        header('Content-Type: application/json; charset=utf-8');

        try {
            // Valida payload mínimo (não valida assinatura real do gateway)
            $eventType = $_POST['event_type'] ?? 'message';
            $channelId = trim($_POST['channel_id'] ?? '');
            $from = trim($_POST['from'] ?? '');
            $text = trim($_POST['text'] ?? '');
            $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

            if (empty($channelId) || empty($from)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'channel_id e from são obrigatórios',
                    'code' => 'VALIDATION_ERROR'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // Simula payload do webhook (não precisa validar assinatura real)
            $payload = [
                'event' => $eventType,
                'channel_id' => $channelId,
                'from' => $from,
                'text' => $text,
                'timestamp' => time()
            ];

            if ($eventType === 'message') {
                $payload['message'] = [
                    'id' => 'test_' . uniqid(),
                    'from' => $from,
                    'text' => $text,
                    'timestamp' => time()
                ];
            }

            // Ingere evento fake na tabela de eventos
            $eventId = EventIngestionService::ingest([
                'event_type' => 'whatsapp.inbound.message',
                'source_system' => 'pixelhub_test',
                'payload' => $payload,
                'tenant_id' => $tenantId,
                'metadata' => [
                    'test' => true,
                    'simulated' => true
                ]
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'event_id' => $eventId,
                'message' => 'Webhook simulado com sucesso. Evento ingerido no sistema.',
                'code' => 'SUCCESS'
            ], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (\PDOException $e) {
            error_log("[WhatsAppGatewayTest::simulateWebhook] PDOException: " . $e->getMessage());
            error_log("[WhatsAppGatewayTest::simulateWebhook] SQL State: " . $e->getCode());
            error_log("[WhatsAppGatewayTest::simulateWebhook] Stack trace: " . $e->getTraceAsString());
            
            // Verifica se é erro de tabela não encontrada
            if ($e->getCode() == '42S02') {
                $errorMessage = 'Tabela communication_events não encontrada. Execute a migration primeiro.';
            } else {
                $errorMessage = 'Erro no banco de dados: ' . $e->getMessage();
            }
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $errorMessage
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\RuntimeException $e) {
            error_log("[WhatsAppGatewayTest::simulateWebhook] RuntimeException: " . $e->getMessage());
            error_log("[WhatsAppGatewayTest::simulateWebhook] Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Exception $e) {
            error_log("[WhatsAppGatewayTest::simulateWebhook] Exception: " . $e->getMessage());
            error_log("[WhatsAppGatewayTest::simulateWebhook] Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        } catch (\Throwable $e) {
            error_log("[WhatsAppGatewayTest::simulateWebhook] Throwable: " . $e->getMessage());
            error_log("[WhatsAppGatewayTest::simulateWebhook] Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro interno do servidor',
                'code' => 'INTERNAL_ERROR',
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

