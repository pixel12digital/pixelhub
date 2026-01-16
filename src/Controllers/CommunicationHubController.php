<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\EventRouterService;
use PixelHub\Services\EventNormalizationService;
use PixelHub\Services\WhatsAppBillingService;
use PixelHub\Services\GatewaySecret;
use PDO;

/**
 * Controller para o Painel Operacional de Comunicação
 * 
 * Interface onde operadores enviam mensagens, respondem conversas e gerenciam canais
 */
class CommunicationHubController extends Controller
{
    /**
     * Painel principal de comunicação
     * 
     * GET /communication-hub
     */
    public function index(): void
    {
        try {
            Auth::requireInternal();

            $db = DB::getConnection();

        // Filtros
        $channel = $_GET['channel'] ?? 'all'; // all, whatsapp, chat, email
        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : null;
        $status = $_GET['status'] ?? 'active'; // active, all, closed

        // Busca threads de conversa ativos
        $where = [];
        $params = [];

        // Threads de WhatsApp (via eventos recentes)
        try {
            $whatsappThreads = $this->getWhatsAppThreads($db, $tenantId, $status);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar threads WhatsApp: " . $e->getMessage());
            $whatsappThreads = [];
        }
        
        // Threads de chat interno
        try {
            $chatThreads = $this->getChatThreads($db, $tenantId, $status);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar threads chat: " . $e->getMessage());
            $chatThreads = [];
        }

        // Combina e ordena por última atividade
        $allThreads = array_merge($whatsappThreads ?? [], $chatThreads ?? []);
        
        // Separa incoming leads das conversas normais
        $incomingLeads = [];
        $normalThreads = [];
        
        if (!empty($allThreads)) {
            usort($allThreads, function($a, $b) {
                $timeA = strtotime($a['last_activity'] ?? '1970-01-01');
                $timeB = strtotime($b['last_activity'] ?? '1970-01-01');
                return $timeB <=> $timeA; // Mais recente primeiro
            });

            // Filtra por canal se necessário
            if ($channel !== 'all') {
                $allThreads = array_filter($allThreads, function($thread) use ($channel) {
                    return ($thread['channel'] ?? '') === $channel;
                });
                $allThreads = array_values($allThreads); // Reindexa array
            }
            
            // Separa incoming leads
            foreach ($allThreads as $thread) {
                if (!empty($thread['is_incoming_lead'])) {
                    $incomingLeads[] = $thread;
                } else {
                    $normalThreads[] = $thread;
                }
            }
        }

        // Busca tenants para filtro
        try {
            $tenantsStmt = $db->query("
                SELECT id, name, email, phone 
                FROM tenants 
                WHERE (is_archived IS NULL OR is_archived = 0)
                ORDER BY name
            ");
            $tenants = $tenantsStmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar tenants: " . $e->getMessage());
            $tenants = [];
        }

        // Estatísticas
        $stats = [
            'whatsapp_active' => count(array_filter($normalThreads, function($t) {
                return ($t['channel'] ?? '') === 'whatsapp' && ($t['status'] ?? '') === 'active';
            })),
            'chat_active' => count(array_filter($normalThreads, function($t) {
                return ($t['channel'] ?? '') === 'chat' && ($t['status'] ?? '') === 'active';
            })),
            'total_unread' => count(array_filter($normalThreads, function($t) {
                return ($t['unread_count'] ?? 0) > 0;
            })),
            'incoming_leads_count' => count($incomingLeads)
        ];

        // Garante que threads é sempre um array válido
        $threadsList = is_array($normalThreads) ? $normalThreads : [];
        $incomingLeadsList = is_array($incomingLeads) ? $incomingLeads : [];
        
        $this->view('communication_hub.index', [
            'threads' => $threadsList,
            'incoming_leads' => $incomingLeadsList,
            'tenants' => is_array($tenants) ? $tenants : [],
            'stats' => is_array($stats) ? $stats : ['whatsapp_active' => 0, 'chat_active' => 0, 'total_unread' => 0, 'incoming_leads_count' => 0],
            'filters' => [
                'channel' => $channel ?? 'all',
                'tenant_id' => $tenantId,
                'status' => $status ?? 'active'
            ]
        ]);
        
        } catch (\Throwable $e) {
            // Log detalhado do erro
            $errorMsg = "[CommunicationHub] Erro fatal no index: " . $e->getMessage() . "\n";
            $errorMsg .= "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $errorMsg .= "Stack trace: " . $e->getTraceAsString() . "\n";
            
            if (function_exists('pixelhub_log')) {
                pixelhub_log($errorMsg);
            } else {
                error_log($errorMsg);
            }
            
            http_response_code(500);
            
            // Sempre mostra o erro em desenvolvimento ou se APP_DEBUG estiver ativo
            $displayErrors = ini_get('display_errors');
            $isDebug = defined('APP_DEBUG') && APP_DEBUG;
            
            if ($displayErrors == '1' || $displayErrors == 'On' || $isDebug) {
                echo "<h1>Erro interno do servidor</h1>";
                echo "<h2>Mensagem:</h2>";
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
                echo "<h2>Arquivo:</h2>";
                echo "<pre>" . htmlspecialchars($e->getFile() . ":" . $e->getLine()) . "</pre>";
                echo "<h2>Stack Trace:</h2>";
                echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            } else {
                echo "<h1>Erro interno do servidor</h1>";
                echo "<p>Ocorreu um erro ao carregar o painel de comunicação.</p>";
                echo "<p>Verifique os logs para mais detalhes.</p>";
            }
        }
    }

    /**
     * Visualiza uma conversa específica
     * 
     * GET /communication-hub/thread?thread_id=xxx&channel=whatsapp
     */
    public function thread(): void
    {
        Auth::requireInternal();

        $threadId = $_GET['thread_id'] ?? null;
        $channel = $_GET['channel'] ?? 'whatsapp';

        if (empty($threadId)) {
            $this->redirect('/communication-hub?error=missing_thread_id');
            return;
        }

        $db = DB::getConnection();

        if ($channel === 'whatsapp') {
            // Busca mensagens WhatsApp via eventos
            $messages = $this->getWhatsAppMessages($db, $threadId);
            $thread = $this->getWhatsAppThreadInfo($db, $threadId);
            
            // Marca conversa como lida ao abrir (mark as read)
            if ($thread && isset($thread['conversation_id'])) {
                $this->markConversationAsRead($db, (int) $thread['conversation_id']);
            }
        } else {
            // Busca mensagens de chat interno
            $messages = $this->getChatMessages($db, $threadId);
            $thread = $this->getChatThreadInfo($db, $threadId);
        }

        $this->view('communication_hub.thread', [
            'thread' => $thread,
            'messages' => $messages,
            'channel' => $channel
        ]);
    }

    /**
     * Retorna dados da conversa em JSON (para carregamento via AJAX)
     * 
     * GET /communication-hub/thread-data?thread_id=xxx&channel=whatsapp
     */
    public function getThreadData(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $threadId = $_GET['thread_id'] ?? null;
        $channel = $_GET['channel'] ?? 'whatsapp';

        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            if ($channel === 'whatsapp') {
                // Busca mensagens WhatsApp via eventos
                $messages = $this->getWhatsAppMessages($db, $threadId);
                $thread = $this->getWhatsAppThreadInfo($db, $threadId);
                
                // Marca conversa como lida ao abrir (mark as read)
                if ($thread && isset($thread['conversation_id'])) {
                    $this->markConversationAsRead($db, (int) $thread['conversation_id']);
                }
            } else {
                // Busca mensagens de chat interno
                $messages = $this->getChatMessages($db, $threadId);
                $thread = $this->getChatThreadInfo($db, $threadId);
            }

            if (!$thread) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            $this->json([
                'success' => true,
                'thread' => $thread,
                'messages' => $messages,
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar dados da conversa: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao carregar conversa'], 500);
        }
    }

    /**
     * Marca conversa como lida
     */
    private function markConversationAsRead(PDO $db, int $conversationId): void
    {
        try {
            $stmt = $db->prepare("
                UPDATE conversations 
                SET unread_count = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao marcar conversa como lida: " . $e->getMessage());
            // Não quebra fluxo se falhar
        }
    }

    /**
     * Envia mensagem
     * 
     * POST /communication-hub/send
     * CORRIGIDO: tenant_id agora é opcional (pode ser inferido da conversa)
     */
    public function send(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $channel = $_POST['channel'] ?? null;
        $threadId = $_POST['thread_id'] ?? null;
        $to = $_POST['to'] ?? null; // phone, email, etc
        $message = trim($_POST['message'] ?? '');
        $tenantId = isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null;
        // CORRIGIDO: channel_id deve permanecer string (VARCHAR(100) no banco, string no gateway)
        $channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? trim($_POST['channel_id']) : null;
        // NOVO: Suporte para encaminhamento para múltiplos canais
        $forwardToAll = isset($_POST['forward_to_all']) && $_POST['forward_to_all'] === '1';
        $channelIdsArray = isset($_POST['channel_ids']) && is_array($_POST['channel_ids']) ? $_POST['channel_ids'] : null;

        // LOG TEMPORÁRIO: Validação do channel_id recebido
        error_log("[CommunicationHub::send] Recebido: channel={$channel}, threadId={$threadId}, tenantId={$tenantId}, channelId=" . var_export($channelId, true) . " (tipo: " . gettype($channelId) . "), to={$to}");

        try {
            if (empty($channel) || empty($message)) {
                $this->json(['success' => false, 'error' => 'Canal e mensagem são obrigatórios'], 400);
                return;
            }
            if ($channel === 'whatsapp') {
                if (empty($to)) {
                    $this->json(['success' => false, 'error' => 'to (telefone) é obrigatório para WhatsApp'], 400);
                    return;
                }
                
                $db = DB::getConnection();
                
                // NOVO: Determina lista de canais para envio
                $targetChannels = [];
                
                // Se forward_to_all está ativo, busca todos os canais habilitados
                if ($forwardToAll) {
                    error_log("[CommunicationHub::send] Modo encaminhamento para todos os canais ativado");
                    $channelStmt = $db->query("
                        SELECT DISTINCT channel_id 
                        FROM tenant_message_channels 
                        WHERE provider = 'wpp_gateway' 
                        AND is_enabled = 1
                        AND (
                            channel_id IN ('ImobSites', 'Pixel12 Digital', 'pixel12digital')
                            OR LOWER(channel_id) LIKE '%imobsites%'
                            OR LOWER(channel_id) LIKE '%pixel12%'
                        )
                        ORDER BY channel_id
                    ");
                    $allChannels = $channelStmt->fetchAll(PDO::FETCH_COLUMN);
                    $targetChannels = array_filter(array_map('trim', $allChannels));
                    error_log("[CommunicationHub::send] Canais encontrados para encaminhamento: " . implode(', ', $targetChannels));
                } 
                // Se channel_ids array foi fornecido, usa esses canais
                elseif ($channelIdsArray && !empty($channelIdsArray)) {
                    error_log("[CommunicationHub::send] Lista de canais fornecida: " . implode(', ', $channelIdsArray));
                    // Valida que os canais existem e estão habilitados (case-insensitive)
                    $placeholders = str_repeat('?,', count($channelIdsArray) - 1) . '?';
                    $channelStmt = $db->prepare("
                        SELECT channel_id 
                        FROM tenant_message_channels 
                        WHERE provider = 'wpp_gateway' 
                        AND is_enabled = 1
                        AND (
                            channel_id IN ($placeholders)
                            OR LOWER(channel_id) IN (" . implode(',', array_fill(0, count($channelIdsArray), 'LOWER(?)')) . ")
                        )
                    ");
                    // Executa com ambos os arrays (original e lowercase) para busca case-insensitive
                    $executeParams = array_merge($channelIdsArray, array_map('strtolower', $channelIdsArray));
                    $channelStmt->execute($executeParams);
                    $validChannels = $channelStmt->fetchAll(PDO::FETCH_COLUMN);
                    $targetChannels = array_filter(array_map('trim', $validChannels));
                    error_log("[CommunicationHub::send] Canais válidos encontrados: " . implode(', ', $targetChannels));
                    
                    // Se nenhum canal válido encontrado, tenta busca mais flexível
                    if (empty($targetChannels)) {
                        error_log("[CommunicationHub::send] Nenhum canal encontrado com nomes exatos, tentando busca flexível...");
                        foreach ($channelIdsArray as $requestedChannel) {
                            $lowerRequested = strtolower(trim($requestedChannel));
                            $flexibleStmt = $db->prepare("
                                SELECT channel_id 
                                FROM tenant_message_channels 
                                WHERE provider = 'wpp_gateway' 
                                AND is_enabled = 1
                                AND (
                                    LOWER(channel_id) LIKE ?
                                    OR LOWER(channel_id) LIKE ?
                                )
                                LIMIT 1
                            ");
                            $flexibleStmt->execute(["%{$lowerRequested}%", "{$lowerRequested}%"]);
                            $found = $flexibleStmt->fetch(PDO::FETCH_COLUMN);
                            if ($found) {
                                $targetChannels[] = trim($found);
                                error_log("[CommunicationHub::send] Canal encontrado via busca flexível: '{$found}' para '{$requestedChannel}'");
                            }
                        }
                    }
                }
                
                // Se ainda não tem canais definidos, usa lógica antiga (um único canal)
                if (empty($targetChannels)) {
                    // PRIORIDADE 1: Usa channel_id fornecido diretamente (vem da thread ou especificado explicitamente)
                    if ($channelId) {
                        error_log("[CommunicationHub::send] Channel_id fornecido explicitamente: {$channelId}");
                        // Valida que o canal existe e está habilitado (busca case-insensitive e por similaridade)
                        $channelStmt = $db->prepare("
                            SELECT channel_id 
                            FROM tenant_message_channels 
                            WHERE provider = 'wpp_gateway' 
                            AND is_enabled = 1
                            AND (
                                channel_id = ?
                                OR LOWER(channel_id) = LOWER(?)
                                OR LOWER(channel_id) LIKE ?
                            )
                            LIMIT 1
                        ");
                        $channelIdLower = strtolower(trim($channelId));
                        $channelStmt->execute([$channelId, $channelId, "%{$channelIdLower}%"]);
                        $channelData = $channelStmt->fetch();
                        
                        if (!$channelData) {
                            error_log("[CommunicationHub::send] ERRO: Canal '{$channelId}' não encontrado ou não habilitado");
                            $this->json(['success' => false, 'error' => "Canal WhatsApp '{$channelId}' não está disponível ou habilitado. Verifique se o nome do canal está correto."], 400);
                            return;
                        }
                        
                        // Usa o channel_id encontrado no banco (pode ter capitalização diferente)
                        $foundChannelId = trim($channelData['channel_id']);
                        $targetChannels = [$foundChannelId];
                        error_log("[CommunicationHub::send] Canal validado e encontrado: '{$foundChannelId}' (solicitado: '{$channelId}')");
                    } else {
                    // PRIORIDADE 2: Se não tem channel_id, tenta buscar diretamente da conversa/thread
                    // ATENÇÃO: Esta lógica pode pegar o canal errado se a conversa tem histórico de múltiplos canais
                    if (!empty($threadId) && preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                        $conversationId = (int) $matches[1];
                        
                        error_log("[CommunicationHub::send] Tentando inferir channel_id da conversa ID: {$conversationId}");
                        
                        // Busca informações da conversa incluindo tenant_id e channel_id DA PRÓPRIA CONVERSA
                        $convStmt = $db->prepare("SELECT tenant_id, conversation_key, contact_external_id, channel_id FROM conversations WHERE id = ?");
                        $convStmt->execute([$conversationId]);
                        $conv = $convStmt->fetch();
                        
                        if ($conv) {
                            if ($conv['tenant_id']) {
                                $tenantId = (int) $conv['tenant_id'];
                            }
                            
                            // PRIORIDADE 2.1: Usa o channel_id da própria conversa (mais confiável)
                            if (!empty($conv['channel_id'])) {
                                $foundChannelId = trim($conv['channel_id']);
                                error_log("[CommunicationHub::send] Channel_id encontrado na conversa: {$foundChannelId}");
                                
                                // Valida que o canal ainda existe e está habilitado
                                $channelStmt = $db->prepare("
                                    SELECT channel_id 
                                    FROM tenant_message_channels 
                                    WHERE channel_id = ? 
                                    AND provider = 'wpp_gateway' 
                                    AND is_enabled = 1
                                    LIMIT 1
                                ");
                                $channelStmt->execute([$foundChannelId]);
                                $channelData = $channelStmt->fetch();
                                
                                if ($channelData) {
                                    $targetChannels = [$foundChannelId];
                                    error_log("[CommunicationHub::send] Canal da conversa validado: {$foundChannelId}");
                                } else {
                                    error_log("[CommunicationHub::send] AVISO: Canal da conversa '{$foundChannelId}' não está mais habilitado, tentando buscar de eventos...");
                                }
                            }
                            
                            // PRIORIDADE 2.2: Se não encontrou na conversa, tenta buscar dos eventos (menos confiável)
                            if (empty($targetChannels)) {
                                $contactId = $conv['contact_external_id'];
                                error_log("[CommunicationHub::send] Buscando channel_id nos eventos para contato: {$contactId}");
                                
                                // Busca o canal do evento mais recente desta conversa
                                // IMPORTANTE: Filtra por tenant_id se disponível para evitar pegar canal errado
                                $eventStmtSql = "
                                    SELECT ce.payload, ce.tenant_id
                                    FROM communication_events ce
                                    WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                                    AND (
                                        JSON_EXTRACT(ce.payload, '$.from') = ?
                                        OR JSON_EXTRACT(ce.payload, '$.to') = ?
                                        OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
                                        OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
                                    )";
                                
                                $eventParams = [$contactId, $contactId, $contactId, $contactId];
                                
                                // Se temos tenant_id, filtra por ele para pegar canal correto
                                if ($tenantId) {
                                    $eventStmtSql .= " AND (ce.tenant_id = ? OR ce.tenant_id IS NULL)";
                                    $eventParams[] = $tenantId;
                                    error_log("[CommunicationHub::send] Filtrando eventos por tenant_id: {$tenantId}");
                                }
                                
                                $eventStmtSql .= " ORDER BY ce.created_at DESC LIMIT 1";
                                
                                $eventStmt = $db->prepare($eventStmtSql);
                                $eventStmt->execute($eventParams);
                                $event = $eventStmt->fetch();
                                
                                if ($event && $event['payload']) {
                                    $payload = json_decode($event['payload'], true);
                                    if (isset($payload['channel_id']) && !empty($payload['channel_id'])) {
                                        $foundChannelId = trim((string) $payload['channel_id']);
                                        error_log("[CommunicationHub::send] Channel_id encontrado nos eventos: {$foundChannelId}");
                                        
                                        // Valida que o canal ainda existe
                                        $channelStmt = $db->prepare("
                                            SELECT channel_id 
                                            FROM tenant_message_channels 
                                            WHERE channel_id = ? 
                                            AND provider = 'wpp_gateway' 
                                            AND is_enabled = 1
                                            LIMIT 1
                                        ");
                                        $channelStmt->execute([$foundChannelId]);
                                        $channelData = $channelStmt->fetch();
                                        
                                        if ($channelData) {
                                            $targetChannels = [$foundChannelId];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                    // PRIORIDADE 3: Busca channel do tenant (se ainda não encontrou)
                    if (!$channelId && $tenantId) {
                        $channelStmt = $db->prepare("
                            SELECT channel_id 
                            FROM tenant_message_channels 
                            WHERE tenant_id = ? 
                            AND provider = 'wpp_gateway' 
                            AND is_enabled = 1
                            LIMIT 1
                        ");
                        $channelStmt->execute([$tenantId]);
                        $channelData = $channelStmt->fetch();

                        if ($channelData) {
                            // CORRIGIDO: Mantém como string, não converte para int
                            $channelId = trim((string) $channelData['channel_id']);
                            error_log("[CommunicationHub::send] Channel_id encontrado do tenant: {$channelId}");
                        } else {
                            error_log("[CommunicationHub::send] Nenhum canal encontrado para tenant_id: {$tenantId}");
                        }
                    }
                    
                    // PRIORIDADE 4: Fallback: tenta usar canal compartilhado/default (qualquer canal habilitado)
                    if (empty($targetChannels)) {
                        $channelStmt = $db->prepare("
                            SELECT channel_id 
                            FROM tenant_message_channels 
                            WHERE provider = 'wpp_gateway' 
                            AND is_enabled = 1
                            LIMIT 1
                        ");
                        $channelStmt->execute();
                        $channelData = $channelStmt->fetch();

                        if ($channelData) {
                            // CORRIGIDO: Mantém como string, não converte para int
                            $foundChannelId = trim((string) $channelData['channel_id']);
                            $targetChannels = [$foundChannelId];
                            error_log("[CommunicationHub::send] Channel_id encontrado (canal compartilhado): {$foundChannelId}");
                        } else {
                            error_log("[CommunicationHub::send] Nenhum canal WhatsApp habilitado encontrado no sistema");
                            $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp configurado no sistema'], 400);
                            return;
                        }
                    }
                }
                
                // Valida que temos pelo menos um canal
                if (empty($targetChannels)) {
                    error_log("[CommunicationHub::send] ERRO: Nenhum canal identificado para envio");
                    $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp identificado para envio'], 400);
                    return;
                }
                
                error_log("[CommunicationHub::send] Canais alvo para envio: " . implode(', ', $targetChannels) . " (total: " . count($targetChannels) . ")");

                // Normaliza telefone
                $phoneNormalized = WhatsAppBillingService::normalizePhone($to);
                if (empty($phoneNormalized)) {
                    $this->json(['success' => false, 'error' => 'Telefone inválido'], 400);
                    return;
                }

                // ===== LOG TEMPORÁRIO: Passo 1 - Diagnóstico =====
                // Carrega configurações exatamente como o teste de conexão faz
                $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
                $secretRaw = Env::get('WPP_GATEWAY_SECRET', '');
                // Usa GatewaySecret::getDecrypted() como fonte única (mesma do teste de conexão)
                $secret = GatewaySecret::getDecrypted();
                
                $secretPreview = !empty($secret) 
                    ? (substr($secret, 0, 4) . '...' . substr($secret, -4) . ' (len=' . strlen($secret) . ')')
                    : 'VAZIO';
                $hasSecret = !empty($secret);
                
                error_log("[CommunicationHub::send] ===== LOG DIAGNÓSTICO ENVIO =====");
                error_log("[CommunicationHub::send] gateway_base_url: {$baseUrl}");
                error_log("[CommunicationHub::send] canal selecionado: id={$channelId}, nome=N/A");
                error_log("[CommunicationHub::send] secret configurado: " . ($hasSecret ? 'SIM' : 'NÃO') . " - Preview: {$secretPreview}");
                error_log("[CommunicationHub::send] secret raw length: " . strlen($secretRaw));
                // ===== FIM LOG TEMPORÁRIO =====

                // Unifica origem das configurações: usa exatamente as mesmas do módulo de configurações
                // Garante que baseUrl seja uma URL válida (não um caminho relativo)
                if (!empty($baseUrl) && !preg_match('/^https?:\/\//', $baseUrl)) {
                    error_log("[CommunicationHub::send] AVISO: BaseURL inválida detectada: {$baseUrl}. Corrigindo para padrão.");
                    $baseUrl = 'https://wpp.pixel12digital.com.br';
                }
                
                // Garante valor padrão correto
                $baseUrl = !empty($baseUrl) && filter_var($baseUrl, FILTER_VALIDATE_URL) 
                    ? $baseUrl 
                    : 'https://wpp.pixel12digital.com.br';

                // Cria gateway com configurações explícitas (mesmas do teste de conexão)
                $gateway = new WhatsAppGatewayClient($baseUrl, $secret);
                
                // ===== LOG TEMPORÁRIO: Endpoint de verificação de status =====
                $statusEndpoint = "{$baseUrl}/api/channels/{$channelId}";
                error_log("[CommunicationHub::send] endpoint verificar status: {$statusEndpoint}");
                // ===== FIM LOG TEMPORÁRIO =====

                // NOVO: Itera sobre todos os canais e envia para cada um
                $sendResults = [];
                $hasAnySuccess = false;
                $errors = [];
                
                foreach ($targetChannels as $targetChannelId) {
                    error_log("[CommunicationHub::send] Processando canal: {$targetChannelId}");
                    
                    // Valida se a sessão está conectada antes de enviar (NÃO-BLOQUEANTE)
                    $channelInfo = $gateway->getChannel($targetChannelId);
                    
                    $statusCode = $channelInfo['status'] ?? 'N/A';
                    $shouldBlockSend = false;
                    $blockReason = null;
                    
                    if (!$channelInfo['success']) {
                        $errorMsg = $channelInfo['error'] ?? 'Erro desconhecido';
                        $errorLower = strtolower($errorMsg);
                        
                        if (strpos($errorLower, 'unauthorized') !== false || 
                            strpos($errorLower, '401') !== false || 
                            $statusCode === 401) {
                            $shouldBlockSend = true;
                            $blockReason = 'Erro de autenticação';
                        } elseif (strpos($errorLower, 'not found') !== false || 
                                  strpos($errorLower, '404') !== false || 
                                  $statusCode === 404) {
                            $shouldBlockSend = true;
                            $blockReason = 'Canal não encontrado';
                        } else {
                            error_log("[CommunicationHub::send] AVISO: Check de status falhou para {$targetChannelId} ({$errorMsg}), mas tentando enviar mesmo assim");
                        }
                    } else {
                        $channelData = $channelInfo['raw'] ?? [];
                        $sessionStatus = $channelData['status'] ?? $channelData['connection'] ?? null;
                        $isConnected = ($sessionStatus === 'connected' || $sessionStatus === 'open' || $channelData['connected'] ?? false);
                        
                        if (!$isConnected) {
                            $shouldBlockSend = true;
                            $blockReason = "Sessão desconectada";
                        }
                    }
                    
                    if ($shouldBlockSend) {
                        error_log("[CommunicationHub::send] Canal {$targetChannelId} bloqueado: {$blockReason}");
                        $sendResults[] = [
                            'channel_id' => $targetChannelId,
                            'success' => false,
                            'error' => $blockReason,
                            'error_code' => $statusCode === 401 ? 'UNAUTHORIZED' : ($statusCode === 404 ? 'CHANNEL_NOT_FOUND' : 'SESSION_DISCONNECTED')
                        ];
                        $errors[] = "{$targetChannelId}: {$blockReason}";
                        continue;
                    }
                    
                    // Envia via gateway
                    error_log("[CommunicationHub::send] Enviando para canal: {$targetChannelId}");
                    $result = $gateway->sendText($targetChannelId, $phoneNormalized, $message, [
                        'sent_by' => Auth::user()['id'] ?? null,
                        'sent_by_name' => Auth::user()['name'] ?? null
                    ]);
                    
                    if ($result['success']) {
                        error_log("[CommunicationHub::send] ✅ Sucesso ao enviar para {$targetChannelId}");
                        $hasAnySuccess = true;
                        
                        // Cria evento de envio para este canal
                        $eventId = EventIngestionService::ingest([
                            'event_type' => 'whatsapp.outbound.message',
                            'source_system' => 'pixelhub_operator',
                            'payload' => [
                                'to' => $phoneNormalized,
                                'message' => [
                                    'to' => $phoneNormalized,
                                    'text' => $message,
                                    'timestamp' => time()
                                ],
                                'text' => $message,
                                'timestamp' => time(),
                                'channel_id' => $targetChannelId
                            ],
                            'tenant_id' => $tenantId,
                            'metadata' => [
                                'sent_by' => Auth::user()['id'] ?? null,
                                'sent_by_name' => Auth::user()['name'] ?? null,
                                'message_id' => $result['message_id'] ?? null,
                                'forwarded' => count($targetChannels) > 1 ? true : null
                            ]
                        ]);
                        
                        $sendResults[] = [
                            'channel_id' => $targetChannelId,
                            'success' => true,
                            'event_id' => $eventId,
                            'message_id' => $result['message_id'] ?? null
                        ];
                    } else {
                        $error = $result['error'] ?? 'Erro ao enviar mensagem';
                        error_log("[CommunicationHub::send] ❌ Erro ao enviar para {$targetChannelId}: {$error}");
                        
                        $sendResults[] = [
                            'channel_id' => $targetChannelId,
                            'success' => false,
                            'error' => $error,
                            'error_code' => $result['error_code'] ?? 'GATEWAY_ERROR'
                        ];
                        $errors[] = "{$targetChannelId}: {$error}";
                    }
                }
                
                // Retorna resultado agregado
                if (count($targetChannels) === 1) {
                    // Comportamento antigo: retorna resultado único
                    $singleResult = $sendResults[0];
                    if ($singleResult['success']) {
                        $this->json([
                            'success' => true,
                            'event_id' => $singleResult['event_id'],
                            'message_id' => $singleResult['message_id']
                        ]);
                    } else {
                        $this->json([
                            'success' => false,
                            'error' => $singleResult['error'],
                            'error_code' => $singleResult['error_code'],
                            'channel_id' => $singleResult['channel_id']
                        ], 500);
                    }
                } else {
                    // Novo comportamento: retorna resultado múltiplo
                    $successCount = count(array_filter($sendResults, function($r) { return $r['success']; }));
                    $totalCount = count($sendResults);
                    
                    error_log("[CommunicationHub::send] ===== RESULTADO FINAL ENCAMINHAMENTO =====");
                    error_log("[CommunicationHub::send] Total de canais: {$totalCount} | Sucessos: {$successCount} | Falhas: " . ($totalCount - $successCount));
                    error_log("[CommunicationHub::send] ===== FIM LOG DIAGNÓSTICO =====");
                    
                    $this->json([
                        'success' => $hasAnySuccess,
                        'forwarded' => true,
                        'total_channels' => $totalCount,
                        'success_count' => $successCount,
                        'failure_count' => $totalCount - $successCount,
                        'results' => $sendResults,
                        'message' => $hasAnySuccess 
                            ? "Mensagem enviada para {$successCount} de {$totalCount} canal(is)" 
                            : "Falha ao enviar para todos os canais"
                    ], $hasAnySuccess ? 200 : 500);
                }
            } else {
                $this->json(['success' => false, 'error' => "Canal {$channel} não implementado ainda"], 400);
            }
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao enviar mensagem: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Busca threads de WhatsApp (via tabela conversations - fonte de verdade)
     */
    private function getWhatsAppThreads(PDO $db, ?int $tenantId, string $status): array
    {
        // 1. Tenta ler da tabela conversations (fonte de verdade)
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'conversations'");
            if ($checkStmt->rowCount() > 0) {
                return $this->getWhatsAppThreadsFromConversations($db, $tenantId, $status);
            }
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao verificar tabela conversations: " . $e->getMessage());
        }

        // 2. Fallback: lê de communication_events (compatibilidade com versão antiga)
        return $this->getWhatsAppThreadsFromEvents($db, $tenantId, $status);
    }

    /**
     * Busca threads de WhatsApp da tabela conversations (fonte de verdade)
     */
    private function getWhatsAppThreadsFromConversations(PDO $db, ?int $tenantId, string $status): array
    {
        $where = ["c.channel_type = 'whatsapp'"];
        $params = [];

        if ($tenantId) {
            $where[] = "c.tenant_id = ?";
            $params[] = $tenantId;
        }

        // Filtro de status
        if ($status === 'active') {
            $where[] = "c.status NOT IN ('closed', 'archived')";
        } elseif ($status === 'closed') {
            $where[] = "c.status IN ('closed', 'archived')";
        }
        // Se status = 'all', não filtra

        $whereClause = "WHERE " . implode(" AND ", $where);

        try {
            $stmt = $db->prepare("
                SELECT 
                    c.id,
                    c.conversation_key,
                    c.channel_type,
                    c.contact_external_id,
                    c.contact_name,
                    c.tenant_id,
                    c.is_incoming_lead,
                    c.status,
                    c.assigned_to,
                    c.last_message_at,
                    c.last_message_direction,
                    c.message_count,
                    c.unread_count,
                    c.created_at,
                    COALESCE(t.name, 'Sem tenant') as tenant_name,
                    u.name as assigned_to_name
                FROM conversations c
                LEFT JOIN tenants t ON c.tenant_id = t.id
                LEFT JOIN users u ON c.assigned_to = u.id
                {$whereClause}
                ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.created_at DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            $conversations = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro na query conversations: " . $e->getMessage());
            return [];
        }

        // Formata para o formato esperado pela UI
        $threads = [];
        foreach ($conversations as $conv) {
                    $threads[] = [
                        'thread_id' => "whatsapp_{$conv['id']}",
                        'conversation_id' => $conv['id'],
                        'conversation_key' => $conv['conversation_key'],
                        'tenant_id' => $conv['tenant_id'] ?: null,
                        'tenant_name' => $conv['tenant_name'] ?: 'Sem tenant',
                        'contact' => $conv['contact_external_id'],
                        'contact_name' => $conv['contact_name'],
                        'last_activity' => $conv['last_message_at'] ?: $conv['created_at'],
                        'message_count' => (int) $conv['message_count'],
                        'inbound_count' => $conv['last_message_direction'] === 'inbound' ? 1 : 0, // Aproximação
                        'channel' => 'whatsapp',
                        'channel_type' => $conv['channel_type'], // Adiciona contexto
                        'status' => $conv['status'],
                        'unread_count' => (int) $conv['unread_count'],
                        'assigned_to' => $conv['assigned_to'],
                        'assigned_to_name' => $conv['assigned_to_name'],
                        'is_incoming_lead' => (bool) ($conv['is_incoming_lead'] ?? 0) // Flag de incoming lead
                    ];
        }

        return $threads;
    }

    /**
     * Busca threads de WhatsApp via eventos (fallback para compatibilidade)
     */
    private function getWhatsAppThreadsFromEvents(PDO $db, ?int $tenantId, string $status): array
    {
        // Verifica se a tabela existe
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'communication_events'");
            if ($checkStmt->rowCount() === 0) {
                return []; // Tabela não existe ainda
            }
        } catch (\Exception $e) {
            return []; // Erro ao verificar tabela
        }

        $where = ["ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"];
        $params = [];

        // IMPORTANTE: Não filtra por tenant_id se for NULL (mostra todas)
        if ($tenantId !== null) {
            $where[] = "ce.tenant_id = ?";
            $params[] = $tenantId;
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        // Busca eventos e processa em PHP (mais compatível)
        try {
            $stmt = $db->prepare("
                SELECT 
                    ce.event_id,
                    ce.tenant_id,
                    ce.event_type,
                    ce.payload,
                    ce.created_at,
                    t.name as tenant_name
                FROM communication_events ce
                LEFT JOIN tenants t ON ce.tenant_id = t.id
                {$whereClause}
                ORDER BY ce.created_at DESC
                LIMIT 200
            ");
            $stmt->execute($params);
            $events = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro na query WhatsApp threads: " . $e->getMessage());
            return [];
        }

        // Agrupa por tenant + contato
        $threadsMap = [];
        foreach ($events as $event) {
            $payload = json_decode($event['payload'], true);
            $from = $payload['from'] ?? $payload['message']['from'] ?? $payload['to'] ?? null;
            
            if (!$from) continue;
            
            $eventTenantId = $event['tenant_id'] ?? 0;
            $threadKey = "{$eventTenantId}_{$from}";
            
            if (!isset($threadsMap[$threadKey])) {
                $threadsMap[$threadKey] = [
                    'thread_id' => "whatsapp_{$eventTenantId}_{$from}",
                    'tenant_id' => $eventTenantId ?: null,
                    'tenant_name' => $event['tenant_name'],
                    'contact' => $from,
                    'last_activity' => $event['created_at'],
                    'message_count' => 0,
                    'inbound_count' => 0,
                    'channel' => 'whatsapp',
                    'status' => 'active',
                    'unread_count' => 0
                ];
            }
            
            $threadsMap[$threadKey]['message_count']++;
            if ($event['event_type'] === 'whatsapp.inbound.message') {
                $threadsMap[$threadKey]['inbound_count']++;
            }
            
            // Atualiza última atividade se for mais recente
            if (strtotime($event['created_at']) > strtotime($threadsMap[$threadKey]['last_activity'])) {
                $threadsMap[$threadKey]['last_activity'] = $event['created_at'];
            }
        }

        // Converte para array e ordena
        $threads = array_values($threadsMap);
        usort($threads, function($a, $b) {
            return strtotime($b['last_activity']) <=> strtotime($a['last_activity']);
        });

        return array_slice($threads, 0, 50);
    }

    /**
     * Busca threads de chat interno
     */
    private function getChatThreads(PDO $db, ?int $tenantId, string $status): array
    {
        // Verifica se a tabela existe
        try {
            $checkStmt = $db->query("SHOW TABLES LIKE 'chat_threads'");
            if ($checkStmt->rowCount() === 0) {
                return []; // Tabela não existe ainda
            }
        } catch (\Exception $e) {
            return []; // Erro ao verificar tabela
        }

        $where = [];
        $params = [];

        if ($tenantId) {
            $where[] = "ct.customer_id = ?";
            $params[] = $tenantId;
        }

        if ($status === 'active') {
            $where[] = "ct.status != 'closed'";
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        try {
            $stmt = $db->prepare("
                SELECT 
                    CONCAT('chat_', ct.id) as thread_id,
                    ct.id as original_thread_id,
                    ct.customer_id as tenant_id,
                    t.name as tenant_name,
                    ct.status,
                    ct.updated_at as last_activity,
                    (SELECT COUNT(*) FROM chat_messages cm WHERE cm.thread_id = ct.id) as message_count,
                    'chat' as channel
                FROM chat_threads ct
                LEFT JOIN tenants t ON ct.customer_id = t.id
                {$whereClause}
                ORDER BY ct.updated_at DESC
                LIMIT 50
            ");
            $stmt->execute($params);
            $threads = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro na query chat threads: " . $e->getMessage());
            return [];
        }

        foreach ($threads as &$thread) {
            $thread['unread_count'] = 0; // TODO: implementar
            $thread['contact'] = $thread['tenant_name'] ?? 'Cliente';
        }

        return $threads;
    }

    /**
     * Busca mensagens WhatsApp
     * 
     * Suporta dois formatos de thread_id:
     * - whatsapp_{conversation_id} (nova forma, via tabela conversations)
     * - whatsapp_{tenant_id}_{from} (forma antiga, via eventos)
     */
    private function getWhatsAppMessages(PDO $db, string $threadId): array
    {
        // 1. Tenta formato novo: whatsapp_{conversation_id}
        if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
            $conversationId = (int) $matches[1];
            return $this->getWhatsAppMessagesFromConversation($db, $conversationId);
        }

        // 2. Formato antigo: whatsapp_{tenant_id}_{from}
        if (preg_match('/whatsapp_(\d+)_(.+)/', $threadId, $matches)) {
            $tenantId = (int) $matches[1];
            $from = $matches[2];
            return $this->getWhatsAppMessagesFromEvents($db, $tenantId, $from);
        }

        return [];
    }

    /**
     * Busca mensagens de uma conversa específica (nova forma)
     * 
     * CORRIGIDO: Agora busca corretamente mesmo quando tenant_id é NULL
     * PATCH: Resolve @lid -> E.164 via cache + API do provider
     */
    private function getWhatsAppMessagesFromConversation(PDO $db, int $conversationId): array
    {
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
        
        // =====================
        // pnLid (@lid) resolver (mantido para enriquecimento opcional)
        // =====================
        
        // Normalização canônica de telefone (E.164 apenas dígitos; BR mantém 55...)
        // Observação: NÃO converte @lid em dígitos "como se fosse telefone".
        // @lid será tratado via resolver próprio.
        $normalizePhoneE164 = function($value) {
            if (empty($value)) return null;
            $s = (string)$value;
            // remove sufixo tipo @c.us, @s.whatsapp.net etc
            $s = preg_replace('/@.*$/', '', $s);
            // apenas dígitos
            $digits = preg_replace('/[^0-9]/', '', $s);
            if ($digits === '') return null;
            // mantém BR começando com 55 (E.164 "seco" sem +)
            if (strlen($digits) >= 12 && substr($digits, 0, 2) === '55') return $digits;
            // fallback: retorna dígitos (ex: números internacionais sem 55)
            return $digits;
        };

        $extractPnLid = function($jid) {
            if (empty($jid)) return null;
            $jid = (string)$jid;
            if (preg_match('/^([0-9]+)@lid$/', $jid, $m)) return $m[1];
            return null;
        };

        // Lê do cache (MySQL)
        $getPnLidCache = function($provider, $sessionId, $pnLid) use ($db) {
            try {
                $st = $db->prepare("SELECT phone_e164, updated_at FROM wa_pnlid_cache
                                 WHERE provider=? AND session_id=? AND pnlid=? LIMIT 1");
                $st->execute([$provider, $sessionId, $pnLid]);
                $row = $st->fetch(\PDO::FETCH_ASSOC);
                if (!$row) return null;
                // TTL opcional (ex: 30 dias)
                $ttlDays = 30;
                $updatedAt = strtotime($row['updated_at'] ?? '');
                if ($updatedAt && $updatedAt < strtotime("-{$ttlDays} days")) {
                    return null;
                }
                return $row['phone_e164'] ?: null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        // Salva no cache (MySQL)
        $setPnLidCache = function($provider, $sessionId, $pnLid, $phoneE164) use ($db) {
            try {
                $st = $db->prepare("
                    INSERT INTO wa_pnlid_cache (provider, session_id, pnlid, phone_e164)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE phone_e164=VALUES(phone_e164), updated_at=NOW()
                ");
                $st->execute([$provider, $sessionId, $pnLid, $phoneE164]);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        };

        // Chama API do provider (WPPConnect wrapper) para resolver pnLid -> telefone
        $resolvePnLidViaProvider = function($sessionId, $pnLid) use ($normalizePhoneE164) {
            $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
            if (!$baseUrl) {
                error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: baseUrl vazio. sessionId=%s, pnLid=%s', $sessionId, $pnLid));
                return null;
            }
            
            // Obtém secret para autenticação
            try {
                $secret = \PixelHub\Services\GatewaySecret::getDecrypted();
            } catch (\Exception $e) {
                error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Erro ao obter secret. sessionId=%s, pnLid=%s, error=%s', $sessionId, $pnLid, $e->getMessage()));
                return null;
            }
            
            if (empty($secret)) {
                error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Secret vazio. sessionId=%s, pnLid=%s', $sessionId, $pnLid));
                return null;
            }
            
            $url = rtrim($baseUrl, '/') . "/api/" . rawurlencode($sessionId) . "/contact/pn-lid/" . rawurlencode($pnLid);
            error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Chamando API. URL=%s', $url));
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => [
                    "Accept: application/json",
                    "X-Gateway-Secret: {$secret}"
                ],
            ]);
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Resposta. HTTP_CODE=%d, curl_error=%s, raw_response=%s', 
                $code, 
                $curlError ?: 'NONE',
                substr($raw ?: 'NULL', 0, 200)
            ));
            
            if ($code < 200 || $code >= 300 || !$raw) {
                error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: FALHA HTTP. code=%d', $code));
                return null;
            }
            
            $j = json_decode($raw, true);
            if (!is_array($j)) {
                error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: JSON inválido. raw=%s', substr($raw, 0, 200)));
                return null;
            }
            
            error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: JSON parseado. keys=%s', implode(', ', array_keys($j))));
            
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
            
            foreach ($candidates as $idx => $cand) {
                if ($cand) {
                    $e164 = $normalizePhoneE164($cand);
                    if ($e164) {
                        error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Telefone encontrado no campo index %d. valor=%s, e164=%s', $idx, $cand, $e164));
                        return $e164;
                    }
                }
            }
            
            // Se vier no formato JID:
            if (!empty($j['jid'])) {
                $e164 = $normalizePhoneE164($j['jid']);
                if ($e164) {
                    error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Telefone encontrado em jid. jid=%s, e164=%s', $j['jid'], $e164));
                    return $e164;
                }
            }
            
            error_log(sprintf('[PNLID_RESOLVE] resolvePnLidViaProvider: Nenhum telefone encontrado no JSON. json=%s', json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            return null;
        };

        // Função principal: normalizeSender() -> retorna e164 se possível, senão null
        $normalizeSender = function($jidOrNumber, $provider, $sessionId) use (
            $normalizePhoneE164, $extractPnLid, $getPnLidCache, $setPnLidCache, $resolvePnLidViaProvider
        ) {
            if (empty($jidOrNumber)) return null;
            $jidOrNumber = (string)$jidOrNumber;
            
            // Se NÃO é @lid, normaliza como telefone normal
            $pnLid = $extractPnLid($jidOrNumber);
            if (!$pnLid) {
                $normalized = $normalizePhoneE164($jidOrNumber);
                error_log(sprintf('[PNLID_RESOLVE] normalizeSender: NÃO é @lid. jidOrNumber=%s, normalized=%s', $jidOrNumber, $normalized ?: 'NULL'));
                return $normalized;
            }
            
            // É @lid -> tenta cache
            error_log(sprintf('[PNLID_RESOLVE] normalizeSender: Detectado @lid. jidOrNumber=%s, pnLid=%s, provider=%s, sessionId=%s', $jidOrNumber, $pnLid, $provider, $sessionId));
            $cached = $getPnLidCache($provider, $sessionId, $pnLid);
            if (!empty($cached)) {
                error_log(sprintf('[PNLID_RESOLVE] normalizeSender: Cache HIT. pnLid=%s, phone_e164=%s', $pnLid, $cached));
                return $cached;
            }
            error_log(sprintf('[PNLID_RESOLVE] normalizeSender: Cache MISS. Tentando resolver via API... pnLid=%s, sessionId=%s', $pnLid, $sessionId));
            
            // Resolve via API do provider
            $resolved = $resolvePnLidViaProvider($sessionId, $pnLid);
            if (!empty($resolved)) {
                error_log(sprintf('[PNLID_RESOLVE] normalizeSender: API resolveu com sucesso! pnLid=%s, phone_e164=%s', $pnLid, $resolved));
                $cacheSaved = $setPnLidCache($provider, $sessionId, $pnLid, $resolved);
                error_log(sprintf('[PNLID_RESOLVE] normalizeSender: Cache salvo=%s', $cacheSaved ? 'SIM' : 'NÃO'));
                return $resolved;
            }
            
            // Não conseguiu resolver: retorna null para evitar falso-match
            error_log(sprintf('[PNLID_RESOLVE] normalizeSender: FALHA - Não conseguiu resolver pnLid=%s, sessionId=%s. Retornando NULL.', $pnLid, $sessionId));
            return null;
        };

        // Normalização de canal (case/space insensitive)
        $normalizeChannel = function($s) {
            $s = trim((string)$s);
            $s = preg_replace('/\s+/', '', $s); // remove todos os espaços
            $s = mb_strtolower($s, 'UTF-8');
            return $s;
        };

        // Busca a conversa para pegar o contact_external_id, channel_id e remote_key
        $convStmt = $db->prepare("
            SELECT conversation_key, contact_external_id, remote_key, tenant_id, channel_type, channel_id
            FROM conversations
            WHERE id = ?
        ");
        $convStmt->execute([$conversationId]);
        $conversation = $convStmt->fetch();

        if (!$conversation) {
            return [];
        }

        $contactExternalId = $conversation['contact_external_id'];
        $tenantId = $conversation['tenant_id'];
        $sessionId = $conversation['channel_id'] ?? ''; // sessionId para resolver @lid
        $provider = 'wpp_gateway'; // Provider padrão para WhatsApp
        
        // CORREÇÃO: Se contact_external_id é um número, busca @lid mapeado para esse número
        // Isso permite encontrar eventos que usam @lid ao invés do número direto
        $lidBusinessIds = [];
        if (!empty($contactExternalId) && preg_match('/^[0-9]+$/', $contactExternalId)) {
            $lidStmt = $db->prepare("
                SELECT business_id 
                FROM whatsapp_business_ids 
                WHERE phone_number = ?
            ");
            $lidStmt->execute([$contactExternalId]);
            $lidMappings = $lidStmt->fetchAll(PDO::FETCH_COLUMN);
            $lidBusinessIds = $lidMappings ?: [];
            
            if (!empty($lidBusinessIds)) {
                error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - LID MAPPINGS: contact=' . $contactExternalId . ', lids=' . implode(', ', $lidBusinessIds));
            }
        }
        
        // NOVA ARQUITETURA: Usa remote_key da conversa como identidade primária
        $conversationRemoteKey = $conversation['remote_key'] ?? null;
        if (empty($conversationRemoteKey) && !empty($contactExternalId)) {
            // Fallback: cria remote_key a partir de contact_external_id (conversas antigas)
            if (strpos($contactExternalId, '@lid') !== false) {
                if (preg_match('/^([0-9]+)@lid$/', $contactExternalId, $m)) {
                    $conversationRemoteKey = 'lid:' . $m[1];
                }
            } else {
                $digits = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $contactExternalId));
                if ($digits !== '') {
                    $conversationRemoteKey = 'tel:' . $digits;
                }
            }
        }

        // Normaliza contact_external_id para E.164 (já deve estar em E.164, mas garante)
        $normalizedContactExternalId = $normalizePhoneE164($contactExternalId);
        
        // [LOG TEMPORARIO] Normalização
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - NORMALIZACAO: contact_external_id_original=' . ($contactExternalId ?: 'NULL') . ', normalized=' . ($normalizedContactExternalId ?: 'NULL'));

        if (empty($normalizedContactExternalId)) {
            error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - ERRO: normalizedContactExternalId está vazio após normalização');
            return []; // Não pode buscar sem contato
        }

        // CORREÇÃO: Filtra no SQL ao invés de buscar todos os eventos
        // Usa LIKE para pegar variações do telefone (com @c.us, @lid, etc)
        $where = [
            "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
        ];
        $params = [];

        // CORREÇÃO: Filtro mais robusto que pega variações do telefone
        // Usa múltiplos padrões para pegar: número puro, com @c.us, com 9º dígito, etc
        $contactPatterns = [
            "%{$normalizedContactExternalId}%", // Número normalizado
        ];
        
        // Se for número BR (começa com 55), adiciona variação com/sem 9º dígito
        if (strlen($normalizedContactExternalId) >= 12 && substr($normalizedContactExternalId, 0, 2) === '55') {
            // Tenta adicionar 9º dígito (se não tiver)
            if (strlen($normalizedContactExternalId) === 13) { // 55 + DDD + 9 dígitos
                // Remove 9º dígito para buscar variação sem ele
                $without9th = substr($normalizedContactExternalId, 0, 4) . substr($normalizedContactExternalId, 5);
                $contactPatterns[] = "%{$without9th}%";
                error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - ADICIONADO PADRAO: without9th=' . $without9th);
            } elseif (strlen($normalizedContactExternalId) === 12) { // 55 + DDD + 8 dígitos
                // Adiciona 9º dígito para buscar variação com ele
                $with9th = substr($normalizedContactExternalId, 0, 4) . '9' . substr($normalizedContactExternalId, 4);
                $contactPatterns[] = "%{$with9th}%";
                error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - ADICIONADO PADRAO: with9th=' . $with9th);
            }
        }
        
        // CORREÇÃO: Adiciona busca por @lid mapeado (se houver)
        if (!empty($lidBusinessIds)) {
            foreach ($lidBusinessIds as $lid) {
                $contactPatterns[] = "%{$lid}%";
            }
            error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - ADICIONADOS PADROES LID: ' . implode(', ', $lidBusinessIds));
        }
        
        // [LOG TEMPORARIO] Padrões de busca
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - PADROES DE BUSCA: count=' . count($contactPatterns) . ', patterns=' . implode(', ', $contactPatterns));
        
        // Monta condições OR para cada padrão
        // CORREÇÃO: Usa JSON_UNQUOTE para remover aspas do JSON_EXTRACT antes de fazer LIKE
        $contactConditions = [];
        foreach ($contactPatterns as $pattern) {
            $contactConditions[] = "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )";
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
        }
        $where[] = "(" . implode(" OR ", $contactConditions) . ")";

        // Filtro por tenant_id (se disponível)
        if ($tenantId) {
            $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
            $params[] = $tenantId;
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        // Busca eventos filtrados (limitado para performance)
        // [LOG TEMPORARIO] Query do thread
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - EXECUTANDO QUERY: conversation_id=' . $conversationId . ', contact=' . $normalizedContactExternalId . ', tenant_id=' . ($tenantId ?: 'NULL'));
        
        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                ce.event_type,
                ce.created_at,
                ce.payload,
                ce.metadata,
                ce.tenant_id
            FROM communication_events ce
            {$whereClause}
            ORDER BY ce.created_at ASC
            LIMIT 500
        ");
        $stmt->execute($params);
        $filteredEvents = $stmt->fetchAll();
        
        // [LOG TEMPORARIO] Resultado da query
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - QUERY RETORNOU: events_count=' . count($filteredEvents));

        // Validação final em PHP (garantir que mensagem pertence à conversa)
        // A query SQL já filtra a maioria, mas validação final garante precisão
        $messages = [];
        $excludedCount = 0;
        foreach ($filteredEvents as $event) {
            $payload = json_decode($event['payload'], true);
            $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
            $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
            
            // NOVA ARQUITETURA: Compara por remote_key (não depende de resolver @lid)
            $eventFromKey = $eventFrom ? $remoteKey($eventFrom) : null;
            $eventToKey = $eventTo ? $remoteKey($eventTo) : null;
            
            // Para grupos, extrai participant/author
            if ($eventFrom && strpos($eventFrom, '@g.us') !== false) {
                $participant = $payload['raw']['payload']['author'] 
                    ?? $payload['raw']['payload']['participant'] 
                    ?? $payload['message']['key']['participant'] ?? null;
                if ($participant) {
                    $eventFromKey = $remoteKey($participant);
                }
            }
            
            // Compara remote_key (identidade primária)
            $isFromThisContact = !empty($eventFromKey) && !empty($conversationRemoteKey) && $eventFromKey === $conversationRemoteKey;
            $isToThisContact = !empty($eventToKey) && !empty($conversationRemoteKey) && $eventToKey === $conversationRemoteKey;
            
            // CORREÇÃO: Se não bateu por remote_key, verifica se é @lid mapeado para o número da conversa
            if (!$isFromThisContact && !$isToThisContact && !empty($conversationRemoteKey) && strpos($conversationRemoteKey, 'tel:') === 0) {
                // Extrai número da conversa (sem prefixo tel:)
                $conversationPhone = substr($conversationRemoteKey, 4);
                
                // Se o evento tem @lid, verifica se está mapeado para o número da conversa
                if ($eventFromKey && strpos($eventFromKey, 'lid:') === 0) {
                    $lidId = substr($eventFromKey, 4);
                    $lidBusinessId = $lidId . '@lid';
                    
                    // Verifica se esse @lid está mapeado para o número da conversa
                    $checkLidStmt = $db->prepare("
                        SELECT phone_number 
                        FROM whatsapp_business_ids 
                        WHERE business_id = ? AND phone_number = ?
                        LIMIT 1
                    ");
                    $checkLidStmt->execute([$lidBusinessId, $conversationPhone]);
                    if ($checkLidStmt->fetchColumn()) {
                        $isFromThisContact = true;
                        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - MATCH VIA LID: eventFromKey=' . $eventFromKey . ', conversationRemoteKey=' . $conversationRemoteKey);
                    }
                }
                
                if ($eventToKey && strpos($eventToKey, 'lid:') === 0) {
                    $lidId = substr($eventToKey, 4);
                    $lidBusinessId = $lidId . '@lid';
                    
                    // Verifica se esse @lid está mapeado para o número da conversa
                    $checkLidStmt = $db->prepare("
                        SELECT phone_number 
                        FROM whatsapp_business_ids 
                        WHERE business_id = ? AND phone_number = ?
                        LIMIT 1
                    ");
                    $checkLidStmt->execute([$lidBusinessId, $conversationPhone]);
                    if ($checkLidStmt->fetchColumn()) {
                        $isToThisContact = true;
                        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - MATCH VIA LID: eventToKey=' . $eventToKey . ', conversationRemoteKey=' . $conversationRemoteKey);
                    }
                }
            }
            
            // Fallback: Se remote_key não está disponível, usa telefone normalizado (compatibilidade)
            if (!$isFromThisContact && !$isToThisContact && empty($conversationRemoteKey)) {
                $normalizedFrom = $eventFrom ? $normalizeSender($eventFrom, $provider, $sessionId) : null;
                $normalizedTo = $eventTo ? $normalizeSender($eventTo, $provider, $sessionId) : null;
                $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContactExternalId;
                $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContactExternalId;
            }
            
            // LOG TEMPORÁRIO: Valores críticos para debug
            error_log(sprintf('[MATCH_DEBUG] conversation_id=%d, eventFrom=%s, eventFromKey=%s, eventTo=%s, eventToKey=%s, conversationRemoteKey=%s, sessionId=%s',
                $conversationId,
                $eventFrom ?: 'NULL',
                $eventFromKey ?: 'NULL',
                $eventTo ?: 'NULL',
                $eventToKey ?: 'NULL',
                $conversationRemoteKey ?: 'NULL',
                $sessionId ?: 'NULL'
            ));
            
            error_log(sprintf('[MATCH_DEBUG] isFromThisContact=%s, isToThisContact=%s', $isFromThisContact ? 'true' : 'false', $isToThisContact ? 'true' : 'false'));
            
            if (!$isFromThisContact && !$isToThisContact) {
                $excludedCount++;
                // [LOG TEMPORARIO] Evento excluído por normalização
                error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - EVENTO EXCLUIDO: event_id=' . ($event['event_id'] ?? 'N/A') . ', from_original=' . ($eventFrom ?: 'NULL') . ', from_normalized=' . ($normalizedFrom ?: 'NULL') . ', to_original=' . ($eventTo ?: 'NULL') . ', to_normalized=' . ($normalizedTo ?: 'NULL') . ', expected=' . $normalizedContactExternalId);
                continue;
            }
            
            // Verifica se tenant_id bate (se ambos tiverem tenant_id definido)
            if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
                continue;
            }
            
            // Se conversation tem tenant_id mas evento não tem, aceita (fallback)
            // Se evento tem tenant_id mas conversation não, aceita (atualização)
            
            // Determina direction (robusto): usa event_type, mas garante coerência com ids normalizados
            $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            // Se o evento diz inbound mas o "to" é o contato, inverte (edge cases)
            if ($direction === 'inbound' && $isToThisContact && !$isFromThisContact) $direction = 'outbound';
            if ($direction === 'outbound' && $isFromThisContact && !$isToThisContact) $direction = 'inbound';
            
            // Extrai conteúdo da mensagem (suporta diferentes formatos de payload)
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body'] 
                ?? '';
            
            // Processa mídia base64 se ainda não foi processada (áudio OGG, imagens JPEG/PNG)
            // Isso garante que mídias em base64 sejam processadas e salvas em communication_media
            try {
                \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao processar mídia do evento: " . $e->getMessage());
            }
            
            // Busca informações da mídia processada (sempre verifica, mesmo se há conteúdo)
            // Isso permite detectar mídias que foram processadas de base64 no campo text
            $mediaInfo = null;
            try {
                $mediaInfo = \PixelHub\Services\WhatsAppMediaService::getMediaByEventId($event['event_id']);
                
                // Se encontrou mídia processada, limpa o conteúdo para não mostrar base64 ou dados brutos
                if ($mediaInfo && !empty($content)) {
                    // Verifica se o conteúdo parece ser base64 (áudio ou imagem codificada)
                    if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                        // Tenta decodificar para verificar se é base64 válido
                        $textCleaned = preg_replace('/\s+/', '', $content);
                        $decoded = base64_decode($textCleaned, true);
                        if ($decoded !== false) {
                            // Verifica se é áudio OGG, imagem JPEG ou PNG
                            $isOgg = substr($decoded, 0, 4) === 'OggS';
                            $isJpeg = substr($textCleaned, 0, 4) === '/9j/';
                            $isPng = substr($textCleaned, 0, 12) === 'iVBORw0KGgo';
                            
                            if ($isOgg || $isJpeg || $isPng || strlen($decoded) > 1000) {
                                // É mídia em base64, limpa o conteúdo
                                $content = '';
                            }
                        }
                    } else {
                        // Se o conteúdo é muito longo e há mídia processada, provavelmente é dados brutos
                        // Limpa para não poluir a interface
                        if (strlen($content) > 500) {
                            $content = '';
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao buscar mídia: " . $e->getMessage());
            }
            
            // Se não encontrou mídia e não há conteúdo, mostra tipo de mídia
            if (empty($content) && !$mediaInfo) {
                if (isset($payload['type']) || isset($payload['message']['type'])) {
                    $mediaType = $payload['type'] ?? $payload['message']['type'] ?? 'media';
                    $content = "[{$mediaType}]";
                }
            }
            
            // Sanitiza mensagens muito longas sem quebra (ex: base64/tokens)
            $content = self::sanitizeLongMessage($content);
            
            // Extrai channel_id do payload ou metadata
            $eventMetadata = json_decode($event['metadata'] ?? '{}', true);
            $eventChannelId = $payload['channel_id'] 
                ?? $payload['channel'] 
                ?? $payload['session']['id'] 
                ?? $payload['session']['session']
                ?? $payload['data']['session']['id'] ?? null
                ?? $payload['data']['session']['session'] ?? null
                ?? $payload['data']['channel'] ?? null
                ?? $eventMetadata['channel_id'] ?? null
                ?? $eventMetadata['channel'] ?? null
                ?? $sessionId; // Fallback: usa channel_id da conversa
            
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => $eventMetadata,
                'from_raw' => $eventFrom,
                'to_raw' => $eventTo,
                'from_e164' => $normalizedFrom,
                'to_e164' => $normalizedTo,
                'is_inbound' => ($direction === 'inbound'),
                'channel_id' => $eventChannelId, // Identifica qual sessão recebeu/enviou
                'media' => $mediaInfo // Informações da mídia (se houver)
            ];
        }
        
        // [LOG TEMPORARIO] Resultado final da validação
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - RESULTADO FINAL: messages_count=' . count($messages) . ', excluded_count=' . $excludedCount . ', filtered_events_count=' . count($filteredEvents));

        return $messages;
    }

    /**
     * Busca mensagens via eventos (forma antiga, fallback)
     */
    private function getWhatsAppMessagesFromEvents(PDO $db, int $tenantId, string $from): array
    {
        // Busca todos os eventos e filtra em PHP (mais compatível)
        $where = ["ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"];
        $params = [];

        if ($tenantId > 0) {
            $where[] = "ce.tenant_id = ?";
            $params[] = $tenantId;
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                ce.event_type,
                ce.created_at,
                ce.payload,
                ce.metadata
            FROM communication_events ce
            {$whereClause}
            ORDER BY ce.created_at ASC
        ");
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        // Filtra e formata mensagens
        $messages = [];
        foreach ($events as $event) {
            $payload = json_decode($event['payload'], true);
            $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
            $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
            
            // Verifica se é desta conversa
            if ($eventFrom !== $from && $eventTo !== $from) {
                continue;
            }
            
            $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            
            $content = $payload['body'] 
                ?? $payload['text'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body'] 
                ?? '';
            
            // Processa mídia base64 se ainda não foi processada (áudio OGG, imagens JPEG/PNG)
            try {
                \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao processar mídia do evento: " . $e->getMessage());
            }
            
            // Busca informações da mídia processada (sempre verifica, mesmo se há conteúdo)
            $mediaInfo = null;
            try {
                $mediaInfo = \PixelHub\Services\WhatsAppMediaService::getMediaByEventId($event['event_id']);
                
                // Se encontrou mídia, limpa conteúdo se for base64
                if ($mediaInfo && !empty($content)) {
                    if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                        $textCleaned = preg_replace('/\s+/', '', $content);
                        $decoded = base64_decode($textCleaned, true);
                        if ($decoded !== false) {
                            if (substr($decoded, 0, 4) === 'OggS' || strlen($decoded) > 1000) {
                                $content = '';
                            }
                        }
                    } else if (strlen($content) > 500) {
                        $content = '';
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao buscar mídia: " . $e->getMessage());
            }
            
            // Se não encontrou mídia e não há conteúdo, mostra tipo de mídia
            if (empty($content) && !$mediaInfo) {
                if (isset($payload['type']) || isset($payload['message']['type'])) {
                    $mediaType = $payload['type'] ?? $payload['message']['type'] ?? 'media';
                    $content = "[{$mediaType}]";
                }
            }
            
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => json_decode($event['metadata'] ?? '{}', true),
                'media' => $mediaInfo // Informações da mídia (se houver) - objeto completo
            ];
        }

        return $messages;
    }

    /**
     * Busca informações do thread WhatsApp
     * 
     * Suporta dois formatos:
     * - whatsapp_{conversation_id} (nova forma)
     * - whatsapp_{tenant_id}_{from} (forma antiga)
     */
    private function getWhatsAppThreadInfo(PDO $db, string $threadId): ?array
    {
        // 1. Tenta formato novo: whatsapp_{conversation_id}
        if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
            $conversationId = (int) $matches[1];
            
            $stmt = $db->prepare("
                SELECT 
                    c.*,
                    t.name as tenant_name,
                    tmc.channel_id as tenant_channel_id,
                    u.name as assigned_to_name
                FROM conversations c
                LEFT JOIN tenants t ON c.tenant_id = t.id
                LEFT JOIN tenant_message_channels tmc ON c.tenant_id = tmc.tenant_id AND tmc.provider = 'wpp_gateway' AND tmc.is_enabled = 1
                LEFT JOIN users u ON c.assigned_to = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if ($conversation) {
                // Busca channel_id usado nas mensagens originais da conversa (prioridade sobre tenant_channel_id)
                // CORRIGIDO: Garante que channel_id seja string (vem do banco como VARCHAR(100))
                $channelId = isset($conversation['tenant_channel_id']) && $conversation['tenant_channel_id'] !== '' 
                    ? trim((string) $conversation['tenant_channel_id']) 
                    : null;
                
                // Tenta buscar channel_id dos eventos/mensagens da conversa
                // Busca eventos relacionados ao contato desta conversa
                $contactId = $conversation['contact_external_id'];
                if ($contactId) {
                    $eventStmt = $db->prepare("
                        SELECT ce.payload
                        FROM communication_events ce
                        WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                        AND (
                            JSON_EXTRACT(ce.payload, '$.from') = ?
                            OR JSON_EXTRACT(ce.payload, '$.to') = ?
                            OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
                            OR JSON_EXTRACT(ce.payload, '$.message.to') = ?
                        )
                        ORDER BY ce.created_at DESC
                        LIMIT 1
                    ");
                    $eventStmt->execute([$contactId, $contactId, $contactId, $contactId]);
                    $event = $eventStmt->fetch();
                    
                    if ($event && $event['payload']) {
                        $payload = json_decode($event['payload'], true);
                        if (isset($payload['channel_id']) && !empty($payload['channel_id'])) {
                            // CORRIGIDO: Mantém como string, não converte para int
                            $channelId = trim((string) $payload['channel_id']);
                            error_log("[CommunicationHub::getWhatsAppThreadInfo] Channel_id encontrado nos eventos: {$channelId} para contato: {$contactId}");
                        }
                    }
                }
                
                // Se ainda não tem channel_id, tenta buscar qualquer canal habilitado (fallback)
                if (!$channelId) {
                    $fallbackStmt = $db->prepare("
                        SELECT channel_id 
                        FROM tenant_message_channels 
                        WHERE provider = 'wpp_gateway' 
                        AND is_enabled = 1
                        LIMIT 1
                    ");
                    $fallbackStmt->execute();
                    $fallback = $fallbackStmt->fetch();
                    if ($fallback) {
                        // CORRIGIDO: Mantém como string, não converte para int
                        $channelId = trim((string) $fallback['channel_id']);
                        error_log("[CommunicationHub::getWhatsAppThreadInfo] Usando canal fallback: {$channelId}");
                    }
                }
                
                error_log("[CommunicationHub::getWhatsAppThreadInfo] Thread {$threadId}: channel_id={$channelId}, tenant_id={$conversation['tenant_id']}, contact={$contactId}");
                
                return [
                    'thread_id' => $threadId,
                    'conversation_id' => $conversationId,
                    'conversation_key' => $conversation['conversation_key'],
                    'tenant_id' => $conversation['tenant_id'],
                    'tenant_name' => $conversation['tenant_name'],
                    'contact' => $conversation['contact_external_id'],
                    'contact_name' => $conversation['contact_name'],
                    'channel' => 'whatsapp',
                    'channel_id' => $channelId,
                    'status' => $conversation['status'],
                    'assigned_to' => $conversation['assigned_to'],
                    'assigned_to_name' => $conversation['assigned_to_name'],
                    'message_count' => (int) $conversation['message_count'],
                    'unread_count' => (int) $conversation['unread_count']
                ];
            }
        }

        // 2. Formato antigo: whatsapp_{tenant_id}_{from}
        if (preg_match('/whatsapp_(\d+)_(.+)/', $threadId, $matches)) {
            $tenantId = (int) $matches[1];
            $from = $matches[2];

            $stmt = $db->prepare("
                SELECT t.*, tmc.channel_id
                FROM tenants t
                LEFT JOIN tenant_message_channels tmc ON t.id = tmc.tenant_id AND tmc.provider = 'wpp_gateway'
                WHERE t.id = ?
            ");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();

            if ($tenant) {
                return [
                    'thread_id' => $threadId,
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenant['name'],
                    'contact' => $from,
                    'channel' => 'whatsapp',
                    'channel_id' => $tenant['channel_id'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Busca mensagens de chat
     */
    private function getChatMessages(PDO $db, string $threadId): array
    {
        if (preg_match('/chat_(\d+)/', $threadId, $matches)) {
            $originalThreadId = (int) $matches[1];

            $stmt = $db->prepare("
                SELECT * FROM chat_messages
                WHERE thread_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$originalThreadId]);
            return $stmt->fetchAll();
        }

        return [];
    }

    /**
     * Busca informações do thread de chat
     */
    private function getChatThreadInfo(PDO $db, string $threadId): ?array
    {
        if (preg_match('/chat_(\d+)/', $threadId, $matches)) {
            $originalThreadId = (int) $matches[1];

            $stmt = $db->prepare("
                SELECT ct.*, t.name as tenant_name
                FROM chat_threads ct
                LEFT JOIN tenants t ON ct.customer_id = t.id
                WHERE ct.id = ?
            ");
            $stmt->execute([$originalThreadId]);
            $thread = $stmt->fetch();

            if ($thread) {
                return [
                    'thread_id' => $threadId,
                    'original_thread_id' => $originalThreadId,
                    'tenant_id' => $thread['customer_id'],
                    'tenant_name' => $thread['tenant_name'],
                    'channel' => 'chat',
                    'status' => $thread['status']
                ];
            }
        }

        return null;
    }

    /**
     * Retorna lista de conversas em JSON (para atualização AJAX)
     * 
     * GET /communication-hub/conversations-list?channel=all&tenant_id=X&status=active
     * 
     * Retorna {success: bool, threads: array} para atualização da lista sem reload
     */
    public function getConversationsList(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $channel = $_GET['channel'] ?? 'all';
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $status = $_GET['status'] ?? 'active';

        // [LOG TEMPORARIO] Início da busca de lista
        error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - INICIADO: channel=' . $channel . ', tenant_id=' . ($tenantId ?: 'NULL') . ', status=' . $status);

        $db = DB::getConnection();

        try {
            // Busca threads de WhatsApp
            $whatsappThreads = $this->getWhatsAppThreads($db, $tenantId, $status);
            
            // Busca threads de chat interno
            $chatThreads = $this->getChatThreads($db, $tenantId, $status);

            // Combina e ordena por última atividade
            $allThreads = array_merge($whatsappThreads ?? [], $chatThreads ?? []);
            
            // [LOG TEMPORARIO] Antes de ordenar
            if (!empty($allThreads)) {
                $firstBeforeSort = $allThreads[0] ?? null;
                error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - ANTES SORT: threads_count=' . count($allThreads) . ', primeiro_thread_id=' . ($firstBeforeSort['thread_id'] ?? 'N/A') . ', last_activity=' . ($firstBeforeSort['last_activity'] ?? 'N/A'));
            }
            
            if (!empty($allThreads)) {
                usort($allThreads, function($a, $b) {
                    $timeA = strtotime($a['last_activity'] ?? '1970-01-01');
                    $timeB = strtotime($b['last_activity'] ?? '1970-01-01');
                    return $timeB <=> $timeA; // Mais recente primeiro
                });
                
                // [LOG TEMPORARIO] Após ordenar
                $firstAfterSort = $allThreads[0] ?? null;
                error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - APOS SORT: primeiro_thread_id=' . ($firstAfterSort['thread_id'] ?? 'N/A') . ', last_activity=' . ($firstAfterSort['last_activity'] ?? 'N/A'));

                // Filtra por canal se necessário
                if ($channel !== 'all') {
                    $allThreads = array_filter($allThreads, function($thread) use ($channel) {
                        return ($thread['channel'] ?? '') === $channel;
                    });
                    $allThreads = array_values($allThreads); // Reindexa array
                    
                    // CRÍTICO: Reordena após filtrar (array_filter pode desordenar)
                    usort($allThreads, function($a, $b) {
                        $timeA = strtotime($a['last_activity'] ?? '1970-01-01');
                        $timeB = strtotime($b['last_activity'] ?? '1970-01-01');
                        return $timeB <=> $timeA; // Mais recente primeiro
                    });
                    
                    // [LOG TEMPORARIO] Após filtrar e reordenar
                    if (!empty($allThreads)) {
                        $firstAfterFilter = $allThreads[0] ?? null;
                        error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - APOS FILTRO: threads_count=' . count($allThreads) . ', primeiro_thread_id=' . ($firstAfterFilter['thread_id'] ?? 'N/A') . ', last_activity=' . ($firstAfterFilter['last_activity'] ?? 'N/A'));
                    }
                }
            }

            // [LOG TEMPORARIO] Resultado final
            error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - RETORNO FINAL: threads_count=' . count($allThreads ?? []));
            if (!empty($allThreads)) {
                $firstFinal = $allThreads[0] ?? null;
                $secondFinal = $allThreads[1] ?? null;
                error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - PRIMEIRO: thread_id=' . ($firstFinal['thread_id'] ?? 'N/A') . ', last_activity=' . ($firstFinal['last_activity'] ?? 'N/A') . ', unread_count=' . ($firstFinal['unread_count'] ?? 0));
                if ($secondFinal) {
                    error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - SEGUNDO: thread_id=' . ($secondFinal['thread_id'] ?? 'N/A') . ', last_activity=' . ($secondFinal['last_activity'] ?? 'N/A') . ', unread_count=' . ($secondFinal['unread_count'] ?? 0));
                }
            }

            $this->json([
                'success' => true,
                'threads' => $allThreads ?? []
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar lista de conversas: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao buscar conversas'], 500);
        }
    }

    /**
     * Verifica se há novas mensagens ou atualizações na lista de conversas
     * 
     * GET /communication-hub/check-updates?after_timestamp=Y
     * 
     * Retorna {has_updates: bool, latest_update_ts: string|null} para polling da lista
     */
    public function checkUpdates(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $afterTimestamp = $_GET['after_timestamp'] ?? null;
        $tenantId = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;
        $status = $_GET['status'] ?? 'active';

        $db = DB::getConnection();

        try {
            // Verifica se há conversas atualizadas após o timestamp
            $where = ["c.channel_type = 'whatsapp'"];
            $params = [];

            if ($tenantId) {
                $where[] = "c.tenant_id = ?";
                $params[] = $tenantId;
            }

            if ($status === 'active') {
                $where[] = "c.status NOT IN ('closed', 'archived')";
            } elseif ($status === 'closed') {
                $where[] = "c.status IN ('closed', 'archived')";
            }

            if ($afterTimestamp) {
                $where[] = "(c.updated_at > ? OR c.last_message_at > ?)";
                $params[] = $afterTimestamp;
                $params[] = $afterTimestamp;
            }

            $whereClause = "WHERE " . implode(" AND ", $where);

            $stmt = $db->prepare("
                SELECT MAX(GREATEST(COALESCE(c.updated_at, '1970-01-01'), COALESCE(c.last_message_at, '1970-01-01'))) as latest_update_ts
                FROM conversations c
                {$whereClause}
                LIMIT 1
            ");
            $stmt->execute($params);
            $result = $stmt->fetch();

            $latestUpdateTs = $result['latest_update_ts'] ?? null;
            $hasUpdates = false;

            if ($latestUpdateTs) {
                if (!$afterTimestamp || strtotime($latestUpdateTs) > strtotime($afterTimestamp)) {
                    $hasUpdates = true;
                }
            }

            $this->json([
                'success' => true,
                'has_updates' => $hasUpdates,
                'latest_update_ts' => $latestUpdateTs
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao verificar atualizações: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao verificar atualizações'], 500);
        }
    }

    /**
     * Verifica se há novas mensagens (check leve, otimizado)
     * 
     * GET /communication-hub/messages/check?thread_id=X&after_timestamp=Y&after_event_id=Z
     * 
     * Retorna apenas {has_new: bool} para verificação rápida
     * 
     * OTIMIZADO: Não carrega payloads JSON, apenas verifica existência
     */
    public function checkNewMessages(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $threadId = $_GET['thread_id'] ?? null;
        $afterTimestamp = $_GET['after_timestamp'] ?? null;
        $afterEventId = $_GET['after_event_id'] ?? null;

        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            // [LOG TEMPORARIO] Início do check
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - INICIADO: thread_id=' . $threadId . ', after_timestamp=' . ($afterTimestamp ?: 'NULL') . ', after_event_id=' . ($afterEventId ?: 'NULL'));
            
            // Resolve thread para pegar dados da conversa
            $conversationData = $this->resolveThreadToConversation($db, $threadId);
            if (!$conversationData) {
                error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - ERRO: Thread não encontrado');
                $this->json(['success' => false, 'error' => 'Thread não encontrado'], 404);
                return;
            }

            $contactExternalId = $conversationData['contact_external_id'];
            $tenantId = $conversationData['tenant_id'];
            
            // [LOG TEMPORARIO] Dados da conversa
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - CONVERSA: conversation_id=' . ($conversationData['conversation_id'] ?? 'NULL') . ', contact_external_id=' . ($contactExternalId ?: 'NULL') . ', tenant_id=' . ($tenantId ?: 'NULL'));
            
            // CORREÇÃO: Normalização robusta que lida com variações (@c.us, 9º dígito)
            $normalizeContact = function($contact) {
                if (empty($contact)) return null;
                // Remove tudo após @ (ex: 554796164699@c.us -> 554796164699)
                $cleaned = preg_replace('/@.*$/', '', (string) $contact);
                // Remove caracteres não numéricos
                $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
                // Se for número BR (começa com 55), normaliza para E.164
                if (strlen($digitsOnly) >= 12 && substr($digitsOnly, 0, 2) === '55') {
                    // Retorna apenas dígitos (E.164 sem formatação)
                    return $digitsOnly;
                }
                return $digitsOnly;
            };
            $normalizedContact = $normalizeContact($contactExternalId);
            
            // [LOG TEMPORARIO] Normalização
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - NORMALIZACAO: contact_external_id_original=' . ($contactExternalId ?: 'NULL') . ', normalized=' . ($normalizedContact ?: 'NULL'));

            if (empty($normalizedContact)) {
                error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - ERRO: normalizedContact está vazio');
                $this->json(['success' => true, 'has_new' => false]);
                return;
            }

            // CORREÇÃO: Filtra no SQL ao invés de buscar todos os eventos
            // Query leve: verifica existência sem carregar payload completo
            $where = [
                "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
            ];
            $params = [];

            // CORREÇÃO: Filtro mais robusto que pega variações do telefone
            $contactPatterns = [
                "%{$normalizedContact}%", // Número normalizado
            ];
            
            // Se for número BR (começa com 55), adiciona variação com/sem 9º dígito
            if (strlen($normalizedContact) >= 12 && substr($normalizedContact, 0, 2) === '55') {
                if (strlen($normalizedContact) === 13) { // 55 + DDD + 9 dígitos
                    $without9th = substr($normalizedContact, 0, 4) . substr($normalizedContact, 5);
                    $contactPatterns[] = "%{$without9th}%";
                } elseif (strlen($normalizedContact) === 12) { // 55 + DDD + 8 dígitos
                    $with9th = substr($normalizedContact, 0, 4) . '9' . substr($normalizedContact, 4);
                    $contactPatterns[] = "%{$with9th}%";
                }
            }
            
            // Monta condições OR para cada padrão
            // CORREÇÃO: Usa JSON_UNQUOTE para remover aspas do JSON_EXTRACT antes de fazer LIKE
            $contactConditions = [];
            foreach ($contactPatterns as $pattern) {
                $contactConditions[] = "(
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
                )";
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern;
            }
            $where[] = "(" . implode(" OR ", $contactConditions) . ")";

            // Filtro por tenant_id (se disponível)
            if ($tenantId) {
                $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
                $params[] = $tenantId;
            }

            if ($afterTimestamp) {
                $where[] = "(ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))";
                $params[] = $afterTimestamp;
                $params[] = $afterTimestamp;
                $params[] = $afterEventId ?? '';
            }

            $whereClause = "WHERE " . implode(" AND ", $where);
            
            // [LOG TEMPORARIO] Query SQL
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - QUERY SQL: WHERE=' . $whereClause . ', params_count=' . count($params));
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - CONTACT PATTERNS: ' . json_encode($contactPatterns));

            // INSTRUMENTAÇÃO: Conta total de eventos que atendem aos critérios
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM communication_events ce
                {$whereClause}
            ");
            $countStmt->execute($params);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $totalCount = $countResult['total'] ?? 0;
            
            // [LOG TEMPORARIO] COUNT total
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - COUNT(*) TOTAL: ' . $totalCount);
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - CONDICAO EXATA: thread_id=' . $threadId . ', after_timestamp=' . ($afterTimestamp ?: 'NULL') . ', after_event_id=' . ($afterEventId ?: 'NULL') . ', normalized_contact=' . ($normalizedContact ?: 'NULL'));

            // Check leve: busca apenas event_id e payload mínimo (só para filtrar por contato)
            // Limite baixo: só precisa verificar se existe pelo menos 1
            $stmt = $db->prepare("
                SELECT ce.event_id, ce.payload
                FROM communication_events ce
                {$whereClause}
                ORDER BY ce.created_at ASC, ce.event_id ASC
                LIMIT 20
            ");
            $stmt->execute($params);
            $events = $stmt->fetchAll();
            
            // [LOG TEMPORARIO] Resultado da query
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - QUERY RETORNOU: events_count=' . count($events) . ', COUNT_TOTAL=' . $totalCount);

            // Filtra rapidamente para verificar se há mensagens desta conversa
            $hasNew = false;
            $matchedEvents = [];

            foreach ($events as $event) {
                $payload = json_decode($event['payload'], true);
                $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
                $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
                
                $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
                $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
                
                $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContact;
                $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContact;
                
                // [LOG TEMPORARIO] Validação de cada evento
                error_log(sprintf(
                    '[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - VALIDANDO EVENTO: event_id=%s, from_raw=%s, from_normalized=%s, to_raw=%s, to_normalized=%s, expected=%s, isFromThisContact=%s, isToThisContact=%s',
                    $event['event_id'] ?? 'NULL',
                    $eventFrom ?: 'NULL',
                    $normalizedFrom ?: 'NULL',
                    $eventTo ?: 'NULL',
                    $normalizedTo ?: 'NULL',
                    $normalizedContact,
                    $isFromThisContact ? 'true' : 'false',
                    $isToThisContact ? 'true' : 'false'
                ));
                
                if ($isFromThisContact || $isToThisContact) {
                    $hasNew = true;
                    $matchedEvents[] = $event['event_id'];
                    // [LOG TEMPORARIO] Nova mensagem detectada
                    error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - NOVA MENSAGEM DETECTADA: event_id=' . $event['event_id'] . ', contact=' . $normalizedContact);
                    // Não quebra aqui - continua verificando para logar todos os matches
                }
            }

            // [LOG TEMPORARIO] Resultado do check
            error_log('[LOG TEMPORARIO] CommunicationHub::checkNewMessages() - RESULTADO: has_new=' . ($hasNew ? 'true' : 'false') . ', events_checked=' . count($events) . ', matched_events=' . count($matchedEvents) . ', matched_ids=[' . implode(', ', $matchedEvents) . ']');

            $this->json([
                'success' => true,
                'has_new' => $hasNew
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao verificar novas mensagens: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao verificar mensagens'], 500);
        }
    }

    /**
     * Busca novas mensagens (incremental, otimizado)
     * 
     * GET /communication-hub/messages/new?thread_id=X&after_timestamp=Y&after_event_id=Z
     */
    public function getNewMessages(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $threadId = $_GET['thread_id'] ?? null;
        $afterTimestamp = $_GET['after_timestamp'] ?? null;
        $afterEventId = $_GET['after_event_id'] ?? null;

        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            $conversationData = $this->resolveThreadToConversation($db, $threadId);
            if (!$conversationData) {
                $this->json(['success' => false, 'error' => 'Thread não encontrado'], 404);
                return;
            }

            $messages = $this->getWhatsAppMessagesIncremental(
                $db,
                $conversationData['conversation_id'],
                $conversationData['contact_external_id'],
                $conversationData['tenant_id'],
                $afterTimestamp,
                $afterEventId
            );

            $this->json([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar novas mensagens: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao buscar mensagens'], 500);
        }
    }

    /**
     * Busca uma mensagem específica por event_id
     * 
     * GET /communication-hub/message?event_id=X&thread_id=Y (thread_id opcional para validação)
     * 
     * NOTA: thread_id é opcional mas recomendado para validação de isolamento
     */
    public function getMessage(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $eventId = $_GET['event_id'] ?? null;
        $threadId = $_GET['thread_id'] ?? null; // Opcional: validação de isolamento
        
        if (empty($eventId)) {
            $this->json(['success' => false, 'error' => 'event_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT 
                    ce.event_id,
                    ce.event_type,
                    ce.created_at,
                    ce.payload,
                    ce.metadata,
                    ce.tenant_id
                FROM communication_events ce
                WHERE ce.event_id = ?
                LIMIT 1
            ");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch();

            if (!$event) {
                $this->json(['success' => false, 'error' => 'Mensagem não encontrada'], 404);
                return;
            }

            // Validação de isolamento: se thread_id fornecido, valida que mensagem pertence à thread
            if (!empty($threadId)) {
                $conversationData = $this->resolveThreadToConversation($db, $threadId);
                if ($conversationData) {
                    $payload = json_decode($event['payload'], true);
                    $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
                    $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
                    
                    $normalizeContact = function($contact) {
                        if (empty($contact)) return null;
                        return preg_replace('/@.*$/', '', (string) $contact);
                    };
                    
                    $normalizedContact = $normalizeContact($conversationData['contact_external_id']);
                    $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
                    $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
                    
                    $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContact;
                    $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContact;
                    
                    if (!$isFromThisContact && !$isToThisContact) {
                        // Mensagem não pertence à thread solicitada
                        $this->json(['success' => false, 'error' => 'Mensagem não pertence à thread'], 403);
                        return;
                    }
                }
            }

            $payload = json_decode($event['payload'], true);
            $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body'] 
                ?? '';
            
            if (empty($content)) {
                if (isset($payload['type']) || isset($payload['message']['type'])) {
                    $mediaType = $payload['type'] ?? $payload['message']['type'] ?? 'media';
                    $content = "[{$mediaType}]";
                }
            }
            
            // Sanitiza mensagens muito longas sem quebra
            $content = self::sanitizeLongMessage($content);
            
            // Processa mídia base64 se ainda não foi processada (áudio OGG, imagens JPEG/PNG)
            try {
                \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao processar mídia do evento: " . $e->getMessage());
            }
            
            // Busca informações da mídia processada (se houver)
            $mediaInfo = null;
            try {
                $mediaInfo = \PixelHub\Services\WhatsAppMediaService::getMediaByEventId($event['event_id']);
                
                // Se encontrou mídia, limpa conteúdo se for base64
                if ($mediaInfo && !empty($content)) {
                    if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                        $textCleaned = preg_replace('/\s+/', '', $content);
                        $decoded = base64_decode($textCleaned, true);
                        if ($decoded !== false) {
                            if (substr($decoded, 0, 4) === 'OggS' || strlen($decoded) > 1000) {
                                $content = '';
                            }
                        }
                    } else if (strlen($content) > 500) {
                        $content = '';
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao buscar mídia: " . $e->getMessage());
            }
            
            $message = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => json_decode($event['metadata'] ?? '{}', true),
                'media' => $mediaInfo // Inclui objeto media completo quando existir
            ];

            $this->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar mensagem: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao buscar mensagem'], 500);
        }
    }

    /**
     * Helper: Resolve thread_id para dados da conversa
     */
    private function resolveThreadToConversation(PDO $db, string $threadId): ?array
    {
        // Formato novo: whatsapp_{conversation_id}
        if (preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
            $conversationId = (int) $matches[1];
            
            $stmt = $db->prepare("
                SELECT id as conversation_id, contact_external_id, tenant_id
                FROM conversations
                WHERE id = ?
            ");
            $stmt->execute([$conversationId]);
            $conv = $stmt->fetch();
            
            if ($conv) {
                return [
                    'conversation_id' => $conv['conversation_id'],
                    'contact_external_id' => $conv['contact_external_id'],
                    'tenant_id' => $conv['tenant_id']
                ];
            }
        }

        // Formato antigo: whatsapp_{tenant_id}_{from}
        if (preg_match('/whatsapp_(\d+)_(.+)/', $threadId, $matches)) {
            return [
                'conversation_id' => null, // Não tem conversation
                'contact_external_id' => $matches[2],
                'tenant_id' => (int) $matches[1]
            ];
        }

        return null;
    }

    /**
     * Busca mensagens incrementais (apenas novas após marcador)
     * 
     * Usa created_at indexado + tie-breaker event_id para evitar perder mensagens
     * em timestamps iguais
     */
    private function getWhatsAppMessagesIncremental(
        PDO $db,
        ?int $conversationId,
        string $contactExternalId,
        ?int $tenantId,
        ?string $afterTimestamp,
        ?string $afterEventId
    ): array {
        // CORREÇÃO: Normalização robusta que lida com variações (@c.us, 9º dígito)
        $normalizeContact = function($contact) {
            if (empty($contact)) return null;
            // Remove tudo após @ (ex: 554796164699@c.us -> 554796164699)
            $cleaned = preg_replace('/@.*$/', '', (string) $contact);
            // Remove caracteres não numéricos
            $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
            // Se for número BR (começa com 55), normaliza para E.164
            if (strlen($digitsOnly) >= 12 && substr($digitsOnly, 0, 2) === '55') {
                // Retorna apenas dígitos (E.164 sem formatação)
                return $digitsOnly;
            }
            return $digitsOnly;
        };
        $normalizedContactExternalId = $normalizeContact($contactExternalId);

        if (empty($normalizedContactExternalId)) {
            return []; // Não pode buscar sem contato
        }

        // CORREÇÃO: Filtra no SQL ao invés de buscar todos os eventos
        // Build query incremental (usando índice created_at + tie-breaker event_id)
        $where = [
            "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
        ];
        $params = [];

        // CORREÇÃO: Filtro mais robusto que pega variações do telefone (mesma lógica do método principal)
        $contactPatterns = [
            "%{$normalizedContactExternalId}%", // Número normalizado
        ];
        
        // Se for número BR (começa com 55), adiciona variação com/sem 9º dígito
        if (strlen($normalizedContactExternalId) >= 12 && substr($normalizedContactExternalId, 0, 2) === '55') {
            if (strlen($normalizedContactExternalId) === 13) { // 55 + DDD + 9 dígitos
                $without9th = substr($normalizedContactExternalId, 0, 4) . substr($normalizedContactExternalId, 5);
                $contactPatterns[] = "%{$without9th}%";
            } elseif (strlen($normalizedContactExternalId) === 12) { // 55 + DDD + 8 dígitos
                $with9th = substr($normalizedContactExternalId, 0, 4) . '9' . substr($normalizedContactExternalId, 4);
                $contactPatterns[] = "%{$with9th}%";
            }
        }
        
        // Monta condições OR para cada padrão
        // CORREÇÃO: Usa JSON_UNQUOTE para remover aspas do JSON_EXTRACT antes de fazer LIKE
        $contactConditions = [];
        foreach ($contactPatterns as $pattern) {
            $contactConditions[] = "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )";
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
            $params[] = $pattern;
        }
        $where[] = "(" . implode(" OR ", $contactConditions) . ")";

        // Filtro por tenant_id (se disponível)
        if ($tenantId) {
            $where[] = "(ce.tenant_id = ? OR ce.tenant_id IS NULL)";
            $params[] = $tenantId;
        }

        if ($afterTimestamp) {
            // Filtro incremental: created_at > timestamp OU (created_at = timestamp E event_id > after_event_id)
            $where[] = "(ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))";
            $params[] = $afterTimestamp;
            $params[] = $afterTimestamp;
            $params[] = $afterEventId ?? '';
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        // Busca eventos incrementais filtrados (limitado para não sobrecarregar)
        // [LOG TEMPORARIO] Query incremental
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesIncremental() - EXECUTANDO QUERY: contact=' . $normalizedContactExternalId . ', tenant_id=' . ($tenantId ?: 'NULL') . ', after_timestamp=' . ($afterTimestamp ?: 'NULL'));
        
        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                ce.event_type,
                ce.created_at,
                ce.payload,
                ce.metadata,
                ce.tenant_id
            FROM communication_events ce
            {$whereClause}
            ORDER BY ce.created_at ASC, ce.event_id ASC
            LIMIT 100
        ");
        $stmt->execute($params);
        $filteredEvents = $stmt->fetchAll();
        
        // [LOG TEMPORARIO] Resultado da query incremental
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesIncremental() - QUERY RETORNOU: events_count=' . count($filteredEvents));

        // Validação final em PHP (garantir que mensagem pertence à conversa)
        // A query SQL já filtra a maioria, mas validação final garante precisão
        $messages = [];
        $excludedCount = 0;
        foreach ($filteredEvents as $event) {
            $payload = json_decode($event['payload'], true);
            $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
            $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
            
            $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
            $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
            
            $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContactExternalId;
            $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContactExternalId;
            
            if (!$isFromThisContact && !$isToThisContact) {
                $excludedCount++;
                continue;
            }
            
            if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
                $excludedCount++;
                // [LOG TEMPORARIO] Mensagem excluída por tenant_id
                error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesIncremental() - MENSAGEM EXCLUIDA: event_id=' . $event['event_id'] . ', motivo=tenant_id_mismatch (event_tenant=' . $event['tenant_id'] . ', conv_tenant=' . $tenantId . ')');
                continue;
            }
            
            $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body'] 
                ?? '';
            
            // Processa mídia base64 se ainda não foi processada (áudio OGG, imagens JPEG/PNG)
            // Isso garante que mídias em base64 sejam processadas e salvas em communication_media
            try {
                \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao processar mídia do evento: " . $e->getMessage());
            }
            
            // Busca informações da mídia processada (sempre verifica, mesmo se há conteúdo)
            // Isso permite detectar mídias que foram processadas de base64 no campo text
            $mediaInfo = null;
            try {
                $mediaInfo = \PixelHub\Services\WhatsAppMediaService::getMediaByEventId($event['event_id']);
                
                // Se encontrou mídia processada, limpa o conteúdo para não mostrar base64 ou dados brutos
                if ($mediaInfo && !empty($content)) {
                    // Verifica se o conteúdo parece ser base64 (áudio ou imagem codificada)
                    if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                        // Tenta decodificar para verificar se é base64 válido
                        $textCleaned = preg_replace('/\s+/', '', $content);
                        $decoded = base64_decode($textCleaned, true);
                        if ($decoded !== false) {
                            // Verifica se é áudio OGG, imagem JPEG ou PNG
                            $isOgg = substr($decoded, 0, 4) === 'OggS';
                            $isJpeg = substr($textCleaned, 0, 4) === '/9j/';
                            $isPng = substr($textCleaned, 0, 12) === 'iVBORw0KGgo';
                            
                            if ($isOgg || $isJpeg || $isPng || strlen($decoded) > 1000) {
                                // É mídia em base64, limpa o conteúdo
                                $content = '';
                            }
                        }
                    } else {
                        // Se o conteúdo é muito longo e há mídia processada, provavelmente é dados brutos
                        // Limpa para não poluir a interface
                        if (strlen($content) > 500) {
                            $content = '';
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao buscar mídia: " . $e->getMessage());
            }
            
            // Se não encontrou mídia e não há conteúdo, mostra tipo de mídia
            if (empty($content) && !$mediaInfo) {
                if (isset($payload['type']) || isset($payload['message']['type'])) {
                    $mediaType = $payload['type'] ?? $payload['message']['type'] ?? 'media';
                    $content = "[{$mediaType}]";
                }
            }
            
            // Sanitiza mensagens muito longas sem quebra
            $content = self::sanitizeLongMessage($content);
            
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => json_decode($event['metadata'] ?? '{}', true),
                'media' => $mediaInfo // Informações da mídia (se houver)
            ];
        }

        // [LOG TEMPORARIO] Resultado final incremental
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesIncremental() - RESULTADO FINAL: messages_count=' . count($messages) . ', excluded_count=' . $excludedCount);

        return $messages;
    }

    /**
     * Sanitiza mensagens muito longas sem quebra (ex: base64, tokens)
     * 
     * Se a mensagem for suspeita (muito longa, sem espaços), retorna preview truncado
     * 
     * @param string $content Conteúdo original
     * @return string Conteúdo sanitizado
     */
    private static function sanitizeLongMessage(string $content): string
    {
        // Se mensagem é muito longa (mais de 500 chars) e não tem espaços/quebras
        if (strlen($content) > 500) {
            $hasSpaces = strpos($content, ' ') !== false;
            $hasNewlines = strpos($content, "\n") !== false;
            $hasTabs = strpos($content, "\t") !== false;
            
            // Se não tem espaços/quebras, é suspeita (pode ser base64/token)
            if (!$hasSpaces && !$hasNewlines && !$hasTabs) {
                // Trunca para 500 chars e adiciona indicador
                $truncated = substr($content, 0, 500);
                return $truncated . "\n\n[... conteúdo truncado - mensagem muito longa sem quebras ...]";
            }
        }
        
        return $content;
    }

    /**
     * Cria tenant a partir de um incoming lead
     * 
     * POST /communication-hub/incoming-lead/create-tenant
     */
    public function createTenantFromIncomingLead(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;
        $name = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');

        if ($conversationId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id é obrigatório'], 400);
            return;
        }

        if (empty($name)) {
            $this->json(['success' => false, 'error' => 'Nome é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            $db->beginTransaction();

            // Busca a conversa
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if (!$conversation) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            if (!$conversation['is_incoming_lead']) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Esta conversa não é um incoming lead'], 400);
                return;
            }

            // Cria o tenant
            $stmt = $db->prepare("
                INSERT INTO tenants 
                (name, phone, email, person_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'pf', 'active', NOW(), NOW())
            ");
            $stmt->execute([
                $name,
                $phone ?: $conversation['contact_external_id'],
                $email ?: null
            ]);

            $tenantId = (int) $db->lastInsertId();

            // Atualiza a conversa vinculando ao tenant
            $updateStmt = $db->prepare("
                UPDATE conversations 
                SET tenant_id = ?,
                    is_incoming_lead = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$tenantId, $conversationId]);

            $db->commit();

            $this->json([
                'success' => true,
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'message' => 'Cliente criado e conversa vinculada com sucesso'
            ]);

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[CommunicationHub] Erro ao criar tenant: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Vincula incoming lead a um tenant existente
     * 
     * POST /communication-hub/incoming-lead/link-tenant
     */
    public function linkIncomingLeadToTenant(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;
        $tenantId = isset($input['tenant_id']) ? (int) $input['tenant_id'] : 0;

        if ($conversationId <= 0 || $tenantId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id e tenant_id são obrigatórios'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            $db->beginTransaction();

            // Verifica se a conversa existe e é incoming lead
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if (!$conversation) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            if (!$conversation['is_incoming_lead']) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Esta conversa não é um incoming lead'], 400);
                return;
            }

            // Verifica se o tenant existe
            $tenantStmt = $db->prepare("SELECT id, name FROM tenants WHERE id = ?");
            $tenantStmt->execute([$tenantId]);
            $tenant = $tenantStmt->fetch();

            if (!$tenant) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Cliente não encontrado'], 404);
                return;
            }

            // Atualiza a conversa vinculando ao tenant
            $updateStmt = $db->prepare("
                UPDATE conversations 
                SET tenant_id = ?,
                    is_incoming_lead = 0,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$tenantId, $conversationId]);

            $db->commit();

            $this->json([
                'success' => true,
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant['name'],
                'conversation_id' => $conversationId,
                'message' => 'Conversa vinculada ao cliente com sucesso'
            ]);

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[CommunicationHub] Erro ao vincular tenant: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Rejeita/ignora um incoming lead
     * 
     * POST /communication-hub/incoming-lead/reject
     */
    public function rejectIncomingLead(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;

        if ($conversationId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            // Marca como rejeitado (arquiva ou fecha)
            $stmt = $db->prepare("
                UPDATE conversations 
                SET status = 'archived',
                    is_incoming_lead = 0,
                    updated_at = NOW()
                WHERE id = ? AND is_incoming_lead = 1
            ");
            $stmt->execute([$conversationId]);

            if ($stmt->rowCount() === 0) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada ou não é um incoming lead'], 404);
                return;
            }

            $this->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'message' => 'Incoming lead rejeitado e arquivado'
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao rejeitar incoming lead: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Serve mídia armazenada de forma segura
     * 
     * GET /communication-hub/media?path=whatsapp-media/...
     */
    public function serveMedia(): void
    {
        Auth::requireInternal();
        
        $path = $_GET['path'] ?? null;
        
        if (empty($path)) {
            http_response_code(400);
            echo "Caminho da mídia não fornecido";
            exit;
        }
        
        // Sanitiza path (previne path traversal)
        $path = ltrim($path, '/');
        $pathParts = explode('/', $path);
        
        // Garante que começa com whatsapp-media
        if ($pathParts[0] !== 'whatsapp-media') {
            http_response_code(403);
            echo "Caminho inválido";
            exit;
        }
        
        // Monta caminho absoluto
        $absolutePath = __DIR__ . '/../../storage/' . $path;
        
        // Verifica se arquivo existe
        if (!file_exists($absolutePath)) {
            http_response_code(404);
            echo "Mídia não encontrada";
            exit;
        }
        
        // Busca informações da mídia no banco (opcional, para validação)
        try {
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT cm.*, ce.tenant_id 
                FROM communication_media cm
                INNER JOIN communication_events ce ON cm.event_id = ce.event_id
                WHERE cm.stored_path = ?
                LIMIT 1
            ");
            $stmt->execute([$path]);
            $media = $stmt->fetch();
            
            // Determina Content-Type
            $contentType = $media['mime_type'] ?? 'application/octet-stream';
            $fileName = $media['file_name'] ?? basename($path);
        } catch (\Exception $e) {
            // Se não conseguir buscar no banco, tenta adivinhar MIME type
            $contentType = mime_content_type($absolutePath) ?: 'application/octet-stream';
            $fileName = basename($path);
        }
        
        // Envia arquivo
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: inline; filename="' . htmlspecialchars($fileName) . '"');
        header('Cache-Control: private, max-age=31536000'); // Cache por 1 ano
        
        readfile($absolutePath);
        exit;
    }
}

