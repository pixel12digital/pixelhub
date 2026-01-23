<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\ContactHelper;
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
        // PATCH E: Log de entrada no método index
        error_log('[CommunicationHub::index] INICIO');
        
        try {
            error_log('[CommunicationHub::index] ANTES Auth::requireInternal()');
            Auth::requireInternal();
            error_log('[CommunicationHub::index] DEPOIS Auth::requireInternal()');

            error_log('[CommunicationHub::index] ANTES DB::getConnection()');
            $db = DB::getConnection();
            error_log('[CommunicationHub::index] DEPOIS DB::getConnection()');

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
        // CORREÇÃO: Conta incoming leads diretamente do banco para evitar discrepância
        // causada pelo LIMIT 100 na query
        $incomingLeadsCount = 0;
        try {
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM conversations c
                WHERE c.channel_type = 'whatsapp'
                  AND c.is_incoming_lead = 1
                  AND (c.status IS NULL OR c.status NOT IN ('closed', 'archived', 'ignored'))
            ");
            $countStmt->execute();
            $countResult = $countStmt->fetch();
            $incomingLeadsCount = (int) ($countResult['total'] ?? 0);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao contar incoming leads: " . $e->getMessage());
            // Fallback para contagem do array
            $incomingLeadsCount = count($incomingLeads);
        }
        
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
            'incoming_leads_count' => $incomingLeadsCount
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
            
            // Limpa output buffer
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            
            // PATCH E: Detectar modo local
            $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
            
            http_response_code(500);
            
            // Sempre mostra o erro em desenvolvimento ou se APP_DEBUG estiver ativo
            $displayErrors = ini_get('display_errors');
            $isDebug = Env::get('APP_DEBUG', '0') === '1' || Env::get('APP_ENV', 'production') === 'dev';
            
            if ($displayErrors == '1' || $displayErrors == 'On' || $isDebug || $isLocal) {
                echo "<h1>Erro interno do servidor</h1>";
                echo "<h2>Classe:</h2>";
                echo "<pre>" . htmlspecialchars(get_class($e)) . "</pre>";
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
            
            exit;
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

        error_log("[CommunicationHub::getThreadData] Iniciado - thread_id={$threadId}, channel={$channel}");

        if (empty($threadId)) {
            error_log("[CommunicationHub::getThreadData] ERRO: thread_id vazio");
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            if ($channel === 'whatsapp') {
                // Busca mensagens WhatsApp via eventos
                error_log("[CommunicationHub::getThreadData] Buscando mensagens WhatsApp para thread_id={$threadId}");
                $messages = $this->getWhatsAppMessages($db, $threadId);
                error_log("[CommunicationHub::getThreadData] Mensagens encontradas: " . count($messages));
                
                error_log("[CommunicationHub::getThreadData] Buscando thread info para thread_id={$threadId}");
                $thread = $this->getWhatsAppThreadInfo($db, $threadId);
                
                if ($thread) {
                    error_log("[CommunicationHub::getThreadData] Thread encontrado - conversation_id={$thread['conversation_id']}, tenant_id=" . ($thread['tenant_id'] ?? 'NULL') . ", contact={$thread['contact']}");
                } else {
                    error_log("[CommunicationHub::getThreadData] AVISO: Thread NÃO encontrado para thread_id={$threadId}");
                }
                
                // Marca conversa como lida ao abrir (mark as read)
                if ($thread && isset($thread['conversation_id'])) {
                    $this->markConversationAsRead($db, (int) $thread['conversation_id']);
                }
            } else {
                // Busca mensagens de chat interno
                error_log("[CommunicationHub::getThreadData] Buscando mensagens de chat interno para thread_id={$threadId}");
                $messages = $this->getChatMessages($db, $threadId);
                $thread = $this->getChatThreadInfo($db, $threadId);
            }

            if (!$thread) {
                error_log("[CommunicationHub::getThreadData] ERRO: Thread não encontrado - thread_id={$threadId}");
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            error_log("[CommunicationHub::getThreadData] SUCESSO - Retornando dados da conversa");
            $this->json([
                'success' => true,
                'thread' => $thread,
                'messages' => $messages,
                'channel' => $channel
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub::getThreadData] EXCEÇÃO: " . $e->getMessage());
            error_log("[CommunicationHub::getThreadData] Stack trace: " . $e->getTraceAsString());
            $this->json(['success' => false, 'error' => 'Erro ao carregar conversa: ' . $e->getMessage()], 500);
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
        // PATCH E: Detectar modo local/dev ANTES de qualquer coisa
        $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        $stage = 'start';
        $exceptionDebug = null;
        
        // PATCH E: Envolver TUDO em try/catch desde o início
        try {
            // PATCH D: Marcar que Controller foi atingido
            if (!headers_sent()) {
                header('X-PixelHub-Stage: controller-send-start');
            }
            
            // PATCH E: Log entrada + POST resumido
            error_log('[CommunicationHub::send] INICIO (E)');
            error_log('[CommunicationHub::send] REMOTE_ADDR=' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            error_log('[CommunicationHub::send] POST=' . json_encode($_POST, JSON_UNESCAPED_UNICODE));
            
            // LOG DE DIAGNÓSTICO - INÍCIO (PATCH B)
            error_log('[CommunicationHub::send] INICIO');
            error_log('[CommunicationHub::send] POST: ' . json_encode($_POST, JSON_UNESCAPED_UNICODE));
            
            try {
                error_log('[CommunicationHub::send] HEADERS: ' . json_encode(getallheaders()));
            } catch (\Exception $e) {
                error_log('[CommunicationHub::send] ERRO ao pegar headers: ' . $e->getMessage());
            }
            
            error_log('[CommunicationHub::send] SESSION: ' . session_id());
            
            // PATCH E: Stage marker inicial
            error_log('[CommunicationHub::send] STAGE=' . $stage);
            
            // CRÍTICO: Define header ANTES de qualquer output ou verificação
            // Isso previne erros de "headers already sent"
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            
            // Limpa qualquer output buffer anterior
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            
            // Verifica autenticação (pode fazer exit se não autenticado)
            $stage = 'auth_check';
            error_log('[CommunicationHub::send] STAGE=' . $stage);
            Auth::requireInternal();

            $stage = 'validate_input';
            error_log('[CommunicationHub::send] STAGE=' . $stage);
            
            $channel = $_POST['channel'] ?? null;
            $threadId = $_POST['thread_id'] ?? null;
            $to = $_POST['to'] ?? null; // phone, email, etc
            $message = trim($_POST['message'] ?? '');
            $tenantIdFromPost = isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null;
            // CORRIGIDO: channel_id deve permanecer string (VARCHAR(100) no banco, string no gateway)
            $channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? trim($_POST['channel_id']) : null;
            // NOVO: Suporte para encaminhamento para múltiplos canais
            $forwardToAll = isset($_POST['forward_to_all']) && $_POST['forward_to_all'] === '1';
            $channelIdsArray = isset($_POST['channel_ids']) && is_array($_POST['channel_ids']) ? $_POST['channel_ids'] : null;
            // NOVO: Suporte para envio de áudio
            $messageType = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : 'text';
            $base64Ptt = isset($_POST['base64Ptt']) ? trim($_POST['base64Ptt']) : null;
            
            // PATCH I: Derivar tenant_id pela conversa (thread_id) e ignorar POST tenant_id
            // Regra de ouro: se thread_id existe, sempre usar tenant_id da conversa (fonte da verdade)
            $tenantId = $tenantIdFromPost; // Inicializa com valor do POST (fallback para casos sem thread_id)
            if (!empty($threadId) && preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                $conversationId = (int) $matches[1];
                try {
                    $db = DB::getConnection();
                    $convStmt = $db->prepare("SELECT tenant_id, channel_id, contact_external_id FROM conversations WHERE id = ? LIMIT 1");
                    $convStmt->execute([$conversationId]);
                    $conv = $convStmt->fetch();
                    
                    if ($conv && !empty($conv['tenant_id'])) {
                        // PATCH I: Sobrescreve tenant_id do POST com o valor real da conversa
                        $tenantId = (int) $conv['tenant_id'];
                        error_log("[CommunicationHub::send] PATCH I: tenant_id derivado da conversa: POST={$tenantIdFromPost} → DB={$tenantId} (conversation_id={$conversationId})");
                    } elseif ($conv && empty($conv['tenant_id']) && !empty($conv['channel_id'])) {
                        // Se conversa não tem tenant_id mas tem channel_id, tenta resolver pelo channel_id
                        $resolvedTenantId = $this->resolveTenantByChannelId($conv['channel_id'], $db);
                        if ($resolvedTenantId) {
                            // CORREÇÃO: Valida se o número do contato corresponde ao número do tenant
                            // antes de vincular automaticamente. Isso evita vincular conversas de números
                            // desconhecidos ao tenant do canal incorretamente.
                            $shouldLink = true;
                            if (!empty($conv['contact_external_id'])) {
                                $shouldLink = $this->validateContactBelongsToTenant(
                                    $conv['contact_external_id'],
                                    $resolvedTenantId,
                                    $db
                                );
                                if (!$shouldLink) {
                                    error_log("[CommunicationHub::send] AUTO-CURA CANCELADA: Número do contato ({$conv['contact_external_id']}) não corresponde ao tenant {$resolvedTenantId}. Mantendo como não vinculado.");
                                }
                            }
                            
                            if ($shouldLink) {
                                // Auto-cura: persiste tenant_id na conversa apenas se validação passar
                                $updateStmt = $db->prepare("UPDATE conversations SET tenant_id = ?, is_incoming_lead = 0 WHERE id = ?");
                                $updateStmt->execute([$resolvedTenantId, $conversationId]);
                                $tenantId = $resolvedTenantId;
                                error_log("[CommunicationHub::send] PATCH I + AUTO-CURA: tenant_id resolvido pelo channel_id: POST={$tenantIdFromPost} → DB={$tenantId} (conversation_id={$conversationId}, channel_id={$conv['channel_id']})");
                            }
                        } else {
                            error_log("[CommunicationHub::send] PATCH I: Não foi possível resolver tenant_id para conversation_id={$conversationId} (channel_id={$conv['channel_id']})");
                        }
                    } else {
                        error_log("[CommunicationHub::send] PATCH I: Conversa não encontrada (conversation_id={$conversationId}), usando tenant_id do POST={$tenantIdFromPost}");
                    }
                } catch (\Exception $e) {
                    error_log("[CommunicationHub::send] PATCH I: Erro ao buscar tenant_id da conversa: " . $e->getMessage() . " (usando tenant_id do POST={$tenantIdFromPost})");
                    // Em caso de erro, mantém tenant_id do POST como fallback
                }
            }

        // LOG INSTRUMENTADO (apenas em dev ou quando habilitado)
        $isDev = Env::get('APP_ENV', 'production') === 'dev' || Env::get('APP_DEBUG', '0') === '1';
        
        // LOG INICIAL SEMPRE (para debug de erros 500)
        error_log("[CommunicationHub::send] ===== INÍCIO MÉTODO =====");
        error_log("[CommunicationHub::send] channel: " . ($channel ?: 'NULL'));
        error_log("[CommunicationHub::send] message: " . (empty($message) ? 'VAZIO' : 'PRESENTE (len=' . strlen($message) . ')'));
        error_log("[CommunicationHub::send] to: " . ($to ?: 'NULL'));
        error_log("[CommunicationHub::send] threadId: " . ($threadId ?: 'NULL'));
        error_log("[CommunicationHub::send] channelId: " . ($channelId ?: 'NULL'));
        error_log("[CommunicationHub::send] tenantId: " . ($tenantId ?: 'NULL'));
        
        if ($isDev) {
            error_log("[CommunicationHub::send] ===== LOG INSTRUMENTADO (INÍCIO) =====");
            error_log("[CommunicationHub::send] thread_id: " . ($threadId ?: 'NULL'));
            error_log("[CommunicationHub::send] channel_id recebido do front: " . ($channelId ?: 'NULL'));
            error_log("[CommunicationHub::send] tenant_id recebido do front: " . ($_POST['tenant_id'] ?? 'NULL'));
            error_log("[CommunicationHub::send] channel: {$channel}, to: {$to}");
        }

        // Validação: para texto, message é obrigatório; para áudio, base64Ptt é obrigatório
        if (empty($channel)) {
            error_log("[CommunicationHub::send] ❌ ERRO 400: Canal vazio");
            $this->json(['success' => false, 'error' => 'Canal é obrigatório'], 400);
            return;
        }
        if ($messageType !== 'audio' && empty($message)) {
            error_log("[CommunicationHub::send] ❌ ERRO 400: Mensagem vazia (para tipo texto)");
            $this->json(['success' => false, 'error' => 'Mensagem é obrigatória para tipo texto'], 400);
            return;
        }
        if ($messageType === 'audio' && (empty($base64Ptt) || !is_string($base64Ptt))) {
            error_log("[CommunicationHub::send] ❌ ERRO 400: base64Ptt é obrigatório para tipo áudio");
            $this->json(['success' => false, 'error' => 'base64Ptt é obrigatório para tipo áudio'], 400);
            return;
        }
            if ($channel === 'whatsapp') {
                if (empty($to)) {
                    error_log("[CommunicationHub::send] ❌ ERRO 400: Telefone vazio");
                    $this->json(['success' => false, 'error' => 'to (telefone) é obrigatório para WhatsApp'], 400);
                    return;
                }
                
                error_log("[CommunicationHub::send] ✅ Validações básicas passaram");
                
                $stage = 'resolve_tenant';
                error_log('[CommunicationHub::send] STAGE=' . $stage);
                
                try {
                    $db = DB::getConnection();
                    error_log("[CommunicationHub::send] ✅ Conexão DB obtida");
                } catch (\Exception $e) {
                    error_log("[CommunicationHub::send] ❌ ERRO ao obter conexão DB: " . $e->getMessage());
                    throw $e;
                }
                
                // CRÍTICO: Inicializa targetChannels no início para evitar erros
                $targetChannels = [];
                error_log("[CommunicationHub::send] ✅ targetChannels inicializado como array vazio");
                
                $stage = 'resolve_thread';
                error_log('[CommunicationHub::send] STAGE=' . $stage);
                
                // PATCH I: tenant_id já foi derivado da conversa no início (linha ~369)
                // Agora apenas busca channel_id da conversa se thread_id existe
                // CORREÇÃO CRÍTICA: Sempre usa thread.channel_id como fonte da verdade
                // Ignora channel_id do frontend quando thread_id está presente
                if (!empty($threadId) && preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                    $conversationId = (int) $matches[1];
                    error_log("[CommunicationHub::send] ✅ threadId válido detectado, conversationId={$conversationId}");
                    
                    try {
                        $convStmt = $db->prepare("SELECT tenant_id, channel_id, contact_external_id FROM conversations WHERE id = ?");
                        $convStmt->execute([$conversationId]);
                        $conv = $convStmt->fetch();
                        error_log("[CommunicationHub::send] ✅ Query executada, conv encontrada: " . ($conv ? 'SIM' : 'NÃO'));
                    } catch (\Exception $e) {
                        error_log("[CommunicationHub::send] ❌ ERRO ao buscar conversation: " . $e->getMessage());
                        throw $e;
                    }
                    
                    if ($conv) {
                        // PATCH I: tenant_id já foi resolvido no início do método
                        // Aqui apenas verifica consistência e faz auto-cura se necessário
                        if (empty($conv['tenant_id']) && !empty($conv['channel_id']) && $tenantId) {
                            // Auto-cura: persiste tenant_id na conversa se ainda não tem
                            $updateStmt = $db->prepare("UPDATE conversations SET tenant_id = ? WHERE id = ?");
                            $updateStmt->execute([$tenantId, $conversationId]);
                            error_log("[CommunicationHub::send] AUTO-CURA: Persistido tenant_id={$tenantId} na conversa (conversation_id={$conversationId})");
                        } elseif ($conv['tenant_id'] && $conv['tenant_id'] != $tenantId) {
                            // Se há divergência, usa o valor do banco (fonte da verdade)
                            $tenantId = (int) $conv['tenant_id'];
                            error_log("[CommunicationHub::send] PATCH I: Corrigido tenant_id divergente → {$tenantId} (conversation_id={$conversationId})");
                        }
                        
                        // CORREÇÃO: SEMPRE usa channel_id da thread (ignora channel_id do frontend)
                        // Se thread não tem channel_id, retorna erro explícito
                        if (empty($conv['channel_id'])) {
                            error_log("[CommunicationHub::send] ERRO: Thread conversation_id={$conversationId} não possui channel_id. Não é possível enviar.");
                            $this->json([
                                'success' => false, 
                                'error' => 'THREAD_MISSING_CHANNEL_ID',
                                'error_code' => 'THREAD_MISSING_CHANNEL_ID',
                                'message' => 'A conversa não possui canal associado. Verifique se a mensagem foi recebida corretamente.'
                            ], 400);
                            return;
                        }
                        
                        // Usa channel_id da thread como sessionId do gateway (fonte da verdade)
                        $sessionId = trim($conv['channel_id']);
                        error_log("[CommunicationHub::send] Usando sessionId da thread (fonte da verdade): {$sessionId} (ignorando channel_id do frontend se fornecido)");
                        
                        $stage = 'resolve_channel';
                        error_log('[CommunicationHub::send] STAGE=' . $stage);
                        
                        // PATCH H2: Valida sessionId do gateway (não depende de display_name)
                        // Interpreta channel_id da thread como sessionId do gateway
                        $validatedChannel = $this->validateGatewaySessionId($sessionId, $tenantId, $db);
                        
                        if ($validatedChannel) {
                            // Usa o sessionId CANÔNICO validado
                            $foundSessionId = trim($validatedChannel['session_id']);
                            $targetChannels = [$foundSessionId];
                            error_log("[CommunicationHub::send] ✅ SessionId do gateway validado e adicionado ao targetChannels: {$foundSessionId}");
                            error_log("[CommunicationHub::send] ✅ targetChannels após validação: " . json_encode($targetChannels));
                        } else {
                            error_log("[CommunicationHub::send] ❌ ERRO: SessionId '{$sessionId}' do gateway não encontrado ou não habilitado para este tenant");
                            $this->json([
                                'success' => false, 
                                'error' => "SessionId do gateway '{$sessionId}' não está habilitado para este tenant. Verifique se a sessão está cadastrada e habilitada.",
                                'error_code' => 'CHANNEL_NOT_FOUND'
                            ], 400);
                            return;
                        }
                    } else {
                        error_log("[CommunicationHub::send] ERRO: Thread conversation_id={$conversationId} não encontrada.");
                        $this->json([
                            'success' => false, 
                            'error' => 'THREAD_NOT_FOUND',
                            'error_code' => 'THREAD_NOT_FOUND'
                        ], 404);
                        return;
                    }
                }
                
                // NOVO: Determina lista de canais para envio
                // targetChannels já foi inicializado no início, mas pode ter sido definido no bloco do threadId acima
                // Se ainda estiver vazio, precisa buscar canais
                
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
                        // PATCH H2: Interpreta channelId recebido como sessionId do gateway
                        // Valida usando a nova função que detecta schema automaticamente
                        $validatedChannel = $this->validateGatewaySessionId($channelId, $tenantId, $db);
                        
                        if (!$validatedChannel) {
                            error_log("[CommunicationHub::send] ERRO: SessionId '{$channelId}' do gateway não encontrado ou não habilitado para este tenant");
                            $this->json([
                                'success' => false, 
                                'error' => "SessionId do gateway '{$channelId}' não está habilitado para este tenant. Verifique se a sessão está cadastrada e habilitada.",
                                'error_code' => 'CHANNEL_NOT_FOUND'
                            ], 400);
                            return;
                        }
                        
                        // CRÍTICO: Usa o sessionId CANÔNICO validado (valor original do gateway)
                        // Este é o valor que será enviado ao gateway
                        $foundSessionId = trim($validatedChannel['session_id']);
                        $targetChannels = [$foundSessionId];
                        
                        // LOG DE DIAGNÓSTICO: Informações do canal
                        // PATCH F+G: Secret e baseUrl sempre vêm do serviço/env, não do banco
                        error_log(sprintf(
                            "[CommunicationHub::send] SessionId validado: solicitado='%s' → canônico='%s' (será usado no gateway)",
                            $channelId,
                            $foundSessionId
                        ));
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
                            
                            // PRIORIDADE 2.1: Usa o channel_id da própria conversa como sessionId (mais confiável)
                            if (!empty($conv['channel_id'])) {
                                $sessionIdFromConv = trim($conv['channel_id']);
                                error_log("[CommunicationHub::send] SessionId encontrado na conversa: {$sessionIdFromConv}");
                                
                                // PATCH H2: Valida sessionId usando função que detecta schema
                                $validatedChannel = $this->validateGatewaySessionId($sessionIdFromConv, $tenantId, $db);
                                
                                if ($validatedChannel) {
                                    $targetChannels = [trim($validatedChannel['session_id'])];
                                    error_log("[CommunicationHub::send] SessionId da conversa validado: {$validatedChannel['session_id']}");
                                } else {
                                    error_log("[CommunicationHub::send] AVISO: SessionId da conversa '{$sessionIdFromConv}' não está mais habilitado, tentando buscar de eventos...");
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
                                        $sessionIdFromEvent = trim((string) $payload['channel_id']);
                                        error_log("[CommunicationHub::send] SessionId encontrado nos eventos: {$sessionIdFromEvent}");
                                        
                                        // PATCH H2: Valida sessionId usando função que detecta schema
                                        $validatedChannel = $this->validateGatewaySessionId($sessionIdFromEvent, $tenantId, $db);
                                        
                                        if ($validatedChannel) {
                                            $targetChannels = [trim($validatedChannel['session_id'])];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                    // PRIORIDADE 3: Busca sessionId do tenant (se ainda não encontrou)
                    // PATCH H2: Usa coluna correta (session_id ou channel_id) conforme schema
                    if (!$channelId && $tenantId) {
                        $sessionIdColumn = $this->getSessionIdColumnName($db);
                        $channelStmt = $db->prepare("
                            SELECT {$sessionIdColumn} as session_id
                            FROM tenant_message_channels 
                            WHERE tenant_id = ? 
                            AND provider = 'wpp_gateway' 
                            AND is_enabled = 1
                            LIMIT 1
                        ");
                        $channelStmt->execute([$tenantId]);
                        $channelData = $channelStmt->fetch();

                        if ($channelData && !empty($channelData['session_id'])) {
                            $channelId = trim((string) $channelData['session_id']);
                            error_log("[CommunicationHub::send] SessionId encontrado do tenant: {$channelId}");
                        } else {
                            error_log("[CommunicationHub::send] Nenhum canal encontrado para tenant_id: {$tenantId}");
                        }
                    }
                    
                    // PRIORIDADE 4: Fallback: tenta usar canal compartilhado/default (qualquer canal habilitado)
                    // PATCH H2: Usa coluna correta (session_id ou channel_id) conforme schema
                    if (empty($targetChannels) && $channelId) {
                        // Valida o channelId encontrado usando a função
                        $validatedChannel = $this->validateGatewaySessionId($channelId, $tenantId, $db);
                        if ($validatedChannel) {
                            $targetChannels = [trim($validatedChannel['session_id'])];
                            error_log("[CommunicationHub::send] SessionId validado (fallback tenant): {$validatedChannel['session_id']}");
                        }
                    }
                    
                    // PRIORIDADE 5: Último fallback - qualquer canal habilitado do sistema
                    if (empty($targetChannels)) {
                        $sessionIdColumn = $this->getSessionIdColumnName($db);
                        $channelStmt = $db->prepare("
                            SELECT {$sessionIdColumn} as session_id
                            FROM tenant_message_channels 
                            WHERE provider = 'wpp_gateway' 
                            AND is_enabled = 1
                            LIMIT 1
                        ");
                        $channelStmt->execute();
                        $channelData = $channelStmt->fetch();

                        if ($channelData && !empty($channelData['session_id'])) {
                            $foundSessionId = trim((string) $channelData['session_id']);
                            $targetChannels = [$foundSessionId];
                            error_log("[CommunicationHub::send] SessionId encontrado (canal compartilhado/fallback): {$foundSessionId}");
                        } else {
                            error_log("[CommunicationHub::send] Nenhum canal WhatsApp habilitado encontrado no sistema");
                            $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp configurado no sistema'], 400);
                            return;
                        }
                    }
                }
                
                // Valida que temos pelo menos um canal
                error_log("[CommunicationHub::send] ===== VALIDAÇÃO FINAL =====");
                error_log("[CommunicationHub::send] targetChannels antes da validação final: " . json_encode($targetChannels));
                error_log("[CommunicationHub::send] targetChannels está vazio: " . (empty($targetChannels) ? 'SIM' : 'NÃO'));
                
                if (empty($targetChannels)) {
                    error_log("[CommunicationHub::send] ❌ ERRO: Nenhum canal identificado para envio");
                    $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp identificado para envio'], 400);
                    return;
                }
                
                error_log("[CommunicationHub::send] ✅ Canais alvo para envio: " . implode(', ', $targetChannels) . " (total: " . count($targetChannels) . ")");

                // Normaliza telefone
                $phoneNormalized = WhatsAppBillingService::normalizePhone($to);
                if (empty($phoneNormalized)) {
                    $this->json(['success' => false, 'error' => 'Telefone inválido'], 400);
                    return;
                }

                // CORREÇÃO: Obtém baseUrl e secret via env/config (mesmo padrão do módulo de teste)
                // PATCH F+G: Nunca buscar base_url ou gateway_secret do banco (colunas não existem)
                // Usa o mesmo padrão do WhatsAppGatewayTestController
                $baseUrl = Env::get('WPP_GATEWAY_BASE_URL', 'https://wpp.pixel12digital.com.br');
                $baseUrl = rtrim($baseUrl, '/');
                
                // PATCH F: Secret sempre obtido do serviço GatewaySecret (fonte única da verdade)
                $secret = GatewaySecret::getDecrypted();
                
                // LOG: configurações do gateway
                if ($isDev) {
                    $baseUrlHost = parse_url($baseUrl, PHP_URL_HOST) ?: 'NULL';
                    error_log("[CommunicationHub::send] gateway_base_url: {$baseUrl} (host: {$baseUrlHost})");
                    error_log("[CommunicationHub::send] gateway_secret: " . (!empty($secret) ? 'CONFIGURADO' : 'VAZIO'));
                }

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

                $stage = 'build_payload';
                error_log('[CommunicationHub::send] STAGE=' . $stage);
                
                // Cria gateway com configurações (específicas do canal ou globais)
                $gateway = new WhatsAppGatewayClient($baseUrl, $secret);
                
                // ===== LOG TEMPORÁRIO: Endpoint de verificação de status =====
                // Usa o primeiro canal de targetChannels para o log (garantido que não está vazio)
                $logChannelId = !empty($targetChannels) ? $targetChannels[0] : ($channelId ?: 'N/A');
                $statusEndpoint = "{$baseUrl}/api/channels/{$logChannelId}";
                error_log("[CommunicationHub::send] endpoint verificar status: {$statusEndpoint}");
                // ===== FIM LOG TEMPORÁRIO =====

                $stage = 'call_gateway';
                error_log('[CommunicationHub::send] STAGE=' . $stage);

                // NOVO: Itera sobre todos os canais e envia para cada um
                $sendResults = [];
                $hasAnySuccess = false;
                $errors = [];
                
                foreach ($targetChannels as $targetChannelId) {
                    // LOG DE DIAGNÓSTICO: Informações antes de enviar
                    error_log(sprintf(
                        "[CommunicationHub::send] ===== DIAGNÓSTICO ENVIO ====="
                    ));
                    error_log(sprintf(
                        "[CommunicationHub::send] thread_id=%s | channel_id=%s | tenant_id=%s | base_url_host=%s",
                        $threadId ?: 'NULL',
                        $targetChannelId,
                        $tenantId ?: 'NULL',
                        parse_url($baseUrl, PHP_URL_HOST) ?: 'NULL'
                    ));
                    
                    // Valida se a sessão está conectada antes de enviar (NÃO-BLOQUEANTE)
                    $channelInfo = $gateway->getChannel($targetChannelId);
                    
                    // 🔍 LOG DETALHADO: Estrutura completa da resposta do gateway
                    error_log("[CommunicationHub::send] ===== LOG DETALHADO STATUS CANAL =====");
                    error_log("[CommunicationHub::send] channel_id: {$targetChannelId}");
                    error_log("[CommunicationHub::send] channelInfo completo: " . json_encode($channelInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    error_log("[CommunicationHub::send] channelInfo['success']: " . ($channelInfo['success'] ?? 'NULL'));
                    error_log("[CommunicationHub::send] channelInfo['status'] (HTTP): " . ($channelInfo['status'] ?? 'NULL'));
                    error_log("[CommunicationHub::send] channelInfo['error']: " . ($channelInfo['error'] ?? 'NULL'));
                    error_log("[CommunicationHub::send] channelInfo['raw'] existe: " . (isset($channelInfo['raw']) ? 'SIM' : 'NÃO'));
                    
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
                        
                        // 🔍 LOG DETALHADO: Estrutura completa do channelData (raw)
                        error_log("[CommunicationHub::send] channelData (raw) completo: " . json_encode($channelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        error_log("[CommunicationHub::send] channelData keys: " . implode(', ', array_keys($channelData)));
                        
                        // Verifica TODOS os campos possíveis onde o status pode estar
                        $possibleStatusFields = [
                            'status' => $channelData['status'] ?? null,
                            'channel.status' => $channelData['channel']['status'] ?? null,
                            'channel.connection' => $channelData['channel']['connection'] ?? null,
                            'connection' => $channelData['connection'] ?? null,
                            'connected' => $channelData['connected'] ?? null,
                            'session.status' => $channelData['session']['status'] ?? null,
                            'session.connection' => $channelData['session']['connection'] ?? null,
                            'data.status' => $channelData['data']['status'] ?? null,
                            'data.connection' => $channelData['data']['connection'] ?? null,
                        ];
                        
                        error_log("[CommunicationHub::send] Campos de status possíveis:");
                        foreach ($possibleStatusFields as $field => $value) {
                            error_log("[CommunicationHub::send]   - {$field}: " . ($value !== null ? var_export($value, true) : 'NULL'));
                        }
                        
                        // Lógica corrigida: prioriza channel.status (estrutura real do gateway)
                        $sessionStatus = $channelData['channel']['status'] 
                            ?? $channelData['channel']['connection'] 
                            ?? $channelData['status'] 
                            ?? $channelData['connection'] 
                            ?? null;
                        $isConnected = ($sessionStatus === 'connected' || $sessionStatus === 'open' || $channelData['connected'] ?? false);
                        
                        // 🔍 LOG DETALHADO: Resultado da verificação
                        error_log("[CommunicationHub::send] sessionStatus extraído: " . ($sessionStatus ?? 'NULL'));
                        error_log("[CommunicationHub::send] channelData['connected'] (boolean): " . ($channelData['connected'] ?? 'NULL'));
                        error_log("[CommunicationHub::send] isConnected calculado: " . ($isConnected ? 'true' : 'false'));
                        
                        if (!$isConnected) {
                            $shouldBlockSend = true;
                            $blockReason = "Sessão desconectada";
                            error_log("[CommunicationHub::send] ⚠️ BLOQUEADO: Sessão não conectada - sessionStatus={$sessionStatus}, connected=" . ($channelData['connected'] ?? 'NULL'));
                        } else {
                            error_log("[CommunicationHub::send] ✅ Sessão conectada - permitindo envio");
                        }
                        
                        error_log("[CommunicationHub::send] ===== FIM LOG DETALHADO STATUS CANAL =====");
                    }
                    
                    if ($shouldBlockSend) {
                        error_log(sprintf(
                            "[CommunicationHub::send] Canal bloqueado: channel_id=%s | reason=%s | status_code=%s",
                            $targetChannelId,
                            $blockReason,
                            $statusCode
                        ));
                        $sendResults[] = [
                            'channel_id' => $targetChannelId,
                            'success' => false,
                            'error' => $blockReason,
                            'error_code' => $statusCode === 401 ? 'UNAUTHORIZED' : ($statusCode === 404 ? 'CHANNEL_NOT_FOUND' : 'SESSION_DISCONNECTED')
                        ];
                        $errors[] = "{$targetChannelId}: {$blockReason}";
                        continue;
                    }
                    
                    // Envia via gateway usando valor CANÔNICO (case-sensitive)
                    if ($isDev) {
                        error_log("[CommunicationHub::send] Enviando para gateway com sessionId (canônico): {$targetChannelId}, type: {$messageType}");
                    }
                    
                    // Switch entre texto e áudio
                    if ($messageType === 'audio') {
                        // Validação adicional para áudio
                        $b64 = $base64Ptt;
                        $pos = stripos($b64, 'base64,');
                        if ($pos !== false) {
                            $b64 = substr($b64, $pos + 7);
                        }
                        $b64 = trim($b64);
                        
                        // Validação mínima (evita "T2dnUw==" sozinho)
                        $bin = base64_decode($b64, true);
                        if ($bin === false || strlen($bin) < 2000) {
                            $sendResults[] = [
                                'channel_id' => $targetChannelId,
                                'success' => false,
                                'error' => 'Invalid or too small audio payload (need real OGG/Opus, not only header)',
                            ];
                            $errors[] = "{$targetChannelId}: Invalid or too small audio payload";
                            continue;
                        }
                        
                        // Valida se parece Opus (costuma existir cedo no arquivo)
                        if (strpos($bin, 'OpusHead') === false) {
                            $sendResults[] = [
                                'channel_id' => $targetChannelId,
                                'success' => false,
                                'error' => 'Audio must be OGG/Opus (OpusHead not found)',
                            ];
                            $errors[] = "{$targetChannelId}: Audio must be OGG/Opus";
                            continue;
                        }
                        
                        $result = $gateway->sendAudioBase64Ptt(
                            $targetChannelId,
                            $phoneNormalized,
                            $b64,
                            [
                                'sent_by' => Auth::user()['id'] ?? null,
                                'sent_by_name' => Auth::user()['name'] ?? null
                            ]
                        );
                    } else {
                        // Texto como já é hoje
                        $result = $gateway->sendText($targetChannelId, $phoneNormalized, $message, [
                            'sent_by' => Auth::user()['id'] ?? null,
                            'sent_by_name' => Auth::user()['name'] ?? null
                        ]);
                    }
                    
                    // LOG DE DIAGNÓSTICO: Resposta do gateway
                    $gatewayErrorCode = $result['error_code'] ?? null;
                    $gatewayStatus = $result['status'] ?? null;
                    error_log(sprintf(
                        "[CommunicationHub::send] Resposta gateway: channel_id=%s | success=%s | error_code=%s | status=%s",
                        $targetChannelId,
                        $result['success'] ? 'true' : 'false',
                        $gatewayErrorCode ?: 'NULL',
                        $gatewayStatus ?: 'NULL'
                    ));
                    
                    if ($isDev && !$result['success']) {
                        error_log("[CommunicationHub::send] Resposta do gateway (detalhada): " . json_encode([
                            'success' => $result['success'] ?? false,
                            'error' => $result['error'] ?? null,
                            'error_code' => $gatewayErrorCode,
                            'status' => $gatewayStatus
                        ]));
                    }
                    
                    if ($result['success']) {
                        if ($isDev) {
                            error_log("[CommunicationHub::send] ✅ Sucesso ao enviar para {$targetChannelId}");
                        }
                        $hasAnySuccess = true;
                        
                        $stage = 'persist_message';
                        error_log('[CommunicationHub::send] STAGE=' . $stage);
                        
                        // Cria evento de envio para este canal
                        $eventPayload = [
                            'to' => $phoneNormalized,
                            'timestamp' => time(),
                            'channel_id' => $targetChannelId,
                            'type' => $messageType
                        ];
                        
                        if ($messageType === 'audio') {
                            $eventPayload['message'] = [
                                'to' => $phoneNormalized,
                                'type' => 'audio',
                                'timestamp' => time()
                            ];
                            // Não inclui base64Ptt no payload do evento (muito grande)
                            // Pode ser salvo separadamente se necessário
                        } else {
                            $eventPayload['message'] = [
                                'to' => $phoneNormalized,
                                'text' => $message,
                                'timestamp' => time()
                            ];
                            $eventPayload['text'] = $message;
                        }
                        
                        $eventId = EventIngestionService::ingest([
                            'event_type' => 'whatsapp.outbound.message',
                            'source_system' => 'pixelhub_operator',
                            'payload' => $eventPayload,
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
                        $errorCode = $result['error_code'] ?? null;
                        $gatewayStatus = $result['status'] ?? null;
                        
                        // Detecta erros específicos e melhora mensagens
                        $errorLower = strtolower($error);
                        
                        // Detecta SESSION_DISCONNECTED do gateway
                        if (strpos($errorLower, 'session') !== false && strpos($errorLower, 'disconnect') !== false) {
                            $errorCode = 'SESSION_DISCONNECTED';
                            $error = 'Sessão do WhatsApp desconectada. Verifique se a sessão está conectada no gateway.';
                        } 
                        // Detecta erros específicos do WPPConnect para áudio
                        elseif ($messageType === 'audio' && (stripos($error, 'WPPConnect') !== false || stripos($error, 'sendVoiceBase64') !== false)) {
                            $errorCode = $errorCode ?: 'WPPCONNECT_SEND_ERROR';
                            // Melhora mensagem se for erro genérico
                            if (stripos($error, 'Erro ao enviar a mensagem') !== false) {
                                $error = 'Falha ao enviar áudio via WPPConnect. Possíveis causas: sessão desconectada, formato de áudio inválido, ou tamanho muito grande. Verifique os logs do gateway para mais detalhes.';
                            }
                        }
                        // Detecta áudio muito grande
                        elseif (stripos($error, 'AUDIO_TOO_LARGE') !== false || stripos($error, 'muito grande') !== false) {
                            $errorCode = 'AUDIO_TOO_LARGE';
                        }
                        // Detecta outros erros do gateway
                        elseif ($gatewayStatus === 409 || $gatewayStatus === 400) {
                            $errorCode = $errorCode ?: 'GATEWAY_ERROR';
                        }
                        
                        error_log("[CommunicationHub::send] ❌ Erro ao enviar para {$targetChannelId}: {$error} (code: {$errorCode}, status: {$gatewayStatus})");
                        
                        // Log detalhado para áudio
                        if ($messageType === 'audio' && $isDev) {
                            error_log("[CommunicationHub::send] Detalhes do erro de áudio: " . json_encode([
                                'error' => $error,
                                'error_code' => $errorCode,
                                'status' => $gatewayStatus,
                                'channel_id' => $targetChannelId,
                                'to' => $phoneNormalized,
                                'base64_length' => isset($b64) ? strlen($b64) : 'N/A'
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        
                        $sendResults[] = [
                            'channel_id' => $targetChannelId,
                            'success' => false,
                            'error' => $error,
                            'error_code' => $errorCode ?: ($result['error_code'] ?? 'GATEWAY_ERROR')
                        ];
                        $errors[] = "{$targetChannelId}: {$error}";
                    }
                }
                
                $stage = 'return_success';
                error_log('[CommunicationHub::send] STAGE=' . $stage);
                
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
                        // Retorna código HTTP apropriado baseado no error_code
                        $httpCode = 500;
                        if ($singleResult['error_code'] === 'SESSION_DISCONNECTED') {
                            $httpCode = 409; // Conflict
                        } elseif ($singleResult['error_code'] === 'UNAUTHORIZED' || $singleResult['error_code'] === 'CHANNEL_NOT_FOUND') {
                            $httpCode = 400; // Bad Request
                        }
                        
                        $this->json([
                            'success' => false,
                            'error' => $singleResult['error'],
                            'error_code' => $singleResult['error_code'],
                            'channel_id' => $singleResult['channel_id']
                        ], $httpCode);
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
            
        } catch (\Throwable $e) {
            // PATCH D: Marcar exceção no Controller
            if (!headers_sent()) {
                header('X-PixelHub-Stage: controller-exception');
            }
            
            // PATCH E: Log exceção real
            error_log('[CommunicationHub::send] EXCEPTION (E): ' . get_class($e) . ' ' . $e->getMessage());
            error_log('[CommunicationHub::send] FILE: ' . $e->getFile() . ':' . $e->getLine());
            error_log('[CommunicationHub::send] TRACE: ' . $e->getTraceAsString());
            error_log('[CommunicationHub::send] STAGE=' . ($stage ?? 'unknown'));
            
            // Limpa output buffer
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
            
            // PATCH E: Retorna JSON com debug detalhado apenas em modo local
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            } else {
                @http_response_code(500);
            }
            
            // Prepara debug apenas se local
            if ($isLocal) {
                $exceptionDebug = [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                    'stage' => $stage ?? 'unknown'
                ];
            }
            
            $response = [
                'success' => false,
                'error' => 'Erro interno do servidor',
                'error_code' => 'CONTROLLER_EXCEPTION',
                'debug' => $exceptionDebug
            ];
            
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    /**
     * Resolve channel_id NULL consultando múltiplas fontes
     * 
     * Estratégias (em ordem de prioridade):
     * 1. Busca em eventos recentes da mesma conversa (communication_events)
     * 2. Busca em outras conversas com mesmo contact_external_id
     * 3. Busca em tenant_message_channels baseado no tenant_id
     * 
     * @param PDO $db Conexão com banco
     * @param array $conversations Array de conversas que precisam de channel_id (indexado por índice)
     * @return array Array indexado por índice da conversa com channel_id resolvido (ou null se não encontrado)
     */
    private function resolveMissingChannelIds(PDO $db, array $conversations): array
    {
        $resolved = [];
        
        if (empty($conversations)) {
            return $resolved;
        }
        
        // Estratégia 1: Busca em eventos recentes da mesma conversa
        $conversationIds = array_filter(array_column($conversations, 'id'));
        if (!empty($conversationIds)) {
            try {
                // Verifica se tabela communication_events existe
                $checkStmt = $db->query("SHOW TABLES LIKE 'communication_events'");
                if ($checkStmt->rowCount() > 0) {
                    $placeholders = str_repeat('?,', count($conversationIds) - 1) . '?';
                    
                    // Busca channel_id mais recente de eventos inbound para essas conversas
                    // Primeiro busca por conversation_id no metadata
                    $stmt = $db->prepare("
                        SELECT 
                            ce.metadata,
                            ce.payload
                        FROM communication_events ce
                        WHERE ce.event_type = 'whatsapp.inbound.message'
                        AND JSON_EXTRACT(ce.metadata, '$.conversation_id') IN ($placeholders)
                        ORDER BY ce.created_at DESC
                        LIMIT 100
                    ");
                    
                    $stmt->execute($conversationIds);
                    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Processa eventos e extrai channel_id
                    foreach ($events as $event) {
                        $metadata = json_decode($event['metadata'] ?? '{}', true);
                        $payload = json_decode($event['payload'] ?? '{}', true);
                        
                        $conversationId = $metadata['conversation_id'] ?? null;
                        
                        // Tenta extrair channel_id de múltiplas fontes (mesma lógica do ConversationService)
                        $channelId = $payload['sessionId'] 
                            ?? $payload['session']['id'] 
                            ?? $payload['session']['session'] 
                            ?? $payload['data']['session']['id'] 
                            ?? $payload['data']['session']['session'] 
                            ?? $payload['channelId'] 
                            ?? $payload['channel'] 
                            ?? $payload['data']['channel'] 
                            ?? $metadata['channel_id'] 
                            ?? null;
                        
                        if ($channelId && $conversationId) {
                            // Encontra índice da conversa
                            foreach ($conversations as $idx => $conv) {
                                if ($conv['id'] == $conversationId && empty($resolved[$idx])) {
                                    $resolved[$idx] = trim((string)$channelId);
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Se ainda há conversas sem channel_id, tenta buscar por contact_external_id
                    $remainingIndices = [];
                    foreach ($conversations as $idx => $conv) {
                        if (empty($resolved[$idx]) && !empty($conv['contact_external_id'])) {
                            $remainingIndices[$idx] = $conv['contact_external_id'];
                        }
                    }
                    
                    if (!empty($remainingIndices)) {
                        // Busca eventos por contact_external_id
                        $contactPlaceholders = str_repeat('?,', count($remainingIndices) - 1) . '?';
                        $stmt2 = $db->prepare("
                            SELECT 
                                ce.metadata,
                                ce.payload,
                                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) as from_field,
                                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) as message_from
                            FROM communication_events ce
                            WHERE ce.event_type = 'whatsapp.inbound.message'
                            AND (
                                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) IN ($contactPlaceholders)
                                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) IN ($contactPlaceholders)
                            )
                            ORDER BY ce.created_at DESC
                            LIMIT 100
                        ");
                        
                        $contactParams = array_merge(array_values($remainingIndices), array_values($remainingIndices));
                        $stmt2->execute($contactParams);
                        $events2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Processa eventos e mapeia por contact_external_id
                        $contactToChannelMap = [];
                        foreach ($events2 as $event2) {
                            $payload2 = json_decode($event2['payload'] ?? '{}', true);
                            $from = $event2['from_field'] ?? $event2['message_from'] ?? null;
                            
                            if ($from) {
                                $channelId2 = $payload2['sessionId'] 
                                    ?? $payload2['session']['id'] 
                                    ?? $payload2['session']['session'] 
                                    ?? $payload2['data']['session']['id'] 
                                    ?? $payload2['data']['session']['session'] 
                                    ?? $payload2['channelId'] 
                                    ?? $payload2['channel'] 
                                    ?? $payload2['data']['channel'] 
                                    ?? null;
                                
                                if ($channelId2 && !isset($contactToChannelMap[$from])) {
                                    $contactToChannelMap[$from] = trim((string)$channelId2);
                                }
                            }
                        }
                        
                        // Aplica aos índices restantes
                        foreach ($remainingIndices as $idx => $contactId) {
                            if (empty($resolved[$idx]) && isset($contactToChannelMap[$contactId])) {
                                $resolved[$idx] = $contactToChannelMap[$contactId];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao resolver channel_id de eventos: " . $e->getMessage());
            }
        }
        
        // Estratégia 2: Busca em outras conversas com mesmo contact_external_id
        $contactIds = [];
        foreach ($conversations as $idx => $conv) {
            if (empty($resolved[$idx]) && !empty($conv['contact_external_id'])) {
                $contactIds[$idx] = $conv['contact_external_id'];
            }
        }
        
        if (!empty($contactIds)) {
            try {
                $uniqueContactIds = array_unique(array_values($contactIds));
                $placeholders = str_repeat('?,', count($uniqueContactIds) - 1) . '?';
                
                $stmt = $db->prepare("
                    SELECT DISTINCT contact_external_id, channel_id
                    FROM conversations
                    WHERE contact_external_id IN ($placeholders)
                    AND channel_id IS NOT NULL
                    AND channel_id != ''
                    ORDER BY last_message_at DESC
                ");
                $stmt->execute(array_values($uniqueContactIds));
                $matchingConvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Cria mapa de contact_external_id -> channel_id
                $contactToChannelMap = [];
                foreach ($matchingConvs as $match) {
                    $contactId = $match['contact_external_id'];
                    $channelId = $match['channel_id'];
                    if (!isset($contactToChannelMap[$contactId])) {
                        $contactToChannelMap[$contactId] = $channelId;
                    }
                }
                
                // Aplica aos índices que ainda não foram resolvidos
                foreach ($contactIds as $idx => $contactId) {
                    if (empty($resolved[$idx]) && isset($contactToChannelMap[$contactId])) {
                        $resolved[$idx] = $contactToChannelMap[$contactId];
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao resolver channel_id de outras conversas: " . $e->getMessage());
            }
        }
        
        // Estratégia 3: Busca em tenant_message_channels baseado no tenant_id
        $tenantIds = [];
        foreach ($conversations as $idx => $conv) {
            if (empty($resolved[$idx]) && !empty($conv['tenant_id'])) {
                $tenantIds[$idx] = $conv['tenant_id'];
            }
        }
        
        if (!empty($tenantIds)) {
            try {
                $uniqueTenantIds = array_unique(array_values($tenantIds));
                $placeholders = str_repeat('?,', count($uniqueTenantIds) - 1) . '?';
                
                // Busca primeiro canal habilitado de cada tenant
                $stmt = $db->prepare("
                    SELECT tenant_id, channel_id
                    FROM tenant_message_channels
                    WHERE tenant_id IN ($placeholders)
                    AND provider = 'wpp_gateway'
                    AND is_enabled = 1
                    AND channel_id IS NOT NULL
                    AND channel_id != ''
                    GROUP BY tenant_id
                    ORDER BY id ASC
                ");
                $stmt->execute(array_values($uniqueTenantIds));
                $tenantChannels = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Cria mapa de tenant_id -> channel_id
                $tenantToChannelMap = [];
                foreach ($tenantChannels as $tc) {
                    $tenantId = $tc['tenant_id'];
                    $channelId = $tc['channel_id'];
                    if (!isset($tenantToChannelMap[$tenantId])) {
                        $tenantToChannelMap[$tenantId] = $channelId;
                    }
                }
                
                // Aplica aos índices que ainda não foram resolvidos
                foreach ($tenantIds as $idx => $tenantId) {
                    if (empty($resolved[$idx]) && isset($tenantToChannelMap[$tenantId])) {
                        $resolved[$idx] = $tenantToChannelMap[$tenantId];
                    }
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao resolver channel_id de tenant_message_channels: " . $e->getMessage());
            }
        }
        
        return $resolved;
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
        // IMPORTANTE: Exclui conversas com status='ignored' da lista de ativas
        if ($status === 'active') {
            $where[] = "c.status NOT IN ('closed', 'archived', 'ignored')";
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
                    c.channel_id,
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
                    t.phone as tenant_phone,
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

        // OTIMIZAÇÃO: Resolve todos os @lid em lote ANTES do loop (evita N+1 queries)
        $lidPhoneMap = [];
        $lidBatchData = [];
        
        // Coleta todos os @lid que precisam ser resolvidos (com sufixo ou digits-only)
        foreach ($conversations as $conv) {
            if (empty($conv['tenant_phone']) && !empty($conv['contact_external_id'])) {
                $contactId = (string) $conv['contact_external_id'];
                
                // Detecta se é @lid (com sufixo ou digits-only)
                $isLid = false;
                if (strpos($contactId, '@lid') !== false) {
                    $isLid = true;
                } else {
                    // Tenta detectar como pnlid digits-only (14-20 dígitos, não começa com 55)
                    $digits = preg_replace('/[^0-9]/', '', $contactId);
                    if ($digits === $contactId && strlen($digits) >= 14 && strlen($digits) <= 20) {
                        // Se começa com 55 e tem 12-13 dígitos, é E.164 brasileiro, não pnlid
                        if (!(strlen($digits) <= 13 && substr($digits, 0, 2) === '55')) {
                            $isLid = true;
                            
                            // LOG TEMPORÁRIO: Detectou pnlid sem @lid
                            error_log(sprintf(
                                '[LID_DETECT] conversation_id=%d, contact_external_id=%s, detected_as_lid=YES (digits-only, len=%d)',
                                $conv['id'] ?? 0,
                                $contactId,
                                strlen($digits)
                            ));
                        }
                    }
                }
                
                if ($isLid) {
                    $lidBatchData[] = [
                        'contactId' => $contactId,
                        'sessionId' => !empty($conv['channel_id']) ? (string) $conv['channel_id'] : null,
                    ];
                }
            }
        }
        
        // Resolve todos em lote (máximo 2 queries independente do número de conversas)
        if (!empty($lidBatchData)) {
            try {
                $lidPhoneMap = ContactHelper::resolveLidPhonesBatch($lidBatchData, 'wpp_gateway');
            } catch (\Exception $e) {
                error_log("Erro ao resolver @lid em lote: " . $e->getMessage());
                $lidPhoneMap = [];
            }
        }

        // CORREÇÃO: Resolve channel_id NULL consultando eventos recentes ou tenant_message_channels
        // Coleta conversas que precisam de resolução de channel_id
        $conversationsNeedingChannelId = [];
        foreach ($conversations as $idx => $conv) {
            if (empty($conv['channel_id'])) {
                $conversationsNeedingChannelId[$idx] = $conv;
            }
        }
        
        // Resolve channel_id em lote para otimização
        $resolvedChannelIds = [];
        if (!empty($conversationsNeedingChannelId)) {
            $resolvedChannelIds = $this->resolveMissingChannelIds($db, $conversationsNeedingChannelId);
        }

        // Formata para o formato esperado pela UI
        $threads = [];
        foreach ($conversations as $idx => $conv) {
                    // CORREÇÃO: Usa channel_id resolvido se o original estava NULL
                    $resolvedChannelId = $resolvedChannelIds[$idx] ?? null;
                    $finalChannelId = !empty($conv['channel_id']) ? $conv['channel_id'] : $resolvedChannelId;
                    
                    // Busca número real do tenant se houver @lid e tenant vinculado
                    $realPhone = null;
                    if (!empty($conv['tenant_id']) && !empty($conv['tenant_phone'])) {
                        // Prioridade 1: tenant.phone
                        $realPhone = $conv['tenant_phone'];
                    } elseif (!empty($conv['contact_external_id'])) {
                        $contactId = (string) $conv['contact_external_id'];
                        
                        // Detecta lidId (com sufixo ou digits-only)
                        $lidId = null;
                        if (strpos($contactId, '@lid') !== false) {
                            $lidId = str_replace('@lid', '', $contactId);
                        } else {
                            // Tenta detectar como pnlid digits-only
                            $digits = preg_replace('/[^0-9]/', '', $contactId);
                            if ($digits === $contactId && strlen($digits) >= 14 && strlen($digits) <= 20) {
                                if (!(strlen($digits) <= 13 && substr($digits, 0, 2) === '55')) {
                                    $lidId = $digits;
                                }
                            }
                        }
                        
                        if ($lidId) {
                            // Prioridade 2: buscar no mapa pré-carregado (O(1))
                            $realPhone = $lidPhoneMap[$lidId] ?? null;
                            
                            // LOG TEMPORÁRIO: Resultado da resolução
                            if ($realPhone) {
                                error_log(sprintf(
                                    '[LID_RESOLVE] conversation_id=%d, contact_external_id=%s, lidId=%s, resolved_phone=%s',
                                    $conv['id'] ?? 0,
                                    $contactId,
                                    $lidId,
                                    $realPhone
                                ));
                            } else {
                                error_log(sprintf(
                                    '[LID_RESOLVE] conversation_id=%d, contact_external_id=%s, lidId=%s, resolved_phone=NULL',
                                    $conv['id'] ?? 0,
                                    $contactId,
                                    $lidId
                                ));
                            }
                        }
                    }
                    
                    $threads[] = [
                        'thread_id' => "whatsapp_{$conv['id']}",
                        'conversation_id' => $conv['id'],
                        'conversation_key' => $conv['conversation_key'],
                        'tenant_id' => $conv['tenant_id'] ?: null,
                        'tenant_name' => $conv['tenant_name'] ?: 'Sem tenant',
                        'contact' => ContactHelper::formatContactId($conv['contact_external_id'], $realPhone),
                        'contact_name' => $conv['contact_name'],
                        'last_activity' => $conv['last_message_at'] ?: $conv['created_at'],
                        'message_count' => (int) $conv['message_count'],
                        'inbound_count' => $conv['last_message_direction'] === 'inbound' ? 1 : 0, // Aproximação
                        'channel' => 'whatsapp',
                        'channel_type' => $conv['channel_type'], // Adiciona contexto
                        'channel_id' => $finalChannelId, // CORREÇÃO: Usa channel_id resolvido se necessário
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
                    t.name as tenant_name,
                    t.phone as tenant_phone
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
                // Busca número real do tenant se disponível
                $realPhone = $event['tenant_phone'] ?? null;
                if (empty($realPhone) && strpos($from, '@lid') !== false) {
                    // Tenta resolver @lid via cache e mapeamento usando ContactHelper
                    $resolvedPhone = ContactHelper::resolveLidPhone(
                        $from,
                        $event['session_id'] ?? $event['channel_id'] ?? null,
                        'wpp_gateway'
                    );
                    if (!empty($resolvedPhone)) {
                        $realPhone = $resolvedPhone;
                    }
                }
                
                $threadsMap[$threadKey] = [
                    'thread_id' => "whatsapp_{$eventTenantId}_{$from}",
                    'tenant_id' => $eventTenantId ?: null,
                    'tenant_name' => $event['tenant_name'],
                    'contact' => ContactHelper::formatContactId($from, $realPhone),
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
            $normalizePhoneE164, $extractPnLid
        ) {
            if (empty($jidOrNumber)) return null;
            $jidOrNumber = (string)$jidOrNumber;
            
            // Se NÃO é @lid, normaliza como telefone normal
            $pnLid = $extractPnLid($jidOrNumber);
            if (!$pnLid) {
                $normalized = $normalizePhoneE164($jidOrNumber);
                return $normalized;
            }
            
            // É @lid -> usa ContactHelper que já tem toda a lógica integrada (cache + eventos + provider)
            $resolved = \PixelHub\Core\ContactHelper::resolveLidPhone($jidOrNumber, $sessionId, $provider);
            if (!empty($resolved)) {
                return $resolved;
            }
            
            // Não conseguiu resolver: retorna null para evitar falso-match
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

        // CORREÇÃO: Se normalizedContactExternalId está vazio mas temos @lid, ainda podemos buscar
        // Isso permite encontrar eventos mesmo quando o número não pode ser normalizado
        if (empty($normalizedContactExternalId) && strpos($contactExternalId, '@lid') === false) {
            error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - ERRO: normalizedContactExternalId está vazio e não é @lid');
            return []; // Não pode buscar sem contato válido
        }

        // CORREÇÃO: Filtra no SQL ao invés de buscar todos os eventos
        // Usa LIKE para pegar variações do telefone (com @c.us, @lid, etc)
        $where = [
            "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
        ];
        $params = [];

        // CORREÇÃO: Filtro mais robusto que pega variações do telefone
        // Usa múltiplos padrões para pegar: número puro, com @c.us, com 9º dígito, etc
        $contactPatterns = [];
        
        // IMPORTANTE: Se contact_external_id tem @lid, adiciona padrão COM @lid primeiro
        // Isso garante que eventos com @lid sejam encontrados mesmo quando o número normalizado não bate
        if (strpos($contactExternalId, '@lid') !== false) {
            // Adiciona padrão com @lid (ex: "56083800395891@lid")
            $contactPatterns[] = "%{$contactExternalId}%";
            error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - ADICIONADO PADRAO COM @lid: ' . $contactExternalId);
        }
        
        // Sempre adiciona número normalizado (pode ser usado como fallback)
        if ($normalizedContactExternalId) {
            $contactPatterns[] = "%{$normalizedContactExternalId}%";
        }
        
        // Se for número BR (começa com 55), adiciona variação com/sem 9º dígito
        if ($normalizedContactExternalId && strlen($normalizedContactExternalId) >= 12 && substr($normalizedContactExternalId, 0, 2) === '55') {
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
        // CORREÇÃO: Para @lid, também busca em author/participant de grupos
        $isLidContact = strpos($contactExternalId, '@lid') !== false;
        
        $contactConditions = [];
        foreach ($contactPatterns as $pattern) {
            $condition = "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
            )";
            
            // Para @lid, também busca em author/participant de grupos
            if ($isLidContact) {
                $condition = "(
                    JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.from')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.from')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.to')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.to')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.author')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.participant')) LIKE ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.key.participant')) LIKE ?
                )";
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern; // author
                $params[] = $pattern; // participant
                $params[] = $pattern; // message.key.participant
            } else {
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern;
                $params[] = $pattern;
            }
            
            $contactConditions[] = $condition;
        }
        $where[] = "(" . implode(" OR ", $contactConditions) . ")";

        // PATCH K: Filtro estrito por tenant_id (após PATCH J, todos eventos têm tenant_id)
        // CORREÇÃO: Torna filtro mais flexível - se tenant_id não retornar resultados e houver channel_id,
        // tenta buscar sem filtro de tenant_id (channel_id já garante isolamento)
        // IMPORTANTE: Se tenant_id é NULL na conversa, não filtra por tenant_id (permite encontrar eventos com qualquer tenant_id)
        $whereWithTenant = $where;
        $paramsWithTenant = $params;
        
        if ($tenantId) {
            $whereWithTenant[] = "ce.tenant_id = ?";
            $paramsWithTenant[] = $tenantId;
        }
        
        // PATCH K: Filtro adicional por channel_id para garantir isolamento por sessão
        // CORREÇÃO: Usa comparação case-insensitive para channel_id (resolve problema "imobsites" vs "ImobSites")
        if (!empty($sessionId)) {
            $whereWithTenant[] = "(
                LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = LOWER(TRIM(REPLACE(?, ' ', '')))
                OR JSON_EXTRACT(ce.payload, '$.session.id') = ?
                OR JSON_EXTRACT(ce.payload, '$.sessionId') = ?
                OR JSON_EXTRACT(ce.payload, '$.channelId') = ?
            )";
            $paramsWithTenant[] = $sessionId;
            $paramsWithTenant[] = $sessionId;
            $paramsWithTenant[] = $sessionId;
            $paramsWithTenant[] = $sessionId;
        }

        $whereClause = "WHERE " . implode(" AND ", $whereWithTenant);

        // Busca eventos filtrados (limitado para performance)
        // [LOG TEMPORARIO] Query do thread
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - EXECUTANDO QUERY: conversation_id=' . $conversationId . ', contact=' . $normalizedContactExternalId . ', tenant_id=' . ($tenantId ?: 'NULL') . ', channel_id=' . ($sessionId ?: 'NULL'));
        
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
        $stmt->execute($paramsWithTenant);
        $filteredEvents = $stmt->fetchAll();
        
        // [LOG TEMPORARIO] Resultado da query
        error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - QUERY RETORNOU: events_count=' . count($filteredEvents));
        
        // CORREÇÃO: Se não encontrou eventos, tenta buscar sem filtro de tenant_id
        // Isso resolve casos onde:
        // 1. A conversa tem tenant_id NULL (não vinculada) mas os eventos têm tenant_id
        // 2. A conversa tem tenant_id incorreto mas os eventos têm tenant_id diferente
        // IMPORTANTE: Para conversas não vinculadas (tenant_id NULL), já não filtra por tenant_id na primeira query,
        // mas se ainda assim não encontra, tenta buscar apenas por contato (sem filtros de tenant_id ou channel_id)
        if (empty($filteredEvents)) {
            error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - NENHUM EVENTO ENCONTRADO, TENTANDO BUSCA MAIS AMPLA: tenant_id=' . ($tenantId ?: 'NULL') . ', channel_id=' . ($sessionId ?: 'NULL'));
            
            // Reconstrói WHERE apenas com filtros de contato (sem tenant_id e sem channel_id)
            // Isso permite encontrar mensagens mesmo quando não há vínculo de tenant ou channel
            $whereWithoutFilters = $where;
            $paramsWithoutFilters = $params;
            
            $whereClauseWithoutFilters = "WHERE " . implode(" AND ", $whereWithoutFilters);
            
            $stmt = $db->prepare("
                SELECT 
                    ce.event_id,
                    ce.event_type,
                    ce.created_at,
                    ce.payload,
                    ce.metadata,
                    ce.tenant_id
                FROM communication_events ce
                {$whereClauseWithoutFilters}
                ORDER BY ce.created_at ASC
                LIMIT 500
            ");
            $stmt->execute($paramsWithoutFilters);
            $filteredEvents = $stmt->fetchAll();
            
            error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - QUERY SEM FILTROS DE TENANT/CHANNEL RETORNOU: events_count=' . count($filteredEvents));
        }

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
            // CORREÇÃO: Se houver channel_id, permite eventos com tenant_id diferente
            // (channel_id já garante isolamento, e isso resolve casos onde a conversa tem tenant_id incorreto)
            if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
                // Se não há channel_id ou o channel_id não garante isolamento, rejeita
                // Se há channel_id, aceita mesmo com tenant_id diferente (pode ser erro de mapeamento)
                if (empty($sessionId)) {
                    error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - EVENTO REJEITADO POR TENANT_ID: event_id=' . ($event['event_id'] ?? 'N/A') . ', event_tenant_id=' . $event['tenant_id'] . ', conversation_tenant_id=' . $tenantId . ' (sem channel_id para isolamento)');
                    continue;
                } else {
                    error_log('[LOG TEMPORARIO] CommunicationHub::getWhatsAppMessagesFromConversation() - EVENTO ACEITO COM TENANT_ID DIFERENTE: event_id=' . ($event['event_id'] ?? 'N/A') . ', event_tenant_id=' . $event['tenant_id'] . ', conversation_tenant_id=' . $tenantId . ' (channel_id=' . $sessionId . ' garante isolamento)');
                }
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
                    t.phone as tenant_phone,
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
                // CORREÇÃO CRÍTICA: PRIORIDADE 1 - Usa channel_id da tabela conversations (fonte da verdade)
                // Este valor foi persistido corretamente durante o recebimento usando extractChannelIdFromPayload()
                // que prioriza sessionId e rejeita valores incorretos como "ImobSites"
                $channelId = null;
                if (!empty($conversation['channel_id'])) {
                    $channelId = trim((string) $conversation['channel_id']);
                    error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 1: Usando channel_id da tabela conversations: {$channelId}");
                }
                
                // PRIORIDADE 2: Se não tem na tabela, busca dos eventos usando mesma lógica do extractChannelIdFromPayload()
                // Prioriza sessionId (sessão real do gateway) e nunca usa metadata.channel_id primeiro
                if (!$channelId) {
                    $contactId = $conversation['contact_external_id'];
                    if ($contactId) {
                        // Busca mensagem recebida (inbound) mais recente - prioridade máxima
                        $eventStmt = $db->prepare("
                            SELECT ce.payload, ce.metadata
                            FROM communication_events ce
                            WHERE ce.event_type = 'whatsapp.inbound.message'
                            AND (
                                JSON_EXTRACT(ce.payload, '$.from') = ?
                                OR JSON_EXTRACT(ce.payload, '$.message.from') = ?
                            )
                            ORDER BY ce.created_at DESC
                            LIMIT 1
                        ");
                        $eventStmt->execute([$contactId, $contactId]);
                        $event = $eventStmt->fetch();
                        
                        if ($event && $event['payload']) {
                            $payload = json_decode($event['payload'], true);
                            $metadata = $event['metadata'] ? json_decode($event['metadata'], true) : null;
                            
                            // Usa mesma lógica do ConversationService::extractChannelIdFromPayload()
                            // PRIORIDADE: sessionId primeiro (sessão real do gateway)
                            if (isset($payload['sessionId'])) {
                                $channelId = trim((string) $payload['sessionId']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.1: sessionId encontrado (payload.sessionId): {$channelId}");
                            } elseif (isset($payload['session']['id'])) {
                                $channelId = trim((string) $payload['session']['id']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.2: sessionId encontrado (payload.session.id): {$channelId}");
                            } elseif (isset($payload['session']['session'])) {
                                $channelId = trim((string) $payload['session']['session']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.3: sessionId encontrado (payload.session.session): {$channelId}");
                            } elseif (isset($payload['data']['session']['id'])) {
                                $channelId = trim((string) $payload['data']['session']['id']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.4: sessionId encontrado (payload.data.session.id): {$channelId}");
                            } elseif (isset($payload['data']['session']['session'])) {
                                $channelId = trim((string) $payload['data']['session']['session']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.5: sessionId encontrado (payload.data.session.session): {$channelId}");
                            } elseif (isset($payload['metadata']['sessionId'])) {
                                $channelId = trim((string) $payload['metadata']['sessionId']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.6: sessionId encontrado (payload.metadata.sessionId): {$channelId}");
                            } elseif (isset($payload['channelId'])) {
                                $channelId = trim((string) $payload['channelId']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.7: channelId encontrado (payload.channelId): {$channelId}");
                            } elseif (isset($payload['channel'])) {
                                $channelId = trim((string) $payload['channel']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.8: channel encontrado (payload.channel): {$channelId}");
                            } elseif (isset($payload['data']['channel'])) {
                                $channelId = trim((string) $payload['data']['channel']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.9: channel encontrado (payload.data.channel): {$channelId}");
                            } elseif ($metadata && isset($metadata['channel_id'])) {
                                // ÚLTIMA opção - pode estar errado (ex: ImobSites)
                                $channelId = trim((string) $metadata['channel_id']);
                                // VALIDAÇÃO: Rejeita valores conhecidos como incorretos
                                $channelIdLower = strtolower($channelId);
                                if ($channelIdLower === 'imobsites') {
                                    error_log("[CommunicationHub::getWhatsAppThreadInfo] AVISO: metadata.channel_id='ImobSites' rejeitado (valor incorreto). Tentando buscar de outra mensagem...");
                                    $channelId = null; // Rejeita valor incorreto
                                } else {
                                    error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.10: channel_id encontrado (metadata.channel_id): {$channelId}");
                                }
                            }
                        }
                        
                        // Se ainda não encontrou, tenta buscar de qualquer mensagem da conversa (inbound ou outbound)
                        if (!$channelId) {
                            $eventStmt2 = $db->prepare("
                                SELECT ce.payload, ce.metadata
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
                            $eventStmt2->execute([$contactId, $contactId, $contactId, $contactId]);
                            $event2 = $eventStmt2->fetch();
                            
                            if ($event2 && $event2['payload']) {
                                $payload2 = json_decode($event2['payload'], true);
                                $metadata2 = $event2['metadata'] ? json_decode($event2['metadata'], true) : null;
                                
                                // Mesma lógica de prioridade
                                if (isset($payload2['sessionId'])) {
                                    $channelId = trim((string) $payload2['sessionId']);
                                } elseif (isset($payload2['session']['id'])) {
                                    $channelId = trim((string) $payload2['session']['id']);
                                } elseif (isset($payload2['session']['session'])) {
                                    $channelId = trim((string) $payload2['session']['session']);
                                } elseif (isset($payload2['data']['session']['id'])) {
                                    $channelId = trim((string) $payload2['data']['session']['id']);
                                } elseif (isset($payload2['data']['session']['session'])) {
                                    $channelId = trim((string) $payload2['data']['session']['session']);
                                } elseif (isset($payload2['metadata']['sessionId'])) {
                                    $channelId = trim((string) $payload2['metadata']['sessionId']);
                                } elseif (isset($payload2['channelId'])) {
                                    $channelId = trim((string) $payload2['channelId']);
                                } elseif (isset($payload2['channel'])) {
                                    $channelId = trim((string) $payload2['channel']);
                                } elseif (isset($payload2['data']['channel'])) {
                                    $channelId = trim((string) $payload2['data']['channel']);
                                } elseif ($metadata2 && isset($metadata2['channel_id'])) {
                                    $channelId = trim((string) $metadata2['channel_id']);
                                    // Validação: rejeita valores incorretos
                                    if (strtolower($channelId) === 'imobsites') {
                                        $channelId = null;
                                    }
                                }
                                
                                if ($channelId) {
                                    error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2: Channel_id encontrado em qualquer mensagem: {$channelId}");
                                }
                            }
                        }
                    }
                }
                
                // PRIORIDADE 3: Usa tenant_channel_id se disponível (canal configurado para o tenant)
                if (!$channelId && isset($conversation['tenant_channel_id']) && $conversation['tenant_channel_id'] !== '') {
                    $channelId = trim((string) $conversation['tenant_channel_id']);
                    error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 3: Usando tenant_channel_id como fallback: {$channelId}");
                }
                
                // PRIORIDADE 4: Se ainda não tem channel_id, tenta buscar qualquer canal habilitado (último recurso)
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
                        $channelId = trim((string) $fallback['channel_id']);
                        error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 4: Usando canal fallback genérico: {$channelId}");
                    }
                }
                
                error_log("[CommunicationHub::getWhatsAppThreadInfo] Thread {$threadId}: channel_id={$channelId}, tenant_id={$conversation['tenant_id']}, contact={$contactId}");
                
                // Busca número real do tenant se houver @lid e tenant vinculado
                $realPhone = null;
                if (!empty($conversation['tenant_id']) && !empty($conversation['tenant_phone'])) {
                    $realPhone = $conversation['tenant_phone'];
                } elseif (strpos($conversation['contact_external_id'] ?? '', '@lid') !== false) {
                    // Tenta resolver @lid via cache e mapeamento usando ContactHelper
                    $resolvedPhone = ContactHelper::resolveLidPhone(
                        $conversation['contact_external_id'],
                        $conversation['channel_id'] ?? null,
                        'wpp_gateway'
                    );
                    if (!empty($resolvedPhone)) {
                        $realPhone = $resolvedPhone;
                        
                        // IMPORTANTE: Se encontrou o número, garante que está salvo no mapeamento
                        // para que apareça também na listagem de conversas
                        try {
                            $lidId = str_replace('@lid', '', $conversation['contact_external_id']);
                            $lidBusinessId = $lidId . '@lid';
                            
                            $checkStmt = $db->prepare("SELECT phone_number FROM whatsapp_business_ids WHERE business_id = ? LIMIT 1");
                            $checkStmt->execute([$lidBusinessId]);
                            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (!$existing || empty($existing['phone_number'])) {
                                // Cria mapeamento se não existir
                                $insertStmt = $db->prepare("
                                    INSERT IGNORE INTO whatsapp_business_ids (business_id, phone_number, tenant_id)
                                    VALUES (?, ?, ?)
                                ");
                                $insertStmt->execute([
                                    $lidBusinessId,
                                    $resolvedPhone,
                                    $conversation['tenant_id'] ?: null
                                ]);
                                
                                // Também salva no cache se tiver channel_id
                                if (!empty($conversation['channel_id'])) {
                                    $cacheStmt = $db->prepare("
                                        INSERT INTO wa_pnlid_cache (provider, session_id, pnlid, phone_e164)
                                        VALUES (?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE phone_e164=VALUES(phone_e164), updated_at=NOW()
                                    ");
                                    $cacheStmt->execute(['wpp_gateway', $conversation['channel_id'], $lidId, $resolvedPhone]);
                                }
                            }
                        } catch (\Exception $e) {
                            // Ignora erro, mas loga para debug
                            error_log("Erro ao salvar mapeamento LID ao abrir conversa: " . $e->getMessage());
                        }
                    }
                }
                
                return [
                    'thread_id' => $threadId,
                    'conversation_id' => $conversationId,
                    'conversation_key' => $conversation['conversation_key'],
                    'tenant_id' => $conversation['tenant_id'],
                    'tenant_name' => $conversation['tenant_name'],
                    'contact' => ContactHelper::formatContactId($conversation['contact_external_id'], $realPhone),
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
                // Busca número real do tenant se disponível
                $realPhone = $tenant['phone'] ?? null;
                if (empty($realPhone) && strpos($from, '@lid') !== false) {
                    // Tenta resolver @lid via cache e mapeamento usando ContactHelper
                    $resolvedPhone = ContactHelper::resolveLidPhone(
                        $from,
                        $tenant['channel_id'] ?? null,
                        'wpp_gateway'
                    );
                    if (!empty($resolvedPhone)) {
                        $realPhone = $resolvedPhone;
                    }
                }
                
                return [
                    'thread_id' => $threadId,
                    'tenant_id' => $tenantId,
                    'tenant_name' => $tenant['name'],
                    'contact' => ContactHelper::formatContactId($from, $realPhone),
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
            
            // Separa incoming leads das conversas normais
            $incomingLeads = [];
            $normalThreads = [];
            
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
                
                // Separa incoming leads das conversas normais
                foreach ($allThreads as $thread) {
                    if (!empty($thread['is_incoming_lead'])) {
                        $incomingLeads[] = $thread;
                    } else {
                        $normalThreads[] = $thread;
                    }
                }
            }

            // [LOG TEMPORARIO] Resultado final
            error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - RETORNO FINAL: threads_count=' . count($normalThreads ?? []) . ', incoming_leads_count=' . count($incomingLeads ?? []));
            if (!empty($normalThreads)) {
                $firstFinal = $normalThreads[0] ?? null;
                $secondFinal = $normalThreads[1] ?? null;
                error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - PRIMEIRO: thread_id=' . ($firstFinal['thread_id'] ?? 'N/A') . ', last_activity=' . ($firstFinal['last_activity'] ?? 'N/A') . ', unread_count=' . ($firstFinal['unread_count'] ?? 0));
                if ($secondFinal) {
                    error_log('[LOG TEMPORARIO] CommunicationHub::getConversationsList() - SEGUNDO: thread_id=' . ($secondFinal['thread_id'] ?? 'N/A') . ', last_activity=' . ($secondFinal['last_activity'] ?? 'N/A') . ', unread_count=' . ($secondFinal['unread_count'] ?? 0));
                }
            }

            // CORREÇÃO: Conta incoming leads diretamente do banco para evitar discrepância
            // causada pelo LIMIT 100 na query
            $incomingLeadsCount = 0;
            try {
                $countStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM conversations c
                    WHERE c.channel_type = 'whatsapp'
                      AND c.is_incoming_lead = 1
                      AND (c.status IS NULL OR c.status NOT IN ('closed', 'archived', 'ignored'))
                ");
                $countStmt->execute();
                $countResult = $countStmt->fetch();
                $incomingLeadsCount = (int) ($countResult['total'] ?? 0);
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Erro ao contar incoming leads na API: " . $e->getMessage());
                // Fallback para contagem do array
                $incomingLeadsCount = count($incomingLeads ?? []);
            }
            
            $this->json([
                'success' => true,
                'threads' => $normalThreads ?? [],
                'incoming_leads' => $incomingLeads ?? [],
                'incoming_leads_count' => $incomingLeadsCount
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

            // IMPORTANTE: Exclui conversas com status='ignored' da lista de ativas
            if ($status === 'active') {
                $where[] = "c.status NOT IN ('closed', 'archived', 'ignored')";
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

            // CORREÇÃO: Atualiza a conversa vinculando ao tenant
            // E também atualiza todas as conversas duplicadas (mesmo remote_key)
            // Isso previne que conversas duplicadas fiquem com tenants diferentes
            $remoteKey = $conversation['remote_key'] ?? null;
            
            if ($remoteKey) {
                // Atualiza todas as conversas com mesmo remote_key
                $updateStmt = $db->prepare("
                    UPDATE conversations 
                    SET tenant_id = ?,
                        is_incoming_lead = 0,
                        updated_at = NOW()
                    WHERE remote_key = ? 
                    AND channel_type = ?
                ");
                $updateStmt->execute([$tenantId, $remoteKey, $conversation['channel_type']]);
                $duplicatesUpdated = $updateStmt->rowCount();
                
                if ($duplicatesUpdated > 1) {
                    error_log("[CommunicationHub] linkIncomingLeadToTenant: Atualizadas {$duplicatesUpdated} conversas duplicadas com remote_key={$remoteKey}");
                }
            } else {
                // Fallback: atualiza apenas a conversa específica se não tiver remote_key
                $updateStmt = $db->prepare("
                    UPDATE conversations 
                    SET tenant_id = ?,
                        is_incoming_lead = 0,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$tenantId, $conversationId]);
            }

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
     * Altera o tenant vinculado a uma conversa
     * 
     * POST /communication-hub/conversation/change-tenant
     */
    public function changeConversationTenant(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

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

            // Verifica se a conversa existe
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if (!$conversation) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
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

            // Atualiza a conversa vinculando ao novo tenant
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
                'message' => 'Cliente vinculado à conversa alterado com sucesso'
            ]);

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[CommunicationHub] Erro ao alterar tenant: " . $e->getMessage());
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
            // Marca como ignorado (status='ignored')
            // Reseta ignored_unread_count para 0 no momento do ignore
            $stmt = $db->prepare("
                UPDATE conversations 
                SET status = 'ignored',
                    is_incoming_lead = 0,
                    ignored_unread_count = 0,
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
                'message' => 'Conversa ignorada'
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
            // CORREÇÃO: Tenta reprocessar a mídia se o arquivo não existe
            // Isso resolve casos onde o registro existe mas o arquivo foi perdido
            try {
                $db = DB::getConnection();
                $stmt = $db->prepare("
                    SELECT ce.* 
                    FROM communication_media cm
                    INNER JOIN communication_events ce ON cm.event_id = ce.event_id
                    WHERE cm.stored_path = ?
                    LIMIT 1
                ");
                $stmt->execute([$path]);
                $event = $stmt->fetch();
                
                if ($event) {
                    error_log("[CommunicationHub::serveMedia] Arquivo não encontrado, tentando reprocessar: {$path}");
                    \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
                    
                    // Verifica novamente se o arquivo foi criado
                    if (file_exists($absolutePath)) {
                        error_log("[CommunicationHub::serveMedia] Arquivo reprocessado com sucesso: {$path}");
                    } else {
                        error_log("[CommunicationHub::serveMedia] Falha ao reprocessar arquivo: {$path}");
                        http_response_code(404);
                        echo "Mídia não encontrada e não foi possível reprocessar";
                        exit;
                    }
                } else {
                    http_response_code(404);
                    echo "Mídia não encontrada";
                    exit;
                }
            } catch (\Exception $e) {
                error_log("[CommunicationHub::serveMedia] Erro ao reprocessar mídia: " . $e->getMessage());
                http_response_code(404);
                echo "Mídia não encontrada";
                exit;
            }
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
    
    /**
     * Normaliza channel_id para comparação (lowercase, remove espaços)
     * 
     * @param string|null $channelId
     * @return string|null
     */
    /**
     * PATCH H2: Detecta qual coluna usar para sessionId do gateway
     * Se existir coluna session_id, usa ela. Senão, usa channel_id como fallback.
     * 
     * @param PDO|null $db Conexão do banco (opcional, cria se não fornecido)
     * @return string Nome da coluna que contém o sessionId ('session_id' ou 'channel_id')
     */
    private function getSessionIdColumnName(?PDO $db = null): string
    {
        static $cachedColumnName = null;
        
        if ($cachedColumnName !== null) {
            return $cachedColumnName;
        }
        
        if (!$db) {
            $db = DB::getConnection();
        }
        
        // Verifica se existe coluna session_id
        try {
            $stmt = $db->query("SHOW COLUMNS FROM tenant_message_channels LIKE 'session_id'");
            $column = $stmt->fetch();
            
            if ($column && $column['Field'] === 'session_id') {
                $cachedColumnName = 'session_id';
                error_log('[CommunicationHub] Usando coluna session_id para sessionId do gateway');
                return 'session_id';
            }
        } catch (\Exception $e) {
            error_log('[CommunicationHub] Erro ao verificar coluna session_id: ' . $e->getMessage());
        }
        
        // Fallback: usa channel_id
        $cachedColumnName = 'channel_id';
        error_log('[CommunicationHub] Usando coluna channel_id como fallback para sessionId do gateway');
        return 'channel_id';
    }
    
    /**
     * PATCH H2: Valida se um sessionId do gateway está habilitado para um tenant
     * 
     * @param string $sessionId SessionId do gateway (ex: "pixel12digital", "ImobSites")
     * @param int|null $tenantId ID do tenant (opcional, valida para qualquer tenant se null)
     * @param PDO|null $db Conexão do banco (opcional)
     * @return array|null Dados do canal se encontrado e habilitado, null caso contrário
     */
    private function validateGatewaySessionId(string $sessionId, ?int $tenantId = null, ?PDO $db = null): ?array
    {
        if (!$db) {
            $db = DB::getConnection();
        }
        
        // PATCH H2: Usa a coluna correta (session_id ou channel_id)
        $sessionIdColumn = $this->getSessionIdColumnName($db);
        
        // Query para validar sessionId
        $where = [
            "provider = 'wpp_gateway'",
            "is_enabled = 1"
        ];
        $params = [];
        
        // Compara sessionId (case-sensitive primeiro, depois case-insensitive como fallback)
        // Também remove espaços para comparar "Pixel12 Digital" com "pixel12digital"
        $sessionIdTrimmed = trim($sessionId);
        $sessionIdNormalized = strtolower(preg_replace('/\s+/', '', $sessionIdTrimmed));
        
        if ($sessionIdColumn === 'session_id') {
            // Se tem coluna session_id, compara direto nela
            $where[] = "(session_id = ? OR LOWER(TRIM(session_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(session_id, ' ', '')) = ?)";
            $params[] = $sessionId;
            $params[] = $sessionId;
            $params[] = $sessionIdNormalized;
        } else {
            // Fallback: usa channel_id
            $where[] = "(channel_id = ? OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(channel_id, ' ', '')) = ?)";
            $params[] = $sessionId;
            $params[] = $sessionId;
            $params[] = $sessionIdNormalized;
        }
        
        // Filtra por tenant se fornecido
        // IMPORTANTE: Se não encontrar com tenant_id específico, tenta sem filtro de tenant
        // Isso permite usar canais compartilhados se o tenant não tiver canal próprio
        if ($tenantId !== null) {
            $where[] = "(tenant_id = ? OR tenant_id IS NULL)";
            $params[] = $tenantId;
        }
        
        // PATCH H2: Seleciona apenas colunas que existem no schema atual
        $selectColumns = $sessionIdColumn === 'session_id' 
            ? "id, channel_id, session_id, tenant_id, is_enabled"
            : "id, channel_id, tenant_id, is_enabled";
        
        $sql = "SELECT {$selectColumns} 
                FROM tenant_message_channels 
                WHERE " . implode(' AND ', $where) . "
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $channel = $stmt->fetch();
        
        if ($channel && $channel['is_enabled']) {
            // Retorna o sessionId canônico (preferir session_id se existir, senão channel_id)
            $canonicalSessionId = $sessionIdColumn === 'session_id' 
                ? ($channel['session_id'] ?? $channel['channel_id'] ?? null)
                : ($channel['channel_id'] ?? null);
            
            return [
                'id' => $channel['id'],
                'session_id' => trim($canonicalSessionId),
                'tenant_id' => $channel['tenant_id'] ? (int) $channel['tenant_id'] : null,
                'is_enabled' => (bool) $channel['is_enabled']
            ];
        }
        
        // FALLBACK: Se não encontrou com filtro de tenant, tenta sem filtro (canais compartilhados)
        // Isso permite usar canais que não estão vinculados a um tenant específico
        if ($tenantId !== null) {
            $whereFallback = [
                "provider = 'wpp_gateway'",
                "is_enabled = 1"
            ];
            $paramsFallback = [];
            
            // Mesma lógica de comparação, mas sem filtro de tenant
            if ($sessionIdColumn === 'session_id') {
                $whereFallback[] = "(session_id = ? OR LOWER(TRIM(session_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(session_id, ' ', '')) = ?)";
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionIdNormalized;
            } else {
                $whereFallback[] = "(channel_id = ? OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(channel_id, ' ', '')) = ?)";
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionIdNormalized;
            }
            
            $sqlFallback = "SELECT {$selectColumns} 
                    FROM tenant_message_channels 
                    WHERE " . implode(' AND ', $whereFallback) . "
                    LIMIT 1";
            
            $stmtFallback = $db->prepare($sqlFallback);
            $stmtFallback->execute($paramsFallback);
            $channelFallback = $stmtFallback->fetch();
            
            if ($channelFallback && $channelFallback['is_enabled']) {
                $canonicalSessionId = $sessionIdColumn === 'session_id' 
                    ? ($channelFallback['session_id'] ?? $channelFallback['channel_id'] ?? null)
                    : ($channelFallback['channel_id'] ?? null);
                
                error_log("[CommunicationHub::validateGatewaySessionId] Canal encontrado via fallback (sem filtro de tenant): '{$canonicalSessionId}'");
                
                return [
                    'id' => $channelFallback['id'],
                    'session_id' => trim($canonicalSessionId),
                    'tenant_id' => $channelFallback['tenant_id'] ? (int) $channelFallback['tenant_id'] : null,
                    'is_enabled' => (bool) $channelFallback['is_enabled']
                ];
            }
        }
        
        return null;
    }

    private function normalizeChannelId(?string $channelId): ?string
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
     * Resolve tenant_id pelo channel_id (com normalização APENAS para busca)
     * 
     * IMPORTANTE: Normalização é usada SOMENTE para comparação WHERE.
     * O valor retornado é sempre o valor original do banco.
     * 
     * @param string|null $channelId
     * @param PDO|null $db Conexão do banco (opcional, cria nova se não fornecido)
     * @return int|null
     */
    private function resolveTenantByChannelId(?string $channelId, ?PDO $db = null): ?int
    {
        if (empty($channelId)) {
            return null;
        }
        
        if (!$db) {
            $db = DB::getConnection();
        }
        
        // Normaliza channel_id APENAS para comparação WHERE (não altera o valor original)
        $normalized = $this->normalizeChannelId($channelId);
        
        // Tenta busca exata primeiro (case-sensitive)
        $stmt = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND is_enabled = 1
            AND channel_id = ?
            LIMIT 1
        ");
        $stmt->execute([$channelId]);
        $result = $stmt->fetch();
        
        if ($result && $result['tenant_id']) {
            return (int) $result['tenant_id'];
        }
        
        // Se não encontrou, tenta busca case-insensitive usando normalização APENAS no WHERE
        $stmt2 = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND is_enabled = 1
            AND (
                LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
                OR LOWER(REPLACE(channel_id, ' ', '')) = ?
            )
            LIMIT 1
        ");
        $stmt2->execute([$channelId, $normalized]);
        $result2 = $stmt2->fetch();
        
        if ($result2 && $result2['tenant_id']) {
            return (int) $result2['tenant_id'];
        }
        
        return null;
    }

    /**
     * Valida se o número do contato corresponde ao número do tenant
     * 
     * Evita vincular conversas de números desconhecidos ao tenant do canal incorretamente.
     * 
     * @param string $contactExternalId Número do contato (pode conter @lid ou outros sufixos)
     * @param int $tenantId ID do tenant
     * @param PDO|null $db Conexão do banco
     * @return bool True se o contato pertence ao tenant, False caso contrário
     */
    private function validateContactBelongsToTenant(string $contactExternalId, int $tenantId, ?PDO $db = null): bool
    {
        if (!$db) {
            $db = DB::getConnection();
        }
        
        // Busca telefone do tenant
        $stmt = $db->prepare("SELECT phone FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        if (!$tenant || empty($tenant['phone'])) {
            // Se tenant não tem telefone cadastrado, não vincula automaticamente
            return false;
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
            
            // Remove 9º dígito de ambos para comparação
            if (strlen($contactPhone) === 13 && strlen($tenantPhone) === 13) {
                $contactWithout9th = substr($contactPhone, 0, 4) . substr($contactPhone, 5);
                $tenantWithout9th = substr($tenantPhone, 0, 4) . substr($tenantPhone, 5);
                
                if ($contactWithout9th === $tenantWithout9th) {
                    return true;
                }
            }
            
            // Tenta adicionar 9º dígito em ambos
            if (strlen($contactPhone) === 12 && strlen($tenantPhone) === 12) {
                $contactWith9th = substr($contactPhone, 0, 4) . '9' . substr($contactPhone, 4);
                $tenantWith9th = substr($tenantPhone, 0, 4) . '9' . substr($tenantPhone, 4);
                
                if ($contactWith9th === $tenantWith9th) {
                    return true;
                }
            }
        }
        
        // Números não correspondem
        return false;
    }
}

