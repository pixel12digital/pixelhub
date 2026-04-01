<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Core\Env;
use PixelHub\Core\ContactHelper;
use PixelHub\Services\OpportunityService;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\EventRouterService;
use PixelHub\Services\EventNormalizationService;
use PixelHub\Services\WhatsAppBillingService;
use PixelHub\Services\GatewaySecret;
use PixelHub\Services\WhatsAppProviderFactory;
use PixelHub\Integrations\WhatsApp\WppConnectProvider;
use PixelHub\Integrations\WhatsApp\WhapiCloudProvider;
use PDO;

/**
 * Controller para o Painel Operacional de Comunicação
 * 
 * Interface onde operadores enviam mensagens, respondem conversas e gerenciam canais
 */
class CommunicationHubController extends Controller
{
    private function resolveOpportunityIdForConversation(PDO $db, int $conversationId): ?int
    {
        // 1) Vínculo direto
        $oppStmt = $db->prepare("SELECT id FROM opportunities WHERE conversation_id = ? ORDER BY id DESC LIMIT 1");
        $oppStmt->execute([$conversationId]);
        $opp = $oppStmt->fetch();
        if (!empty($opp['id'])) {
            return (int) $opp['id'];
        }

        // 2) Fallback: tenta encontrar oportunidade por lead_id/tenant_id e auto-vincular conversation_id
        $convStmt = $db->prepare("SELECT lead_id, tenant_id FROM conversations WHERE id = ? LIMIT 1");
        $convStmt->execute([$conversationId]);
        $conv = $convStmt->fetch();
        if (!$conv) return null;

        $leadId = !empty($conv['lead_id']) ? (int) $conv['lead_id'] : null;
        $tenantId = !empty($conv['tenant_id']) ? (int) $conv['tenant_id'] : null;
        if (!$leadId && !$tenantId) return null;

        if ($leadId) {
            $stmt = $db->prepare("SELECT id FROM opportunities WHERE lead_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute([$leadId]);
        } else {
            $stmt = $db->prepare("SELECT id FROM opportunities WHERE tenant_id = ? ORDER BY updated_at DESC, id DESC LIMIT 1");
            $stmt->execute([$tenantId]);
        }
        $opp2 = $stmt->fetch();
        if (empty($opp2['id'])) return null;

        $oppId = (int) $opp2['id'];
        try {
            $upd = $db->prepare("UPDATE opportunities SET conversation_id = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$conversationId, $oppId]);
        } catch (\Throwable $e) {
            // não bloqueia
        }
        return $oppId;
    }

    /**
     * Resolve qual provider WhatsApp usar (WPPConnect ou Meta Official API)
     * 
     * @param int|null $tenantId ID do tenant
     * @param string|null $channelId Channel ID para WPPConnect (opcional)
     * @param string|null $conversationProviderType Provider da conversa original (para manter consistência)
     * @return WhatsAppGatewayClient Client compatível (WppConnectProvider retorna o underlying client)
     */
    private function resolveWhatsAppProvider(?int $tenantId, ?string $channelId = null, ?string $conversationProviderType = null): WhatsAppGatewayClient
    {
        // PRIORIDADE 1: Se conversa tem provider_type definido, usa o mesmo provider para responder
        // Isso garante que respostas usem o mesmo canal da mensagem original
        // Se não tem tenant_id, usa WPPConnect padrão
        if (empty($tenantId)) {
            $provider = new WppConnectProvider(['channel_id' => $channelId]);
            return $provider->getUnderlyingClient();
        }

        try {
            $provider = WhatsAppProviderFactory::getProviderForTenant($tenantId);
            
            if ($provider instanceof WppConnectProvider) {
                if ($channelId) {
                    $provider = new WppConnectProvider(['channel_id' => $channelId, 'tenant_id' => $tenantId]);
                }
                return $provider->getUnderlyingClient();
            }
            
            // Fallback para WPPConnect se provider não suportado ainda
            $fallbackProvider = new WppConnectProvider(['channel_id' => $channelId, 'tenant_id' => $tenantId]);
            return $fallbackProvider->getUnderlyingClient();
            
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao resolver provider para tenant {$tenantId}: " . $e->getMessage());
            $fallbackProvider = new WppConnectProvider(['channel_id' => $channelId, 'tenant_id' => $tenantId]);
            return $fallbackProvider->getUnderlyingClient();
        }
    }

    /**
     * Cache estático de existência de tabelas (evita SHOW TABLES repetidos no mesmo request)
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];
    
    /**
     * Verifica se uma tabela existe (com cache por request)
     */
    private static function tableExists(PDO $db, string $tableName): bool
    {
        if (isset(self::$tableExistsCache[$tableName])) {
            return self::$tableExistsCache[$tableName];
        }
        
        try {
            $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($tableName));
            self::$tableExistsCache[$tableName] = $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            self::$tableExistsCache[$tableName] = false;
        }
        
        return self::$tableExistsCache[$tableName];
    }
    
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
        $sessionId = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? $_GET['session_id'] : null;

        // Busca sessões WhatsApp disponíveis (para o filtro)
        $whatsappSessions = [];
        try {
            $whatsappSessions = $this->getWhatsAppSessions($db);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar sessões WhatsApp: " . $e->getMessage());
        }

        // Busca threads de conversa ativos
        $where = [];
        $params = [];

        // Threads de WhatsApp (via eventos recentes)
        try {
            $whatsappThreads = $this->getWhatsAppThreads($db, $tenantId, $status, $sessionId);
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
                    // Oculta prospecções sem resposta (source=prospecting + última mensagem outbound)
                    if (($thread['source'] ?? '') === 'prospecting' && ($thread['last_message_direction'] ?? '') === 'outbound') {
                        continue;
                    }
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
                  AND NOT (COALESCE(c.source,'') = 'prospecting' AND COALESCE(c.last_message_direction,'') = 'outbound')
            ");
            $countStmt->execute();
            $countResult = $countStmt->fetch();
            $incomingLeadsCount = (int) ($countResult['total'] ?? 0);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao contar incoming leads: " . $e->getMessage());
            // Fallback para contagem do array
            $incomingLeadsCount = count($incomingLeads);
        }
        
        // =====================================================================
        // Estatísticas operacionais (refletem os filtros atuais)
        // =====================================================================
        $todayStart = date('Y-m-d 00:00:00');
        
        // 1. Pendentes para responder: última mensagem foi do cliente (inbound)
        //    e ainda não houve resposta - isso é a fila real de atendimento
        $pendingToRespond = array_filter($normalThreads, function($t) {
            return ($t['last_message_direction'] ?? '') === 'inbound';
        });
        $pendingCount = count($pendingToRespond);
        
        // 2. Novas hoje: conversas que tiveram primeira mensagem recebida hoje
        $newToday = count(array_filter($normalThreads, function($t) use ($todayStart) {
            $createdAt = $t['created_at'] ?? null;
            return $createdAt && $createdAt >= $todayStart;
        }));
        
        // 3. Mais antigo pendente: entre os pendentes, qual está esperando há mais tempo
        //    Mostra a "idade" do backlog (não média)
        $oldestPendingMinutes = 0;
        if (!empty($pendingToRespond)) {
            $oldestTimestamp = null;
            foreach ($pendingToRespond as $thread) {
                $lastMsgAt = $thread['last_message_at'] ?? $thread['last_activity'] ?? null;
                if ($lastMsgAt) {
                    $ts = strtotime($lastMsgAt);
                    if ($oldestTimestamp === null || $ts < $oldestTimestamp) {
                        $oldestTimestamp = $ts;
                    }
                }
            }
            if ($oldestTimestamp) {
                $oldestPendingMinutes = round((time() - $oldestTimestamp) / 60);
            }
        }
        
        $stats = [
            'pending_to_respond' => $pendingCount,
            'new_today' => $newToday,
            'oldest_pending_minutes' => $oldestPendingMinutes,
            'incoming_leads_count' => $incomingLeadsCount
        ];

        // Garante que threads é sempre um array válido
        $threadsList = is_array($normalThreads) ? $normalThreads : [];
        $incomingLeadsList = is_array($incomingLeads) ? $incomingLeads : [];
        
        // Thread selecionada (para não mostrar badge na conversa aberta)
        $selectedThreadId = $_GET['thread_id'] ?? null;
        
        $this->view('communication_hub.index', [
            'threads' => $threadsList,
            'incoming_leads' => $incomingLeadsList,
            'tenants' => is_array($tenants) ? $tenants : [],
            'whatsapp_sessions' => is_array($whatsappSessions) ? $whatsappSessions : [],
            'stats' => is_array($stats) ? $stats : ['pending_to_respond' => 0, 'new_today' => 0, 'oldest_pending_minutes' => 0, 'incoming_leads_count' => 0],
            'filters' => [
                'channel' => $channel ?? 'all',
                'tenant_id' => $tenantId,
                'status' => $status ?? 'active',
                'session_id' => $sessionId
            ],
            'selected_thread_id' => $selectedThreadId
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

        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            if ($channel === 'whatsapp') {
                $messages = $this->getWhatsAppMessages($db, $threadId);
                $thread = $this->getWhatsAppThreadInfo($db, $threadId);

                if ($thread && isset($thread['conversation_id'])) {
                    $this->markConversationAsRead($db, (int) $thread['conversation_id']);
                    $thread['unread_count'] = 0;
                }
            } else {
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
            error_log("[CommunicationHub::getThreadData] Erro: " . $e->getMessage());
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
     * Converte áudio WebM (binário) para OGG/Opus via ffmpeg.
     * Retorna resultado estruturado para fallback: se EXEC_DISABLED/FFMPEG_*,
     * o chamador pode enviar WebM direto ao gateway (gateway converte na VPS).
     *
     * @param string $webmBin Conteúdo binário do WebM
     * @param string $channelId Apenas para log
     * @return array { ok: bool, base64?: string, reason: string, stderr_preview: string }
     */
    private function convertWebMToOggBase64(string $webmBin, string $channelId): array
    {
        $cap = 400;
        $preview = static function (array $lines) use ($cap) {
            $s = implode(' ', $lines);
            return strlen($s) > $cap ? substr($s, 0, $cap) . '...' : $s;
        };

        $disabled = array_map('trim', array_filter(explode(',', (string) ini_get('disable_functions'))));
        if (in_array('exec', $disabled, true)) {
            error_log("[CommunicationHub::convertWebMToOgg] exec está em disable_functions");
            return [
                'ok' => false,
                'reason' => 'EXEC_DISABLED',
                'stderr_preview' => '',
            ];
        }

        $tmpDir = sys_get_temp_dir();
        $prefix = 'pixelhub_audio_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $webmPath = $tmpDir . DIRECTORY_SEPARATOR . $prefix . '.webm';
        $oggPath = $tmpDir . DIRECTORY_SEPARATOR . $prefix . '.ogg';

        if (file_put_contents($webmPath, $webmBin) === false) {
            error_log("[CommunicationHub::convertWebMToOgg] Falha ao escrever temp WebM");
            @unlink($webmPath);
            return ['ok' => false, 'reason' => 'TEMP_WRITE_FAILED', 'stderr_preview' => ''];
        }

        $ffmpeg = 'ffmpeg';
        $cmd = sprintf(
            '%s -y -i %s -c:a libopus -b:a 32k -ar 16000 %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($webmPath),
            escapeshellarg($oggPath)
        );

        $output = [];
        $ret = 0;
        @exec($cmd, $output, $ret);
        @unlink($webmPath);
        $stderrPreview = $preview($output);

        if ($ret !== 0) {
            error_log("[CommunicationHub::convertWebMToOgg] ffmpeg exit {$ret}, stderr_preview: {$stderrPreview}");
            if (is_file($oggPath)) {
                @unlink($oggPath);
            }
            return [
                'ok' => false,
                'reason' => 'FFMPEG_FAILED',
                'stderr_preview' => $stderrPreview,
            ];
        }

        if (!is_file($oggPath) || filesize($oggPath) < 100) {
            error_log("[CommunicationHub::convertWebMToOgg] OGG vazio ou ausente, out: {$stderrPreview}");
            if (is_file($oggPath)) {
                @unlink($oggPath);
            }
            return [
                'ok' => false,
                'reason' => 'FFMPEG_OUTPUT_INVALID',
                'stderr_preview' => $stderrPreview,
            ];
        }

        $oggBin = file_get_contents($oggPath);
        @unlink($oggPath);
        if ($oggBin === false || strlen($oggBin) < 100) {
            return ['ok' => false, 'reason' => 'OGG_READ_FAILED', 'stderr_preview' => $stderrPreview];
        }

        if (function_exists('pixelhub_log')) {
            pixelhub_log("[CommunicationHub::convertWebMToOgg] WebM→OGG ok para channel={$channelId}, size=" . strlen($oggBin));
        }
        return [
            'ok' => true,
            'base64' => base64_encode($oggBin),
            'reason' => '',
            'stderr_preview' => '',
        ];
    }

    /**
     * Envia mensagem
     * 
     * POST /communication-hub/send
     * CORRIGIDO: tenant_id agora é opcional (pode ser inferido da conversa)
     */
    public function send(): void
    {
        $requestId = substr(str_replace('-', '', bin2hex(random_bytes(8))), 0, 16);

        if (!headers_sent()) {
            header("X-Request-Id: {$requestId}");
        }

        try {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            Auth::requireInternal();

            $channel = $_POST['channel'] ?? null;
            $threadId = $_POST['thread_id'] ?? null;
            $to = $_POST['to'] ?? null;
            $message = trim($_POST['message'] ?? '');
            $tenantIdFromPost = isset($_POST['tenant_id']) && $_POST['tenant_id'] !== '' ? (int) $_POST['tenant_id'] : null;
            $channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? trim($_POST['channel_id']) : null;
            $originalChannelIdFromPost = $channelId;

            $contactNameFromPost = trim($_POST['contact_name'] ?? '');
            $forwardToAll = isset($_POST['forward_to_all']) && $_POST['forward_to_all'] === '1';
            $channelIdsArray = isset($_POST['channel_ids']) && is_array($_POST['channel_ids']) ? $_POST['channel_ids'] : null;
            $messageType = isset($_POST['type']) ? strtolower(trim($_POST['type'])) : 'text';
            $base64Ptt = isset($_POST['base64Ptt']) ? trim($_POST['base64Ptt']) : null;
            $base64Image = isset($_POST['base64Image']) ? trim($_POST['base64Image']) : null;
            $base64Document = isset($_POST['base64Document']) ? trim($_POST['base64Document']) : null;
            $caption = isset($_POST['caption']) ? trim($_POST['caption']) : null;
            $fileName = isset($_POST['fileName']) ? trim($_POST['fileName']) : null;

            // Fallback: lê arquivo do $_FILES['attachment'] se base64Document/Image não veio via POST
            // (base64 em campo POST pode ser bloqueado por WAF/mod_security no servidor)
            if (empty($base64Document) && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['attachment']['tmp_name'];
                $base64Document = base64_encode(file_get_contents($tmpPath));
                if (empty($fileName)) {
                    $fileName = $_FILES['attachment']['name'] ?? 'documento';
                }
                error_log("[CommunicationHub::send] 📎 Documento lido via \$_FILES: {$fileName} (" . strlen($base64Document) . " bytes base64)");
            }
            if (empty($base64Image) && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $mime = $_FILES['attachment']['type'] ?? '';
                if (strpos($mime, 'image/') === 0) {
                    $tmpPath = $_FILES['attachment']['tmp_name'];
                    $base64Image = base64_encode(file_get_contents($tmpPath));
                    error_log("[CommunicationHub::send] 🖼️ Imagem lida via \$_FILES: {$fileName} (" . strlen($base64Image) . " bytes base64)");
                }
            }

            // Aumenta timeout/memória para envio de mídia
            if (in_array($messageType, ['audio', 'image', 'video', 'document'])) {
                set_time_limit(120);
                ini_set('max_execution_time', '120');
                ini_set('memory_limit', '256M');
            }

            // Deriva tenant_id da conversa se thread_id disponível (fonte da verdade)
            $tenantId = $tenantIdFromPost;
            $conversationProviderType = null;

            if (!empty($threadId) && preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                $conversationId = (int) $matches[1];
                try {
                    $db = DB::getConnection();
                    $convStmt = $db->prepare("SELECT tenant_id, channel_id, contact_external_id, provider_type FROM conversations WHERE id = ? LIMIT 1");
                    $convStmt->execute([$conversationId]);
                    $conv = $convStmt->fetch();

                    if ($conv && !empty($conv['provider_type'])) {
                        $conversationProviderType = $conv['provider_type'];
                    }

                    if ($conv && !empty($conv['tenant_id'])) {
                        $tenantId = (int) $conv['tenant_id'];
                    } elseif ($conv && empty($conv['tenant_id']) && !empty($conv['channel_id'])) {
                        $resolvedTenantId = $this->resolveTenantByChannelId($conv['channel_id'], $db);
                        if ($resolvedTenantId) {
                            $shouldLink = true;
                            if (!empty($conv['contact_external_id'])) {
                                $shouldLink = $this->validateContactBelongsToTenant(
                                    $conv['contact_external_id'],
                                    $resolvedTenantId,
                                    $db
                                );
                            }
                            if ($shouldLink) {
                                $updateStmt = $db->prepare("UPDATE conversations SET tenant_id = ?, is_incoming_lead = 0 WHERE id = ?");
                                $updateStmt->execute([$resolvedTenantId, $conversationId]);
                                $tenantId = $resolvedTenantId;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log("[CommunicationHub::send] Erro ao resolver tenant_id da conversa: " . $e->getMessage());
                }
            }

        // Validação: para texto, message é obrigatório; para áudio/imagem/documento, base64 é obrigatório
        if (empty($channel)) {
            $this->json(['success' => false, 'error' => 'Canal é obrigatório', 'request_id' => $requestId], 400);
            return;
        }
        // Texto: message obrigatório (exceto para whatsapp_api que usa templates)
        if ($messageType === 'text' && empty($message) && $channel !== 'whatsapp_api') {
            $this->json(['success' => false, 'error' => 'Mensagem é obrigatória para tipo texto', 'request_id' => $requestId], 400);
            return;
        }
        // Áudio: base64Ptt obrigatório
        if ($messageType === 'audio' && (empty($base64Ptt) || !is_string($base64Ptt))) {
            $this->json(['success' => false, 'error' => 'base64Ptt é obrigatório para tipo áudio', 'request_id' => $requestId], 400);
            return;
        }
        // Imagem: base64Image obrigatório
        if ($messageType === 'image' && (empty($base64Image) || !is_string($base64Image))) {
            $this->json(['success' => false, 'error' => 'base64Image é obrigatório para tipo imagem', 'request_id' => $requestId], 400);
            return;
        }
        // Documento: base64Document e fileName obrigatórios
        if ($messageType === 'document' && (empty($base64Document) || !is_string($base64Document))) {
            $this->json(['success' => false, 'error' => 'base64Document é obrigatório para tipo documento', 'request_id' => $requestId], 400);
            return;
        }
        if ($messageType === 'document' && empty($fileName)) {
            $this->json(['success' => false, 'error' => 'fileName é obrigatório para tipo documento', 'request_id' => $requestId], 400);
            return;
        }
            if ($channel === 'whatsapp_api') {
                $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;

                if (empty($templateId)) {
                    $this->json(['success' => false, 'error' => 'Template é obrigatório para envio via API Meta', 'request_id' => $requestId], 400);
                    return;
                }

                if (empty($to)) {
                    $this->json(['success' => false, 'error' => 'Telefone é obrigatório', 'request_id' => $requestId], 400);
                    return;
                }

                if (empty($tenantId)) {
                    $leadId = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int) $_POST['lead_id'] : null;
                    if ($leadId) {
                        if (!isset($db)) {
                            $db = DB::getConnection();
                        }
                        $leadStmt = $db->prepare("SELECT converted_tenant_id FROM leads WHERE id = ? LIMIT 1");
                        $leadStmt->execute([$leadId]);
                        $lead = $leadStmt->fetch();
                        if ($lead && !empty($lead['converted_tenant_id'])) {
                            $tenantId = (int) $lead['converted_tenant_id'];
                        }
                    }
                }

                // tenant_id é opcional para Meta API: credenciais são buscadas via config global (is_global=1)
                // Se não resolvido via lead, segue com null (sendViaMetaAPI usa 'Cliente' como fallback de nome)

                try {
                    $result = $this->sendViaMetaAPI($templateId, $to, $tenantId, $requestId);
                    $this->json($result);
                    return;
                } catch (\Throwable $e) {
                    error_log("[CommunicationHub::send] Erro ao enviar via Meta API: " . $e->getMessage());
                    $this->json(['success' => false, 'error' => 'Erro ao enviar mensagem: ' . $e->getMessage(), 'request_id' => $requestId], 500);
                    return;
                }
            }
            
            if ($channel === 'whatsapp') {
                if (empty($to)) {
                    $this->json(['success' => false, 'error' => 'to (telefone) é obrigatório para WhatsApp', 'request_id' => $requestId], 400);
                    return;
                }

                $db = DB::getConnection();
                $targetChannels = [];

                if (empty($channelId) && !empty($threadId) && preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                    $conversationId = (int) $matches[1];

                    try {
                        $convStmt = $db->prepare("SELECT tenant_id, channel_id, contact_external_id FROM conversations WHERE id = ?");
                        $convStmt->execute([$conversationId]);
                        $conv = $convStmt->fetch();
                    } catch (\Exception $e) {
                        error_log("[CommunicationHub::send] Erro ao buscar conversa: " . $e->getMessage());
                        throw $e;
                    }
                    
                    if ($conv) {
                        if (empty($conv['tenant_id']) && !empty($conv['channel_id']) && $tenantId) {
                            $updateStmt = $db->prepare("UPDATE conversations SET tenant_id = ? WHERE id = ?");
                            $updateStmt->execute([$tenantId, $conversationId]);
                        } elseif ($conv['tenant_id'] && $conv['tenant_id'] != $tenantId) {
                            $tenantId = (int) $conv['tenant_id'];
                        }

                        if (empty($channelId)) {
                            if (empty($conv['channel_id'])) {
                                $this->json([
                                    'success' => false,
                                    'error' => 'THREAD_MISSING_CHANNEL_ID',
                                    'error_code' => 'THREAD_MISSING_CHANNEL_ID',
                                    'message' => 'A conversa não possui canal associado. Verifique se a mensagem foi recebida corretamente.',
                                    'request_id' => $requestId
                                ], 400);
                                return;
                            }

                            $sessionId = trim($conv['channel_id']);
                            $validatedChannel = $this->validateGatewaySessionId($sessionId, $tenantId, $db);

                            if ($validatedChannel) {
                                $foundSessionId = trim($validatedChannel['session_id']);
                                $targetChannels = [$foundSessionId];
                            } else {
                                $errorChannelId = !empty($originalChannelIdFromPost) ? $originalChannelIdFromPost : (!empty($channelId) ? $channelId : $sessionId);
                                $this->json([
                                    'success' => false,
                                    'error' => "SessionId do gateway '{$errorChannelId}' não está habilitado para este tenant. Verifique se a sessão está cadastrada e habilitada.",
                                    'error_code' => 'CHANNEL_NOT_FOUND',
                                    'channel_id' => $errorChannelId,
                                    'request_id' => $requestId
                                ], 400);
                                return;
                            }
                        }
                    } else {
                        $this->json([
                            'success' => false,
                            'error' => 'THREAD_NOT_FOUND',
                            'error_code' => 'THREAD_NOT_FOUND',
                            'request_id' => $requestId
                        ], 404);
                        return;
                    }
                }
                
                // NOVO: Determina lista de canais para envio
                // CRÍTICO: channel_id do POST sempre tem prioridade absoluta sobre qualquer busca da conversa
                // targetChannels já foi inicializado no início, mas pode ter sido definido no bloco do threadId acima
                // Se ainda estiver vazio, precisa buscar canais
                
                // PRIORIDADE ABSOLUTA: Se channel_id foi fornecido no POST, usa ele diretamente (ignora conversa)
                if (!empty($channelId) && empty($targetChannels)) {
                    $validatedChannel = $this->validateGatewaySessionId($channelId, $tenantId, $db);
                    if ($validatedChannel && !empty($validatedChannel['session_id'])) {
                        $targetChannels = [trim($validatedChannel['session_id'])];
                    } else {
                        $targetChannels = [trim($channelId)];
                    }
                }
                
                // Se forward_to_all está ativo, busca todos os canais habilitados
                if ($forwardToAll) {
                    $sessionIdColumn = $this->getSessionIdColumnName($db);
                    $channelStmt = $db->query("
                        SELECT DISTINCT {$sessionIdColumn} as session_id, channel_id
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
                    $allChannels = $channelStmt->fetchAll(PDO::FETCH_ASSOC);
                    // Usa session_id se existir, senão usa channel_id normalizado
                    $targetChannels = [];
                    foreach ($allChannels as $channel) {
                        $sessionId = !empty($channel['session_id']) ? trim($channel['session_id']) : null;
                        if ($sessionId) {
                            $targetChannels[] = $sessionId;
                        } else {
                            // Fallback: normaliza channel_id removendo espaços e convertendo para lowercase
                            $normalized = strtolower(preg_replace('/\s+/', '', trim($channel['channel_id'])));
                            if (!empty($normalized)) {
                                $targetChannels[] = $normalized;
                            }
                        }
                    }
                    $targetChannels = array_unique(array_filter($targetChannels));
                    error_log("[CommunicationHub::send] Canais encontrados para encaminhamento (normalizados): " . implode(', ', $targetChannels));
                } 
                // Se channel_ids array foi fornecido, usa esses canais
                elseif ($channelIdsArray && !empty($channelIdsArray)) {
                    error_log("[CommunicationHub::send] Lista de canais fornecida: " . implode(', ', $channelIdsArray));
                    // Valida que os canais existem e estão habilitados (case-insensitive)
                    // CRÍTICO: Sempre retorna session_id técnico, não channel_id (que pode ser nome de exibição)
                    $sessionIdColumn = $this->getSessionIdColumnName($db);
                    $placeholders = str_repeat('?,', count($channelIdsArray) - 1) . '?';
                    $channelStmt = $db->prepare("
                        SELECT {$sessionIdColumn} as session_id, channel_id
                        FROM tenant_message_channels 
                        WHERE provider = 'wpp_gateway' 
                        AND is_enabled = 1
                        AND (
                            channel_id IN ($placeholders)
                            OR LOWER(channel_id) IN (" . implode(',', array_fill(0, count($channelIdsArray), 'LOWER(?)')) . ")
                            OR {$sessionIdColumn} IN ($placeholders)
                            OR LOWER({$sessionIdColumn}) IN (" . implode(',', array_fill(0, count($channelIdsArray), 'LOWER(?)')) . ")
                        )
                    ");
                    // Executa com ambos os arrays (original e lowercase) para busca case-insensitive
                    $executeParams = array_merge($channelIdsArray, array_map('strtolower', $channelIdsArray), $channelIdsArray, array_map('strtolower', $channelIdsArray));
                    $channelStmt->execute($executeParams);
                    $validChannels = $channelStmt->fetchAll(PDO::FETCH_ASSOC);
                    // Usa session_id se existir, senão normaliza channel_id
                    $targetChannels = [];
                    foreach ($validChannels as $channel) {
                        $sessionId = !empty($channel['session_id']) ? trim($channel['session_id']) : null;
                        if ($sessionId) {
                            $targetChannels[] = $sessionId;
                        } else {
                            // Fallback: normaliza channel_id
                            $normalized = strtolower(preg_replace('/\s+/', '', trim($channel['channel_id'])));
                            if (!empty($normalized)) {
                                $targetChannels[] = $normalized;
                            }
                        }
                    }
                    $targetChannels = array_unique(array_filter($targetChannels));
                    error_log("[CommunicationHub::send] Canais válidos encontrados (normalizados): " . implode(', ', $targetChannels));
                    
                    // Se nenhum canal válido encontrado, tenta busca mais flexível
                    if (empty($targetChannels)) {
                        error_log("[CommunicationHub::send] Nenhum canal encontrado com nomes exatos, tentando busca flexível...");
                        foreach ($channelIdsArray as $requestedChannel) {
                            $lowerRequested = strtolower(trim($requestedChannel));
                            $sessionIdColumn = $this->getSessionIdColumnName($db);
                            $flexibleStmt = $db->prepare("
                                SELECT {$sessionIdColumn} as session_id, channel_id
                                FROM tenant_message_channels 
                                WHERE provider = 'wpp_gateway' 
                                AND is_enabled = 1
                                AND (
                                    LOWER(channel_id) LIKE ?
                                    OR LOWER(channel_id) LIKE ?
                                    OR LOWER({$sessionIdColumn}) LIKE ?
                                    OR LOWER({$sessionIdColumn}) LIKE ?
                                )
                                LIMIT 1
                            ");
                            $flexibleStmt->execute(["%{$lowerRequested}%", "{$lowerRequested}%", "%{$lowerRequested}%", "{$lowerRequested}%"]);
                            $found = $flexibleStmt->fetch(PDO::FETCH_ASSOC);
                            if ($found) {
                                $sessionId = !empty($found['session_id']) ? trim($found['session_id']) : null;
                                if ($sessionId) {
                                    $targetChannels[] = $sessionId;
                                    error_log("[CommunicationHub::send] Canal encontrado via busca flexível: '{$sessionId}' (session_id) para '{$requestedChannel}'");
                                } else {
                                    // Fallback: normaliza channel_id
                                    $normalized = strtolower(preg_replace('/\s+/', '', trim($found['channel_id'])));
                                    if (!empty($normalized)) {
                                        $targetChannels[] = $normalized;
                                        error_log("[CommunicationHub::send] Canal encontrado via busca flexível: '{$normalized}' (normalizado) para '{$requestedChannel}'");
                                    }
                                }
                            }
                        }
                    }
                }
                
                // Se ainda não tem canais definidos, usa lógica antiga (um único canal)
                if (empty($targetChannels)) {
                    // PRIORIDADE 1: Usa channel_id fornecido diretamente no POST (vem do frontend)
                    // CRÍTICO: O channel_id do POST sempre tem prioridade sobre o da conversa
                    if ($channelId) {
                        // NOTA: $originalChannelIdFromPost já foi preservado no início do método (linha 418)
                        // Não precisa redefinir aqui, pois isso sobrescreveria o valor original
                        error_log("[CommunicationHub::send] PRIORIDADE 1: Usando channel_id do POST: '{$channelId}' (originalChannelIdFromPost já preservado: " . ($originalChannelIdFromPost ?: 'NULL') . ")");
                        
                        // PATCH H2: Interpreta channelId recebido como sessionId do gateway
                        // Valida usando a nova função que detecta schema automaticamente
                        // CORREÇÃO: Tenta primeiro sem tenant_id para permitir canais compartilhados
                        error_log("[CommunicationHub::send] 🔍 Validando channelId do POST: '{$channelId}' (tenant_id: " . ($tenantId ?: 'NULL') . ")");
                        $validatedChannel = $this->validateGatewaySessionId($channelId, null, $db);
                        
                        // Se não encontrou sem tenant_id, tenta com tenant_id específico
                        if (!$validatedChannel && $tenantId) {
                            error_log("[CommunicationHub::send] 🔍 Tentando validação com tenant_id específico: {$tenantId}");
                            $validatedChannel = $this->validateGatewaySessionId($channelId, $tenantId, $db);
                        }
                        
                        if (!$validatedChannel) {
                            error_log("[CommunicationHub::send] ⚠️ ERRO: SessionId '{$channelId}' do gateway não encontrado ou não habilitado");
                            error_log("[CommunicationHub::send] Tentou buscar com tenant_id: " . ($tenantId ?: 'NULL'));
                            
                            // Log adicional: verifica se o canal existe sem filtro de tenant
                            $sessionIdColumn = $this->getSessionIdColumnName($db);
                            $normalized = strtolower(preg_replace('/\s+/', '', trim($channelId)));
                            error_log("[CommunicationHub::send] 🔍 Buscando canais no banco com normalização: original='{$channelId}', normalized='{$normalized}'");
                            
                            $checkStmt = $db->prepare("
                                SELECT channel_id, tenant_id, is_enabled, {$sessionIdColumn} as session_id
                                FROM tenant_message_channels
                                WHERE provider = 'wpp_gateway'
                                AND (
                                    channel_id = ?
                                    OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?))
                                    OR LOWER(REPLACE(channel_id, ' ', '')) = ?
                                    OR {$sessionIdColumn} = ?
                                    OR LOWER(TRIM({$sessionIdColumn})) = LOWER(TRIM(?))
                                    OR LOWER(REPLACE({$sessionIdColumn}, ' ', '')) = ?
                                )
                                LIMIT 5
                            ");
                            $checkStmt->execute([$channelId, $channelId, $normalized, $channelId, $channelId, $normalized]);
                            $foundChannels = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($foundChannels)) {
                                error_log("[CommunicationHub::send] ✅ Canais encontrados no banco: " . json_encode($foundChannels));
                                // CORREÇÃO: Se encontrou canais no banco mas não passou na validação,
                                // pode ser que o problema seja a normalização. Tenta usar o channel_id do banco.
                                $firstFound = $foundChannels[0];
                                $foundChannelId = $firstFound['channel_id'];
                                $foundSessionId = !empty($firstFound['session_id']) ? trim($firstFound['session_id']) : null;
                                
                                error_log("[CommunicationHub::send] 🔍 Tentando usar channel_id do banco: '{$foundChannelId}' (session_id: " . ($foundSessionId ?: 'NULL') . ", original do POST: '{$channelId}')");
                                
                                // Tenta validar novamente com o channel_id do banco
                                $retryValidated = $this->validateGatewaySessionId($foundChannelId, $tenantId, $db);
                                if ($retryValidated) {
                                    error_log("[CommunicationHub::send] ✅ Canal encontrado usando channel_id do banco: '{$foundChannelId}' → session_id: '{$retryValidated['session_id']}'");
                                    $foundSessionId = trim($retryValidated['session_id']);
                                    $targetChannels = [$foundSessionId];
                                    // Pula o erro e continua
                                } elseif ($foundSessionId) {
                                    // Se não passou na validação mas encontrou session_id, tenta validar o session_id
                                    error_log("[CommunicationHub::send] 🔍 Tentando validar session_id encontrado: '{$foundSessionId}'");
                                    $retryValidated = $this->validateGatewaySessionId($foundSessionId, $tenantId, $db);
                                    if ($retryValidated) {
                                        error_log("[CommunicationHub::send] ✅ Canal encontrado usando session_id do banco: '{$foundSessionId}'");
                                        $targetChannels = [trim($retryValidated['session_id'])];
                                        // Pula o erro e continua
                                    } else {
                                        error_log("[CommunicationHub::send] ❌ Canal do banco também não passou na validação (session_id: '{$foundSessionId}')");
                                        // Retorna erro com o channel_id ORIGINAL do POST (preservado no início)
                                        // FORÇA uso do originalChannelIdFromPost se disponível, senão usa channelId do POST
                                        $errorChannelId = !empty($originalChannelIdFromPost) ? $originalChannelIdFromPost : (!empty($channelId) ? $channelId : 'pixel12digital');
                                        // ===== OBJETIVO 4: LOG ANTES RETORNO CHANNEL_NOT_FOUND (RETURN_POINT=B) =====
                                        error_log("{$logPrefix} ===== RETURN_POINT=B (CHANNEL_NOT_FOUND) =====");
                                        error_log("{$logPrefix} RETURN_POINT=B: variável usada para channel_id no response = '{$errorChannelId}'");
                                        error_log("{$logPrefix} RETURN_POINT=B: origem da variável = " . (!empty($originalChannelIdFromPost) ? 'originalChannelIdFromPost' : (!empty($channelId) ? 'channelId' : 'pixel12digital (fallback)')));
                                        error_log("{$logPrefix} RETURN_POINT=B: originalChannelIdFromPost = " . ($originalChannelIdFromPost ?: 'NULL'));
                                        error_log("{$logPrefix} RETURN_POINT=B: channelId = " . ($channelId ?: 'NULL'));
                                        error_log("{$logPrefix} RETURN_POINT=B: foundChannelId do banco = " . ($foundChannelId ?? 'NULL'));
                                        error_log("{$logPrefix} RETURN_POINT=B: foundSessionId do banco = " . ($foundSessionId ?? 'NULL'));
                                        error_log("{$logPrefix} ===== FIM RETURN_POINT=B =====");
                                        
                                        error_log("[CommunicationHub::send] ❌ Retornando erro com channel_id ORIGINAL do POST: '{$errorChannelId}' (originalChannelIdFromPost: " . ($originalChannelIdFromPost ?: 'NULL') . ", channelId: " . ($channelId ?: 'NULL') . ")");
                                        $this->json([
                                            'success' => false, 
                                            'error' => "Canal '{$errorChannelId}' não encontrado ou não habilitado. Verifique se o canal está cadastrado e habilitado.",
                                            'error_code' => 'CHANNEL_NOT_FOUND',
                                            'channel_id' => $errorChannelId,
                                            'request_id' => $requestId
                                        ], 400);
                                        return;
                                    }
                                } else {
                                    error_log("[CommunicationHub::send] ❌ Canal do banco também não passou na validação e não tem session_id");
                                    // Retorna erro com o channel_id ORIGINAL do POST (preservado no início)
                                    // FORÇA uso do originalChannelIdFromPost se disponível, senão usa channelId do POST
                                    $errorChannelId = !empty($originalChannelIdFromPost) ? $originalChannelIdFromPost : (!empty($channelId) ? $channelId : 'pixel12digital');
                                    // ===== OBJETIVO 4: LOG ANTES RETORNO CHANNEL_NOT_FOUND (RETURN_POINT=C) =====
                                    error_log("{$logPrefix} ===== RETURN_POINT=C (CHANNEL_NOT_FOUND) =====");
                                    error_log("{$logPrefix} RETURN_POINT=C: variável usada para channel_id no response = '{$errorChannelId}'");
                                    error_log("{$logPrefix} RETURN_POINT=C: origem da variável = " . (!empty($originalChannelIdFromPost) ? 'originalChannelIdFromPost' : (!empty($channelId) ? 'channelId' : 'pixel12digital (fallback)')));
                                    error_log("{$logPrefix} RETURN_POINT=C: originalChannelIdFromPost = " . ($originalChannelIdFromPost ?: 'NULL'));
                                    error_log("{$logPrefix} RETURN_POINT=C: channelId = " . ($channelId ?: 'NULL'));
                                    error_log("{$logPrefix} RETURN_POINT=C: foundChannels encontrados = " . (count($foundChannels ?? []) > 0 ? json_encode($foundChannels) : 'NENHUM'));
                                    error_log("{$logPrefix} ===== FIM RETURN_POINT=C =====");
                                    
                                    error_log("[CommunicationHub::send] ❌ Retornando erro com channel_id ORIGINAL do POST: '{$errorChannelId}' (originalChannelIdFromPost: " . ($originalChannelIdFromPost ?: 'NULL') . ", channelId: " . ($channelId ?: 'NULL') . ")");
                                    $this->json([
                                        'success' => false, 
                                        'error' => "Canal '{$errorChannelId}' não encontrado ou não habilitado. Verifique se o canal está cadastrado e habilitado.",
                                        'error_code' => 'CHANNEL_NOT_FOUND',
                                        'channel_id' => $errorChannelId,
                                        'request_id' => $requestId
                                    ], 400);
                                    return;
                                }
                            } else {
                                // Nenhum canal encontrado no banco
                                error_log("[CommunicationHub::send] ❌ Nenhum canal encontrado no banco para: '{$channelId}' (normalized: '{$normalized}')");
                                // Retorna erro com o channel_id ORIGINAL do POST (preservado no início)
                                // FORÇA uso do originalChannelIdFromPost se disponível, senão usa channelId do POST
                                $errorChannelId = !empty($originalChannelIdFromPost) ? $originalChannelIdFromPost : (!empty($channelId) ? $channelId : 'pixel12digital');
                                // ===== OBJETIVO 4: LOG ANTES RETORNO CHANNEL_NOT_FOUND (RETURN_POINT=D) =====
                                error_log("{$logPrefix} ===== RETURN_POINT=D (CHANNEL_NOT_FOUND) =====");
                                error_log("{$logPrefix} RETURN_POINT=D: variável usada para channel_id no response = '{$errorChannelId}'");
                                error_log("{$logPrefix} RETURN_POINT=D: origem da variável = " . (!empty($originalChannelIdFromPost) ? 'originalChannelIdFromPost' : (!empty($channelId) ? 'channelId' : 'pixel12digital (fallback)')));
                                error_log("{$logPrefix} RETURN_POINT=D: originalChannelIdFromPost = " . ($originalChannelIdFromPost ?: 'NULL'));
                                error_log("{$logPrefix} RETURN_POINT=D: channelId = " . ($channelId ?: 'NULL'));
                                error_log("{$logPrefix} RETURN_POINT=D: normalized = " . ($normalized ?? 'NULL'));
                                error_log("{$logPrefix} RETURN_POINT=D: nenhum canal encontrado no banco");
                                error_log("{$logPrefix} ===== FIM RETURN_POINT=D =====");
                                
                                error_log("[CommunicationHub::send] ❌ Retornando erro com channel_id ORIGINAL do POST: '{$errorChannelId}' (originalChannelIdFromPost: " . ($originalChannelIdFromPost ?: 'NULL') . ", channelId: " . ($channelId ?: 'NULL') . ")");
                                $this->json([
                                    'success' => false, 
                                    'error' => "Canal '{$errorChannelId}' não encontrado ou não habilitado. Verifique se o canal está cadastrado e habilitado.",
                                    'error_code' => 'CHANNEL_NOT_FOUND',
                                    'channel_id' => $errorChannelId,
                                    'request_id' => $requestId
                                ], 400);
                                return;
                            }
                        }
                        
                        // CRÍTICO: Usa o sessionId CANÔNICO validado (valor original do gateway)
                        // Este é o valor que será enviado ao gateway
                        $foundSessionId = trim($validatedChannel['session_id']);
                        $targetChannels = [$foundSessionId];
                        
                                // ===== OBJETIVO 3: LOG APÓS RESOLUÇÃO/FALLBACK (SUCESSO) =====
                        error_log("{$logPrefix} ===== RESOLUÇÃO CANAL SUCESSO (PRIORIDADE 1) =====");
                        error_log("{$logPrefix} RESOLUÇÃO: valor final de \$channelId = " . ($channelId ?: 'NULL'));
                        error_log("{$logPrefix} RESOLUÇÃO: valor de \$originalChannelIdFromPost = " . ($originalChannelIdFromPost ?: 'NULL'));
                        error_log("{$logPrefix} RESOLUÇÃO: valor de \$sessionId = " . ($foundSessionId ?: 'NULL'));
                        error_log("{$logPrefix} RESOLUÇÃO: channel.id = " . ($validatedChannel['id'] ?? 'NULL'));
                        error_log("{$logPrefix} RESOLUÇÃO: channel.channel_id/slug = " . ($validatedChannel['channel_id'] ?? 'NULL'));
                        error_log("{$logPrefix} RESOLUÇÃO: channel.tenant_id = " . ($validatedChannel['tenant_id'] ?? 'NULL'));
                        error_log("{$logPrefix} RESOLUÇÃO: channel.is_enabled = " . ($validatedChannel['is_enabled'] ?? 'NULL'));
                        error_log("{$logPrefix} ===== FIM RESOLUÇÃO =====");
                        
                        // LOG DE DIAGNÓSTICO: Informações do canal
                        // PATCH F+G: Secret e baseUrl sempre vêm do serviço/env, não do banco
                        error_log(sprintf(
                            "[CommunicationHub::send] ✅ SessionId validado: solicitado='%s' → canônico='%s' (será usado no gateway)",
                            $channelId,
                            $foundSessionId
                        ));
                    } else {
                        error_log("[CommunicationHub::send] PRIORIDADE 2: channel_id do POST não fornecido, buscando da conversa/thread...");
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
                                    SELECT ce.payload, ce.metadata, ce.tenant_id
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
                                
                                $eventStmtSql .= " ORDER BY ce.created_at DESC LIMIT 5";
                                
                                $eventStmt = $db->prepare($eventStmtSql);
                                $eventStmt->execute($eventParams);
                                $events = $eventStmt->fetchAll();
                                
                                // Tenta extrair channel_id de múltiplos eventos (mais robusto)
                                foreach ($events as $event) {
                                    if (empty($event['payload'])) continue;
                                    
                                    $payload = json_decode($event['payload'], true);
                                    $metadata = json_decode($event['metadata'] ?? '{}', true);
                                    
                                    // Extrai channel_id de múltiplas fontes (mesma lógica do webhook)
                                    $sessionIdFromEvent = $payload['sessionId'] 
                                        ?? $payload['session']['id'] 
                                        ?? $payload['session']['session'] 
                                        ?? $payload['data']['session']['id'] 
                                        ?? $payload['data']['session']['session'] 
                                        ?? $payload['channelId'] 
                                        ?? $payload['channel'] 
                                        ?? $payload['data']['channel'] 
                                        ?? $metadata['channel_id'] 
                                        ?? null;
                                    
                                    if ($sessionIdFromEvent) {
                                        $sessionIdFromEvent = trim((string) $sessionIdFromEvent);
                                        error_log("[CommunicationHub::send] SessionId encontrado nos eventos: {$sessionIdFromEvent}");
                                        
                                        // PATCH H2: Valida sessionId usando função que detecta schema
                                        $validatedChannel = $this->validateGatewaySessionId($sessionIdFromEvent, $tenantId, $db);
                                        
                                        if ($validatedChannel) {
                                            $targetChannels = [trim($validatedChannel['session_id'])];
                                            error_log("[CommunicationHub::send] ✅ SessionId validado dos eventos: {$validatedChannel['session_id']}");
                                            break; // Para no primeiro válido
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
                            $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp configurado no sistema', 'request_id' => $requestId], 400);
                            return;
                        }
                    }
                }
                
                // Valida que temos pelo menos um canal
                error_log("[CommunicationHub::send] ===== VALIDAÇÃO FINAL =====");
                error_log("[CommunicationHub::send] targetChannels antes da validação final: " . json_encode($targetChannels));
                error_log("[CommunicationHub::send] targetChannels está vazio: " . (empty($targetChannels) ? 'SIM' : 'NÃO'));
                error_log("[CommunicationHub::send] DEBUG STATE: channel={$channel}, channelId=" . ($channelId ?: 'NULL') . ", tenantId=" . ($tenantId ?: 'NULL') . ", threadId=" . ($threadId ?: 'NULL'));
                
                if (empty($targetChannels)) {
                    error_log("[CommunicationHub::send] ❌ ERRO 400: Nenhum canal identificado para envio");
                    error_log("[CommunicationHub::send] ❌ DEBUG: channel={$channel}, channelId=" . ($channelId ?: 'NULL') . ", tenantId=" . ($tenantId ?: 'NULL'));
                    error_log("[CommunicationHub::send] ❌ DEBUG: to={$to}, message=" . substr($message, 0, 50));
                    
                    // Log adicional: verifica se há canais no banco
                    $allChannelsCheck = $db->query("SELECT COUNT(*) as total FROM tenant_message_channels WHERE provider = 'wpp_gateway' AND is_enabled = 1")->fetch();
                    error_log("[CommunicationHub::send] ❌ DEBUG: Total de canais habilitados no banco: " . ($allChannelsCheck['total'] ?? 0));
                    
                    $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp identificado para envio', 'request_id' => $requestId, 'debug' => ['channel' => $channel, 'channel_id' => $channelId, 'tenant_id' => $tenantId]], 400);
                    return;
                }
                
                // Evita envio duplicado ao mesmo canal (ex.: mesmo canal em targetChannels mais de uma vez)
                $targetChannels = array_values(array_unique($targetChannels));
                if (count($targetChannels) > 1) {
                    error_log("[CommunicationHub::send] Canais após dedup: " . implode(', ', $targetChannels));
                }
                
                // Evita envio duplicado ao mesmo canal (mesmo canal não deve receber a mensagem mais de uma vez)
                $targetChannels = array_values(array_unique($targetChannels));
                
                error_log("[CommunicationHub::send] ✅ Canais alvo para envio: " . implode(', ', $targetChannels) . " (total: " . count($targetChannels) . ")");

                // Normaliza telefone
                $phoneNormalized = WhatsAppBillingService::normalizePhone($to);
                if (empty($phoneNormalized)) {
                    $this->json(['success' => false, 'error' => 'Telefone inválido', 'request_id' => $requestId], 400);
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
                
                // Timeout maior para mídia (áudio/imagem/documento) - conversão e upload podem demorar
                $gatewayTimeout = in_array($messageType, ['audio', 'image', 'video', 'document']) ? 120 : 30;
                error_log("[CommunicationHub::send] Timeout do gateway: {$gatewayTimeout}s (tipo: {$messageType})");
                
                // INTEGRAÇÃO MULTI-PROVIDER: Detecta qual provider usar
                $useMetaAPI = false;
                $metaProvider = null;
                $useWhapiAPI = false;
                $whapiProvider = null;
                
                // PRIORIDADE 1: Se conversa veio via Meta Official API, responde pelo mesmo canal
                if ($conversationProviderType === 'meta_official') {
                    error_log("[CommunicationHub::send] Conversa veio via Meta Official API - tentando usar Meta para responder");
                    try {
                        $metaProvider = WhatsAppProviderFactory::getProvider('meta_official', $tenantId);
                        if ($metaProvider instanceof \PixelHub\Integrations\WhatsApp\MetaOfficialProvider) {
                            $useMetaAPI = true;
                            error_log("[CommunicationHub::send] ✅ Meta Official API provider obtido com sucesso");
                        }
                    } catch (\Exception $e) {
                        error_log("[CommunicationHub::send] ❌ Erro ao obter Meta provider: " . $e->getMessage() . " - usando fallback");
                    }
                }
                
                // PRIORIDADE 2: Se não é Meta, verifica se Whapi.Cloud é o provider padrão
                if (!$useMetaAPI) {
                    try {
                        // Se channel_id corresponde a um session_name Whapi, usa aquele canal específico
                        if (!empty($channelId) && WhatsAppProviderFactory::getWhapiConfigBySession($channelId)) {
                            $defaultProvider = WhatsAppProviderFactory::getWhapiProviderBySession($channelId);
                            error_log("[CommunicationHub::send] Usando Whapi channel específico: {$channelId}");
                        } else {
                            $defaultProvider = WhatsAppProviderFactory::getProvider(null, $tenantId);
                        }
                        if ($defaultProvider instanceof WhapiCloudProvider) {
                            $useWhapiAPI = true;
                            $whapiProvider = $defaultProvider;
                            error_log("[CommunicationHub::send] ✅ Usando Whapi.Cloud como provider padrão");
                        }
                    } catch (\Exception $e) {
                        error_log("[CommunicationHub::send] Erro ao obter provider padrão: " . $e->getMessage());
                    }
                }
                
                // PRIORIDADE 3: Fallback para WPPConnect (legado)
                $gateway = null;
                if (!$useMetaAPI && !$useWhapiAPI) {
                    $gateway = $this->resolveWhatsAppProvider($tenantId, $channelId, $conversationProviderType);
                    
                    // Aplica configurações específicas (timeout, request_id)
                    if ($baseUrl || $secret || $gatewayTimeout !== 30) {
                        $gateway = new WhatsAppGatewayClient($baseUrl, $secret, $gatewayTimeout);
                    }
                    $gateway->setRequestId($requestId);
                }
                
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
                    // CRÍTICO: Normaliza channelId para garantir que sempre use ID técnico (sem espaços)
                    // Se o channelId tem espaços ou caracteres especiais, tenta validar e obter o session_id canônico
                    $originalChannelId = $targetChannelId;
                    $needsNormalization = preg_match('/\s/', $targetChannelId) || 
                                         $targetChannelId !== strtolower($targetChannelId) ||
                                         preg_match('/[A-Z]/', $targetChannelId);
                    
                    if ($needsNormalization) {
                        error_log("[CommunicationHub::send] ⚠️ ChannelId precisa normalização: '{$originalChannelId}' (tem espaços ou maiúsculas)");
                        
                        // Tenta obter o session_id técnico via validação
                        $validatedChannel = $this->validateGatewaySessionId($targetChannelId, $tenantId, $db);
                        if ($validatedChannel && !empty($validatedChannel['session_id'])) {
                            $targetChannelId = trim($validatedChannel['session_id']);
                            error_log("[CommunicationHub::send] ✅ ChannelId normalizado via validação: '{$originalChannelId}' → '{$targetChannelId}'");
                        } else {
                            // Fallback: normaliza removendo espaços e convertendo para lowercase
                            $targetChannelId = strtolower(preg_replace('/\s+/', '', trim($targetChannelId)));
                            error_log("[CommunicationHub::send] ⚠️ ChannelId normalizado (fallback): '{$originalChannelId}' → '{$targetChannelId}'");
                            error_log("[CommunicationHub::send] ⚠️ ATENÇÃO: Validação não encontrou canal, usando normalização básica. Pode não funcionar se o gateway não aceitar este formato.");
                        }
                    } else {
                        error_log("[CommunicationHub::send] ✅ ChannelId já está normalizado: '{$targetChannelId}'");
                    }
                    
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
                    
                    // CORREÇÃO: Verificação de status é apenas informativa (NÃO-BLOQUEANTE)
                    // Não bloqueia envio - deixa o gateway retornar o erro real se houver problema
                    // Isso evita falsos positivos quando o gateway está temporariamente indisponível
                    // SKIP para Meta API e Whapi.Cloud (não usam gateway WPPConnect)
                    if (!$useMetaAPI && !$useWhapiAPI) {
                    try {
                        $channelInfo = $gateway->getChannel($targetChannelId);
                        
                        if ($channelInfo['success']) {
                            $channelData = $channelInfo['raw'] ?? [];
                            $sessionStatus = $channelData['channel']['status'] 
                                ?? $channelData['channel']['connection'] 
                                ?? $channelData['status'] 
                                ?? $channelData['connection'] 
                                ?? null;
                            $isConnected = ($sessionStatus === 'connected' || $sessionStatus === 'open' || $channelData['connected'] ?? false);
                            
                            if ($isConnected) {
                                error_log("[CommunicationHub::send] ✅ Sessão conectada - permitindo envio");
                            } else {
                                error_log("[CommunicationHub::send] ⚠️ AVISO: Sessão pode estar desconectada (status: {$sessionStatus}), mas tentando enviar mesmo assim");
                            }
                        } else {
                            $errorMsg = $channelInfo['error'] ?? 'Erro desconhecido';
                            $statusCode = $channelInfo['status'] ?? 'N/A';
                            
                            // Só bloqueia se for erro crítico de autenticação (401)
                            // 404 pode ser temporário ou o gateway pode aceitar mesmo assim
                            if ($statusCode === 401) {
                                error_log("[CommunicationHub::send] ⚠️ ERRO DE AUTENTICAÇÃO (401) - bloqueando envio");
                                $unauthEntry = [
                                    'channel_id' => $targetChannelId,
                                    'success' => false,
                                    'error' => 'Erro de autenticação com o gateway',
                                    'error_code' => 'UNAUTHORIZED',
                                    'request_id' => $requestId,
                                ];
                                if (!empty($channelInfo['resp_headers_preview'])) {
                                    $unauthEntry['resp_headers_preview'] = $channelInfo['resp_headers_preview'];
                                }
                                if (isset($channelInfo['body_preview'])) {
                                    $unauthEntry['body_preview'] = $channelInfo['body_preview'];
                                }
                                if (!empty($channelInfo['secret_sent'])) {
                                    $unauthEntry['secret_sent'] = $channelInfo['secret_sent'];
                                }
                                if (isset($channelInfo['effective_url']) && $channelInfo['effective_url'] !== '') {
                                    $unauthEntry['effective_url'] = $channelInfo['effective_url'];
                                }
                                $sendResults[] = $unauthEntry;
                                $errors[] = "{$targetChannelId}: Erro de autenticação";
                                continue;
                            } else {
                                // Para 404 ou outros erros, apenas loga mas permite tentar enviar
                                // O gateway retornará o erro real se o canal não existir
                                error_log("[CommunicationHub::send] ⚠️ AVISO: Verificação de canal falhou ({$errorMsg}), mas tentando enviar mesmo assim (pode ser temporário)");
                            }
                        }
                    } catch (\Exception $e) {
                        // Se a verificação falhar por exceção, apenas loga e continua
                        error_log("[CommunicationHub::send] ⚠️ AVISO: Exceção ao verificar canal: " . $e->getMessage() . " - tentando enviar mesmo assim");
                    }
                    } // Fecha if (!$useMetaAPI)
                    
                    // Envia via gateway usando valor CANÔNICO (case-sensitive)
                    if ($isDev) {
                        error_log("[CommunicationHub::send] Enviando para gateway com sessionId (canônico): {$targetChannelId}, type: {$messageType}");
                    }
                    
                    // ===== DISPATCH: Roteia para o provider correto =====
                    $audioOptions = [];

                    if ($useWhapiAPI && $whapiProvider) {
                        // ─── WHAPI.CLOUD: Todos os tipos de mensagem ───────────────────────
                        // Validar se número tem WhatsApp antes de enviar (apenas novas conversas)
                        if (empty($threadId)) {
                            $waValidation = \PixelHub\Services\SdrDispatchService::validatePhoneNumber($phoneNormalized, $targetChannelId);
                            if (!$waValidation['valid'] && ($waValidation['status'] ?? '') !== 'error') {
                                $sendResults[] = ['channel_id' => $targetChannelId, 'success' => false, 'error' => 'Número sem WhatsApp', 'no_whatsapp' => true];
                                $errors[] = "{$targetChannelId}: Número sem WhatsApp";
                                continue;
                            }
                        }
                        // Whapi aceita base64 diretamente e auto-converte áudio para OGG/Opus
                        error_log("[CommunicationHub::send] 📱 Enviando via Whapi.Cloud type={$messageType} to={$phoneNormalized}");

                        if ($messageType === 'audio') {
                            $b64 = $base64Ptt;
                            $pos = stripos($b64, 'base64,');
                            if ($pos !== false) $b64 = substr($b64, $pos + 7);
                            $b64 = trim($b64);
                            $bin = base64_decode($b64, true);
                            if ($bin === false || strlen($bin) < 2000) {
                                $sendResults[] = ['channel_id' => $targetChannelId, 'success' => false, 'error' => 'Áudio inválido ou muito pequeno'];
                                $errors[] = "{$targetChannelId}: Áudio inválido";
                                continue;
                            }
                            // Whapi auto-converte WebM→OGG/Opus, envia base64 diretamente
                            $result = $whapiProvider->sendAudio($phoneNormalized, $b64);
                            error_log("[CommunicationHub::send] Whapi audio response: success=" . ($result['success'] ? 'true' : 'false'));

                        } elseif ($messageType === 'image') {
                            $b64Img = $base64Image;
                            $pos = stripos($b64Img, 'base64,');
                            if ($pos !== false) $b64Img = substr($b64Img, $pos + 7);
                            $b64Img = trim($b64Img);
                            $binImg = base64_decode($b64Img, true);
                            if ($binImg === false || strlen($binImg) < 1000) {
                                $sendResults[] = ['channel_id' => $targetChannelId, 'success' => false, 'error' => 'Imagem inválida'];
                                continue;
                            }
                            $result = $whapiProvider->sendImage($phoneNormalized, $b64Img, $caption ?: null);
                            $sentMediaData = ['type' => 'image', 'binary' => $binImg, 'mimeType' => 'image/jpeg', 'caption' => $caption];
                            error_log("[CommunicationHub::send] Whapi image response: success=" . ($result['success'] ? 'true' : 'false'));

                        } elseif ($messageType === 'document') {
                            $b64Doc = $base64Document;
                            $pos = stripos($b64Doc, 'base64,');
                            if ($pos !== false) $b64Doc = substr($b64Doc, $pos + 7);
                            $b64Doc = trim($b64Doc);
                            $binDoc = base64_decode($b64Doc, true);
                            if ($binDoc === false || strlen($binDoc) < 100) {
                                $sendResults[] = ['channel_id' => $targetChannelId, 'success' => false, 'error' => 'Documento inválido'];
                                continue;
                            }
                            $result = $whapiProvider->sendDocument($phoneNormalized, $b64Doc, $fileName, $caption ?: null);
                            $sentMediaData = ['type' => 'document', 'binary' => $binDoc, 'mimeType' => 'application/octet-stream', 'fileName' => $fileName, 'caption' => $caption];
                            error_log("[CommunicationHub::send] Whapi document response: success=" . ($result['success'] ? 'true' : 'false'));

                        } else {
                            // Texto
                            $result = $whapiProvider->sendText($phoneNormalized, $message);
                            error_log("[CommunicationHub::send] Whapi text response: success=" . ($result['success'] ? 'true' : 'false'));
                        }

                    } elseif ($messageType === 'audio') {
                        // ─── WPPCONNECT: Áudio ────────────────────────────────────────────
                        $audioStartTime = microtime(true);
                        error_log("[CommunicationHub::send] ===== INÍCIO PROCESSAMENTO DE ÁUDIO ======");
                        error_log("[CommunicationHub::send] Timestamp: " . date('Y-m-d H:i:s.u'));
                        error_log("[CommunicationHub::send] channel_id: {$targetChannelId}, to: {$phoneNormalized}");
                        
                        $b64 = $base64Ptt;
                        $b64OriginalLength = strlen($b64);
                        error_log("[CommunicationHub::send] Base64 original length: {$b64OriginalLength} bytes");
                        
                        $pos = stripos($b64, 'base64,');
                        if ($pos !== false) {
                            $b64 = substr($b64, $pos + 7);
                            error_log("[CommunicationHub::send] Removido prefixo data:audio, novo length: " . strlen($b64) . " bytes");
                        }
                        $b64 = trim($b64);
                        
                        $decodeStartTime = microtime(true);
                        $bin = base64_decode($b64, true);
                        $decodeTime = (microtime(true) - $decodeStartTime) * 1000;
                        error_log("[CommunicationHub::send] Base64 decode concluído em {$decodeTime}ms");
                        
                        if ($bin === false || strlen($bin) < 2000) {
                            error_log("[CommunicationHub::send] ❌ Áudio inválido ou muito pequeno: " . ($bin === false ? 'decode failed' : strlen($bin) . ' bytes'));
                            $sendResults[] = [
                                'channel_id' => $targetChannelId,
                                'success' => false,
                                'error' => 'Invalid or too small audio payload (need real OGG/Opus, not only header)',
                            ];
                            $errors[] = "{$targetChannelId}: Invalid or too small audio payload";
                            continue;
                        }
                        
                        $binSize = strlen($bin);
                        $binSizeKB = round($binSize / 1024, 2);
                        error_log("[CommunicationHub::send] Áudio binário válido: {$binSize} bytes ({$binSizeKB} KB)");
                        
                        $opusCheckStartTime = microtime(true);
                        $hasOpusHead = strpos($bin, 'OpusHead') !== false;
                        $isWebM = strpos($bin, 'webm') !== false || strpos($bin, 'matroska') !== false;
                        $isOGG = strpos($bin, 'OggS') === 0;
                        $opusCheckTime = (microtime(true) - $opusCheckStartTime) * 1000;
                        
                        $detectedFormat = 'DESCONHECIDO';
                        if ($isOGG && $hasOpusHead) $detectedFormat = 'OGG/Opus';
                        elseif ($isWebM && $hasOpusHead) $detectedFormat = 'WebM/Opus';
                        elseif ($isWebM) $detectedFormat = 'WebM (sem OpusHead detectado)';
                        elseif ($isOGG) $detectedFormat = 'OGG (sem OpusHead detectado)';
                        elseif ($hasOpusHead) $detectedFormat = 'Opus (container desconhecido)';
                        
                        error_log("{$logPrefix} bytes_input=" . strlen($bin) . " mime_detected=" . $detectedFormat);
                        error_log("[CommunicationHub::send] Formato detectado: {$detectedFormat}");
                        
                        if (function_exists('pixelhub_log')) {
                            pixelhub_log("[CommunicationHub::send] Formato de áudio detectado: {$detectedFormat}, tamanho: {$binSizeKB} KB");
                        }
                        
                        $audioOptions = [];
                        if ($isWebM) {
                            $conv = $this->convertWebMToOggBase64($bin, $targetChannelId);
                            if ($conv['ok'] && !empty($conv['base64'])) {
                                $b64 = $conv['base64'];
                                $bin = base64_decode($b64, true);
                                error_log("[CommunicationHub::send] Áudio convertido para OGG/Opus no Hostmidia, novo tamanho: " . strlen($bin) . " bytes");
                            } else {
                                $fallbackReasons = ['EXEC_DISABLED', 'FFMPEG_FAILED', 'FFMPEG_OUTPUT_INVALID', 'TEMP_WRITE_FAILED', 'OGG_READ_FAILED'];
                                if (in_array($conv['reason'] ?? '', $fallbackReasons, true)) {
                                    error_log("[CommunicationHub::send] Conversão Hostmidia falhou ({$conv['reason']}), fallback: enviando WebM ao gateway para conversão na VPS");
                                    $audioOptions = ['audio_mime' => 'audio/webm', 'is_voice' => true];
                                } else {
                                    $errMsg = 'Áudio em WebM. Servidor não converteu (' . ($conv['reason'] ?? 'UNKNOWN') . ').';
                                    if (!empty($conv['stderr_preview'])) {
                                        $errMsg .= ' Detalhe: ' . substr((string)$conv['stderr_preview'], 0, 200);
                                    }
                                    $sendResults[] = [
                                        'channel_id' => $targetChannelId,
                                        'success' => false,
                                        'error' => $errMsg,
                                        'error_code' => 'AUDIO_CONVERT_FAILED',
                                        'origin' => 'hostmidia',
                                        'reason' => $conv['reason'] ?? 'UNKNOWN',
                                        'stderr_preview' => substr((string)($conv['stderr_preview'] ?? ''), 0, 500),
                                    ];
                                    $errors[] = "{$targetChannelId}: " . $errMsg;
                                    continue;
                                }
                            }
                        }
                        
                        error_log("[CommunicationHub::send] ✅ Validações passadas, chamando gateway->sendAudioBase64Ptt()");
                        $gatewayCallStartTime = microtime(true);
                        
                        $result = $gateway->sendAudioBase64Ptt(
                            $targetChannelId,
                            $phoneNormalized,
                            $b64,
                            [
                                'sent_by' => Auth::user()['id'] ?? null,
                                'sent_by_name' => Auth::user()['name'] ?? null
                            ],
                            $audioOptions
                        );
                        
                        $gatewayCallTime = (microtime(true) - $gatewayCallStartTime) * 1000;
                        $totalAudioTime = (microtime(true) - $audioStartTime) * 1000;
                        error_log("[CommunicationHub::send] Chamada ao gateway concluída em {$gatewayCallTime}ms");
                        error_log("[CommunicationHub::send] Tempo total de processamento de áudio: {$totalAudioTime}ms");
                        error_log("[CommunicationHub::send] ===== FIM PROCESSAMENTO DE ÁUDIO ======");

                    } elseif ($messageType === 'image') {
                        // ─── WPPCONNECT: Imagem ───────────────────────────────────────────
                        error_log("[CommunicationHub::send] 🖼️ Enviando imagem para {$targetChannelId}");
                        
                        $b64Img = $base64Image;
                        $pos = stripos($b64Img, 'base64,');
                        if ($pos !== false) $b64Img = substr($b64Img, $pos + 7);
                        $b64Img = trim($b64Img);
                        
                        $binImg = base64_decode($b64Img, true);
                        if ($binImg === false || strlen($binImg) < 1000) {
                            error_log("[CommunicationHub::send] ❌ Imagem inválida ou muito pequena");
                            $sendResults[] = ['channel_id' => $targetChannelId, 'success' => false, 'error' => 'Imagem inválida ou muito pequena'];
                            continue;
                        }
                        
                        $result = $gateway->sendImage($targetChannelId, $phoneNormalized, $b64Img, null, $caption, [
                            'sent_by' => Auth::user()['id'] ?? null,
                            'sent_by_name' => Auth::user()['name'] ?? null
                        ]);
                        
                        $sentMediaData = ['type' => 'image', 'binary' => $binImg, 'mimeType' => 'image/jpeg', 'caption' => $caption];
                        
                    } elseif ($messageType === 'document') {
                        // ─── WPPCONNECT: Documento ────────────────────────────────────────
                        error_log("[CommunicationHub::send] 📄 Enviando documento para {$targetChannelId}");
                        
                        $b64Doc = $base64Document;
                        $pos = stripos($b64Doc, 'base64,');
                        if ($pos !== false) $b64Doc = substr($b64Doc, $pos + 7);
                        $b64Doc = trim($b64Doc);
                        
                        $binDoc = base64_decode($b64Doc, true);
                        if ($binDoc === false || strlen($binDoc) < 100) {
                            error_log("[CommunicationHub::send] ❌ Documento inválido ou muito pequeno");
                            $sendResults[] = ['channel_id' => $targetChannelId, 'success' => false, 'error' => 'Documento inválido ou muito pequeno'];
                            continue;
                        }
                        
                        $result = $gateway->sendDocument($targetChannelId, $phoneNormalized, $b64Doc, null, $fileName, $caption, [
                            'sent_by' => Auth::user()['id'] ?? null,
                            'sent_by_name' => Auth::user()['name'] ?? null
                        ]);
                        
                        $sentMediaData = ['type' => 'document', 'binary' => $binDoc, 'mimeType' => 'application/octet-stream', 'fileName' => $fileName, 'caption' => $caption];
                        
                    } else {
                        // ─── TEXTO: Meta Official API ou WPPConnect ───────────────────────
                        if ($useMetaAPI && $metaProvider) {
                            error_log("[CommunicationHub::send] 📤 Enviando via Meta Official API para {$phoneNormalized}");
                            $result = $metaProvider->sendText($phoneNormalized, $message, [
                                'sent_by' => Auth::user()['id'] ?? null,
                                'sent_by_name' => Auth::user()['name'] ?? null
                            ]);
                            error_log("[CommunicationHub::send] Meta API response: " . json_encode($result));
                        } else {
                            // WPPConnect
                            $result = $gateway->sendText($targetChannelId, $phoneNormalized, $message, [
                                'sent_by' => Auth::user()['id'] ?? null,
                                'sent_by_name' => Auth::user()['name'] ?? null
                            ]);
                        }
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
                        
                        // Resolve nome do contato (tenant) para incluir no evento outbound
                        $contactNameForEvent = null;
                        if ($tenantId) {
                            try {
                                $tnStmt = $db->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
                                $tnStmt->execute([$tenantId]);
                                $tnRow = $tnStmt->fetch(\PDO::FETCH_ASSOC);
                                if ($tnRow) $contactNameForEvent = $tnRow['name'];
                            } catch (\Exception $e) { /* ignora */ }
                        }
                        
                        // Cria evento de envio para este canal
                        $eventPayload = [
                            'to' => $phoneNormalized,
                            'timestamp' => time(),
                            'channel_id' => $targetChannelId,
                            'type' => $messageType
                        ];
                        if ($contactNameForEvent) {
                            $eventPayload['contact_name'] = $contactNameForEvent;
                        }
                        
                        if ($messageType === 'audio') {
                            $eventPayload['message'] = [
                                'to' => $phoneNormalized,
                                'type' => 'audio',
                                'timestamp' => time()
                            ];
                            // Não inclui base64Ptt no payload do evento (muito grande)
                        } elseif ($messageType === 'image') {
                            $eventPayload['message'] = [
                                'to' => $phoneNormalized,
                                'type' => 'image',
                                'timestamp' => time()
                            ];
                            // Caption vai no text se existir
                            if (!empty($caption)) {
                                $eventPayload['text'] = $caption;
                                $eventPayload['message']['text'] = $caption;
                            }
                        } elseif ($messageType === 'document') {
                            $eventPayload['message'] = [
                                'to' => $phoneNormalized,
                                'type' => 'document',
                                'timestamp' => time(),
                                'fileName' => $fileName ?? 'document'
                            ];
                            // Caption vai no text se existir
                            if (!empty($caption)) {
                                $eventPayload['text'] = $caption;
                                $eventPayload['message']['text'] = $caption;
                            }
                        } else {
                            $eventPayload['message'] = [
                                'to' => $phoneNormalized,
                                'text' => $message,
                                'timestamp' => time()
                            ];
                            $eventPayload['text'] = $message;
                        }
                        
                        // Idempotência: inclui message_id no payload para deduplicar com webhook
                        $gatewayMessageId = $result['message_id'] ?? null;
                        if ($gatewayMessageId !== null) {
                            $eventPayload['id'] = $gatewayMessageId;
                            $eventPayload['message_id'] = $gatewayMessageId;
                        }
                        
                        // CORREÇÃO: Normaliza channel_id no metadata para garantir busca consistente
                        // Eventos inbound têm metadata.channel_id normalizado, outbound precisa ter também
                        $normalizedChannelId = strtolower(str_replace(' ', '', $targetChannelId));
                        
                        // Nova conversa com tenant selecionado no modal: confia na escolha do usuário
                        $metadata = [
                            'sent_by' => Auth::user()['id'] ?? null,
                            'sent_by_name' => Auth::user()['name'] ?? null,
                            'message_id' => $result['message_id'] ?? null,
                            'forwarded' => count($targetChannels) > 1 ? true : null,
                            'channel_id' => $normalizedChannelId
                        ];
                        if (empty($threadId) && $tenantId !== null) {
                            $metadata['explicit_tenant_selection'] = true;
                        }
                        
                        $eventId = EventIngestionService::ingest([
                            'event_type' => 'whatsapp.outbound.message',
                            'source_system' => 'pixelhub_operator',
                            'payload' => $eventPayload,
                            'tenant_id' => $tenantId,
                            'metadata' => $metadata
                        ]);

                        // Atualiza contact_name na conversa se veio do modal de prospecção
                        if (!empty($contactNameFromPost)) {
                            try {
                                $digits = preg_replace('/[^0-9]/', '', $phoneNormalized);
                                $chatId = $digits . '@s.whatsapp.net';
                                $db->prepare("
                                    UPDATE conversations
                                    SET contact_name = ?
                                    WHERE contact_external_id IN (?, ?)
                                      AND channel_id = ?
                                      AND (contact_name IS NULL OR contact_name = '' OR contact_name = 'Contato Desconhecido')
                                ")->execute([$contactNameFromPost, $chatId, $digits, $normalizedChannelId]);
                            } catch (\Exception $cnEx) {
                                error_log("[CommunicationHub::send] Falha ao atualizar contact_name: " . $cnEx->getMessage());
                            }
                        }
                        
                        // ===== SALVAR MÍDIA OUTBOUND (áudio, imagem, documento) =====
                        // Isso permite que o player/preview funcione para mensagens enviadas
                        $mediaSaveStarted = false;
                        
                        // ÁUDIO
                        if ($messageType === 'audio' && $eventId && !empty($b64)) {
                            $mediaSaveStarted = true;
                            error_log("[CommunicationHub::send] 🔊 AUDIO MEDIA SAVE: Iniciando salvamento...");
                            try {
                                $audioData = $bin;
                                if ($audioData !== false && strlen($audioData) > 0) {
                                    $subDir = date('Y/m/d');
                                    $mediaDir = __DIR__ . '/../../storage/whatsapp-media';
                                    if ($tenantId) $mediaDir .= '/tenant-' . $tenantId;
                                    $mediaDir .= '/' . $subDir;
                                    
                                    if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
                                    
                                    $audioMime = $audioOptions['audio_mime'] ?? 'audio/ogg';
                                    $audioExt  = (strpos($audioMime, 'webm') !== false) ? '.webm' : '.ogg';
                                    $mediaFileName = bin2hex(random_bytes(16)) . $audioExt;
                                    $storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $mediaFileName;
                                    $fullPath = $mediaDir . DIRECTORY_SEPARATOR . $mediaFileName;
                                    
                                    $writeResult = file_put_contents($fullPath, $audioData);
                                    if ($writeResult !== false) {
                                        $fileSize = filesize($fullPath);
                                        $mediaStmt = $db->prepare("
                                            INSERT INTO communication_media 
                                            (event_id, media_id, media_type, mime_type, stored_path, file_name, file_size, created_at, updated_at)
                                            VALUES (?, ?, 'audio', ?, ?, ?, ?, NOW(), NOW())
                                        ");
                                        $mediaStmt->execute([$eventId, $result['message_id'] ?? $eventId, $audioMime, $storedPath, $mediaFileName, $fileSize]);
                                        error_log("[CommunicationHub::send] ✅ Mídia de áudio outbound salva: event_id={$eventId}, mime={$audioMime}, path={$storedPath}");
                                    }
                                }
                            } catch (\Exception $audioSaveEx) {
                                error_log("[CommunicationHub::send] ⚠️ Erro ao salvar mídia de áudio: " . $audioSaveEx->getMessage());
                            }
                        }
                        
                        // IMAGEM
                        if ($messageType === 'image' && $eventId && isset($sentMediaData)) {
                            $mediaSaveStarted = true;
                            error_log("[CommunicationHub::send] 🖼️ IMAGE MEDIA SAVE: Iniciando salvamento...");
                            try {
                                $imgData = $sentMediaData['binary'] ?? null;
                                if ($imgData && strlen($imgData) > 0) {
                                    $subDir = date('Y/m/d');
                                    $mediaDir = __DIR__ . '/../../storage/whatsapp-media';
                                    if ($tenantId) $mediaDir .= '/tenant-' . $tenantId;
                                    $mediaDir .= '/' . $subDir;
                                    
                                    if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
                                    
                                    // Detecta extensão pelo magic bytes
                                    $ext = 'jpg';
                                    $mimeType = 'image/jpeg';
                                    if (substr($imgData, 0, 8) === "\x89PNG\r\n\x1a\n") {
                                        $ext = 'png';
                                        $mimeType = 'image/png';
                                    } elseif (substr($imgData, 0, 4) === 'GIF8') {
                                        $ext = 'gif';
                                        $mimeType = 'image/gif';
                                    } elseif (substr($imgData, 0, 4) === 'RIFF' && substr($imgData, 8, 4) === 'WEBP') {
                                        $ext = 'webp';
                                        $mimeType = 'image/webp';
                                    }
                                    
                                    $mediaFileName = bin2hex(random_bytes(16)) . '.' . $ext;
                                    $storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $mediaFileName;
                                    $fullPath = $mediaDir . DIRECTORY_SEPARATOR . $mediaFileName;
                                    
                                    $writeResult = file_put_contents($fullPath, $imgData);
                                    if ($writeResult !== false) {
                                        $fileSize = filesize($fullPath);
                                        $mediaStmt = $db->prepare("
                                            INSERT INTO communication_media 
                                            (event_id, media_id, media_type, mime_type, stored_path, file_name, file_size, created_at, updated_at)
                                            VALUES (?, ?, 'image', ?, ?, ?, ?, NOW(), NOW())
                                        ");
                                        $mediaStmt->execute([$eventId, $result['message_id'] ?? $eventId, $mimeType, $storedPath, $mediaFileName, $fileSize]);
                                        error_log("[CommunicationHub::send] ✅ Mídia de imagem outbound salva: event_id={$eventId}, path={$storedPath}");
                                    } else {
                                        error_log("[CommunicationHub::send] ❌ FALHA ao salvar imagem: path={$fullPath}, error=" . error_get_last()['message'] ?? 'desconhecido');
                                    }
                                }
                            } catch (\Exception $imgSaveEx) {
                                error_log("[CommunicationHub::send] ⚠️ Erro ao salvar mídia de imagem: " . $imgSaveEx->getMessage());
                            }
                        }
                        
                        // DOCUMENTO
                        if ($messageType === 'document' && $eventId && isset($sentMediaData)) {
                            $mediaSaveStarted = true;
                            error_log("[CommunicationHub::send] 📄 DOCUMENT MEDIA SAVE: Iniciando salvamento...");
                            try {
                                $docData = $sentMediaData['binary'] ?? null;
                                $docFileName = $sentMediaData['fileName'] ?? 'document';
                                if ($docData && strlen($docData) > 0) {
                                    $subDir = date('Y/m/d');
                                    $mediaDir = __DIR__ . '/../../storage/whatsapp-media';
                                    if ($tenantId) $mediaDir .= '/tenant-' . $tenantId;
                                    $mediaDir .= '/' . $subDir;
                                    
                                    if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);
                                    
                                    // Preserva extensão original
                                    $ext = pathinfo($docFileName, PATHINFO_EXTENSION) ?: 'bin';
                                    $mimeType = 'application/octet-stream';
                                    if ($ext === 'pdf') $mimeType = 'application/pdf';
                                    elseif (in_array($ext, ['doc', 'docx'])) $mimeType = 'application/msword';
                                    
                                    $storedFileName = bin2hex(random_bytes(16)) . '.' . $ext;
                                    $storedPath = 'whatsapp-media/' . ($tenantId ? "tenant-{$tenantId}/" : '') . $subDir . '/' . $storedFileName;
                                    $fullPath = $mediaDir . DIRECTORY_SEPARATOR . $storedFileName;
                                    
                                    $writeResult = file_put_contents($fullPath, $docData);
                                    if ($writeResult !== false) {
                                        $fileSize = filesize($fullPath);
                                        $mediaStmt = $db->prepare("
                                            INSERT INTO communication_media 
                                            (event_id, media_id, media_type, mime_type, stored_path, file_name, file_size, created_at, updated_at)
                                            VALUES (?, ?, 'document', ?, ?, ?, ?, NOW(), NOW())
                                        ");
                                        $mediaStmt->execute([$eventId, $result['message_id'] ?? $eventId, $mimeType, $storedPath, $docFileName, $fileSize]);
                                        error_log("[CommunicationHub::send] ✅ Mídia de documento outbound salva: event_id={$eventId}, path={$storedPath}");
                                    }
                                }
                            } catch (\Exception $docSaveEx) {
                                error_log("[CommunicationHub::send] ⚠️ Erro ao salvar mídia de documento: " . $docSaveEx->getMessage());
                            }
                        }
                        
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
                        $jsonError = $result['json_error'] ?? null;
                        $responsePreview = $result['response_preview'] ?? null;
                        $rawResponse = $result['raw'] ?? null;
                        
                        // Log completo da resposta raw quando há erro
                        if ($isDev && $rawResponse !== null) {
                            $rawForLog = is_array($rawResponse) ? $rawResponse : ['raw_type' => gettype($rawResponse), 'raw_value' => is_string($rawResponse) ? substr($rawResponse, 0, 1000) : $rawResponse];
                            // Remove base64Ptt se existir
                            if (is_array($rawForLog) && isset($rawForLog['base64Ptt'])) {
                                $rawForLog['base64Ptt'] = '[REMOVED - too large]';
                            }
                            error_log("[CommunicationHub::send] Resposta raw completa do gateway: " . json_encode($rawForLog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                            
                            // Tenta extrair mensagens de erro adicionais do raw
                            if (is_array($rawResponse)) {
                                $additionalErrors = [];
                                foreach (['error', 'message', 'error_message', 'details', 'data'] as $key) {
                                    if (isset($rawResponse[$key])) {
                                        $value = $rawResponse[$key];
                                        if (is_string($value)) {
                                            $additionalErrors[] = "{$key}: {$value}";
                                        } elseif (is_array($value) && isset($value['error'])) {
                                            $additionalErrors[] = "{$key}.error: " . (is_string($value['error']) ? $value['error'] : json_encode($value['error']));
                                        }
                                    }
                                }
                                if (!empty($additionalErrors)) {
                                    error_log("[CommunicationHub::send] Mensagens de erro adicionais do raw: " . implode(" | ", $additionalErrors));
                                }
                            }
                        }
                        
                        // Detecta erros específicos e melhora mensagens
                        $errorLower = strtolower($error);
                        
                        // Detecta erros de JSON/HTML do gateway
                        if ($errorCode === 'GATEWAY_HTML_ERROR' || $errorCode === 'GATEWAY_SERVER_ERROR' || $errorCode === 'EMPTY_RESPONSE') {
                            error_log("[CommunicationHub::send] ❌ Erro crítico do gateway: {$errorCode}");
                            if ($jsonError) {
                                error_log("[CommunicationHub::send] Erro JSON: {$jsonError}");
                            }
                            if ($responsePreview) {
                                error_log("[CommunicationHub::send] Preview da resposta: {$responsePreview}");
                            }
                            // Mantém a mensagem de erro já melhorada do gateway client
                        }
                        // Detecta SESSION_DISCONNECTED do gateway
                        elseif (strpos($errorLower, 'session') !== false && strpos($errorLower, 'disconnect') !== false) {
                            $errorCode = 'SESSION_DISCONNECTED';
                            $error = 'Sessão do WhatsApp desconectada. Verifique se a sessão está conectada no gateway.';
                        } 
                        // Detecta erros específicos do WPPConnect para áudio
                        elseif ($messageType === 'audio' && (stripos($error, 'WPPConnect') !== false || stripos($error, 'sendVoiceBase64') !== false || stripos($error, 'wppconnect') !== false)) {
                            $errorCode = $errorCode ?: 'WPPCONNECT_SEND_ERROR';
                            
                            // Timeout apenas quando a mensagem indica explicitamente (evita falso positivo com "30" solto)
                            $isTimeout = stripos($error, 'timeout') !== false || stripos($error, 'timed out') !== false
                                || stripos($error, '30000ms') !== false
                                || preg_match('/\b30\s*second/i', $error) === 1
                                || preg_match('/\b30s\b/i', $error) === 1
                                || stripos($error, '30 segundos') !== false;
                            if ($isTimeout) {
                                $errorCode = 'WPPCONNECT_TIMEOUT';
                                $error = 'O gateway WPPConnect está demorando mais de 30 segundos para processar o áudio. Isso pode acontecer se o áudio for muito grande ou se o gateway estiver sobrecarregado. Tente gravar um áudio mais curto (menos de 1 minuto) ou aguarde alguns minutos e tente novamente.';
                            } else {
                                // Preserva mensagem original se ela for específica (mais de 50 caracteres)
                                // Só substitui se for mensagem genérica muito curta
                                $isGenericError = (stripos($error, 'Erro ao enviar a mensagem') !== false || stripos($error, 'Failed to send') !== false) && strlen($error) < 50;
                                
                                if ($isGenericError) {
                                    $error = 'Falha ao enviar áudio via WPPConnect. Possíveis causas: sessão desconectada, formato de áudio inválido (o gateway espera OGG/Opus, mas foi enviado WebM/Opus), ou tamanho muito grande. Verifique os logs do gateway para mais detalhes.';
                                }
                                // Caso contrário, mantém a mensagem original do gateway
                            }
                        }
                        // Detecta timeout primeiro (evita classificar como AUDIO_TOO_LARGE por "muito grande" na mensagem)
                        if ($errorCode === 'TIMEOUT' || stripos($error, 'timeout') !== false) {
                            $errorCode = 'TIMEOUT';
                            if (!stripos($error, 'timeout')) {
                                $error = $messageType === 'audio'
                                    ? 'Timeout ao enviar áudio. O gateway pode estar sobrecarregado ou o arquivo muito grande.'
                                    : 'Timeout na requisição ao gateway. A mensagem pode ter sido enviada mesmo assim.';
                            }
                            
                            // ─── TIMEOUT RESILIENCE: Registra evento outbound para texto ───
                            // Para mensagens de TEXTO, o gateway provavelmente enviou a mensagem
                            // mas não respondeu a tempo. Registra no Inbox com flag delivery_uncertain
                            // para que a mensagem apareça na conversa.
                            // NÃO se aplica a mídia (áudio/imagem/vídeo) pois o upload pode ter falhado.
                            if ($messageType === 'text' && !empty($phoneNormalized)) {
                                try {
                                    $normalizedChannelId = strtolower(str_replace(' ', '', $targetChannelId));
                                    $timeoutEventPayload = [
                                        'to' => $phoneNormalized,
                                        'timestamp' => time(),
                                        'channel_id' => $targetChannelId,
                                        'type' => 'text',
                                        'message' => [
                                            'to' => $phoneNormalized,
                                            'text' => $message,
                                            'timestamp' => time()
                                        ],
                                        'text' => $message
                                    ];
                                    $timeoutMetadata = [
                                        'sent_by' => Auth::user()['id'] ?? null,
                                        'sent_by_name' => Auth::user()['name'] ?? null,
                                        'channel_id' => $normalizedChannelId,
                                        'delivery_uncertain' => true,
                                        'timeout_at' => date('Y-m-d H:i:s'),
                                        'request_id' => $requestId
                                    ];
                                    if (empty($threadId) && $tenantId !== null) {
                                        $timeoutMetadata['explicit_tenant_selection'] = true;
                                    }
                                    
                                    $timeoutEventId = EventIngestionService::ingest([
                                        'event_type' => 'whatsapp.outbound.message',
                                        'source_system' => 'pixelhub_operator',
                                        'payload' => $timeoutEventPayload,
                                        'tenant_id' => $tenantId,
                                        'metadata' => $timeoutMetadata
                                    ]);
                                    
                                    error_log("[CommunicationHub::send] ⚠️ TIMEOUT mas evento registrado com delivery_uncertain: event_id={$timeoutEventId}, to={$phoneNormalized}, request_id={$requestId}");
                                    
                                    // Marca como sucesso parcial para o frontend
                                    $hasAnySuccess = true;
                                    $sendResults[] = [
                                        'channel_id' => $targetChannelId,
                                        'success' => true,
                                        'event_id' => $timeoutEventId,
                                        'message_id' => null,
                                        'delivery_uncertain' => true
                                    ];
                                    continue; // Pula o bloco de erro abaixo
                                } catch (\Throwable $timeoutEx) {
                                    error_log("[CommunicationHub::send] Erro ao registrar evento timeout: " . $timeoutEx->getMessage());
                                    // Continua para o bloco de erro normal
                                }
                            }
                        }
                        // Detecta áudio muito grande (só se não for timeout)
                        elseif (stripos($error, 'AUDIO_TOO_LARGE') !== false || (stripos($error, 'muito grande') !== false && $messageType === 'audio')) {
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
                                'base64_length' => isset($b64) ? strlen($b64) : 'N/A',
                                'json_error' => $jsonError,
                                'response_preview' => $responsePreview
                            ], JSON_UNESCAPED_UNICODE));
                        }
                        
                        $errPayload = [
                            'channel_id' => $targetChannelId,
                            'success' => false,
                            'error' => $error,
                            'error_code' => $errorCode ?: ($result['error_code'] ?? 'GATEWAY_ERROR')
                        ];
                        if ($messageType === 'audio' && !empty($audioOptions['audio_mime'])) {
                            $errPayload['origin'] = 'gateway';
                            $errPayload['reason'] = $result['reason'] ?? $errorCode ?? 'UNKNOWN';
                            if (!empty($result['stderr_preview'])) {
                                $errPayload['stderr_preview'] = substr((string) $result['stderr_preview'], 0, 500);
                            }
                        }
                        if (!empty($result['gateway_html_error'])) {
                            $errPayload['gateway_html_error'] = $result['gateway_html_error'];
                        }
                        if (($result['error_code'] ?? '') === 'UNAUTHORIZED') {
                            if (!empty($result['resp_headers_preview'])) {
                                $errPayload['resp_headers_preview'] = $result['resp_headers_preview'];
                            }
                            if (isset($result['body_preview'])) {
                                $errPayload['body_preview'] = $result['body_preview'];
                            }
                            if (!empty($result['secret_sent'])) {
                                $errPayload['secret_sent'] = $result['secret_sent'];
                            }
                            if (isset($result['effective_url']) && $result['effective_url'] !== '') {
                                $errPayload['effective_url'] = $result['effective_url'];
                            }
                        }
                        if (($result['error_code'] ?? '') === 'TIMEOUT' && !empty($result['timeout_info'])) {
                            $errPayload['timeout_info'] = $result['timeout_info'];
                        }
                        $sendResults[] = $errPayload;
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
                        $payload = [
                            'success' => true,
                            'event_id' => $singleResult['event_id'],
                            'message_id' => $singleResult['message_id']
                        ];
                        // thread_id para Inbox abrir a conversa após "Nova Conversa" (evita reload)
                        $eventId = $singleResult['event_id'] ?? null;
                        if ($eventId) {
                            try {
                                $evStmt = $db->prepare("SELECT conversation_id FROM communication_events WHERE event_id = ? LIMIT 1");
                                $evStmt->execute([$eventId]);
                                $ev = $evStmt->fetch();
                                if ($ev && !empty($ev['conversation_id'])) {
                                    $payload['thread_id'] = 'whatsapp_' . $ev['conversation_id'];

                                    // ===== HISTÓRICO DA OPORTUNIDADE (enxuto) =====
                                    // Registra somente em caso de conversa vinculada a uma oportunidade
                                    try {
                                        $convId = (int) $ev['conversation_id'];
                                        $oppId = $this->resolveOpportunityIdForConversation($db, $convId);
                                        if (!empty($oppId)) {
                                            error_log("[Interaction] WhatsApp enviado - oppId encontrado: {$oppId}");
                                            
                                            // REMOVIDO: Não registra mais WhatsApp no histórico de negócio
                                            // OpportunityService::addInteractionHistory((int) $oppId, 'WhatsApp: Enviado', Auth::user()['id'] ?? null);
                                            
                                            // REMOVIDO: Sistema de interações desativado
                                            // \PixelHub\Services\OpportunityInteractionService::logWhatsApp(...)
                                        } else {
                                            error_log("[Interaction] WhatsApp enviado - oppId NÃO encontrado para convId: {$convId}");
                                        }
                                    } catch (\Throwable $hx) {
                                        // Não quebra envio se falhar histórico
                                    }
                                }
                            } catch (\Throwable $ex) {
                                // Não quebra se falhar
                            }
                        }
                        $this->json($payload);
                    } else {
                        // Retorna código HTTP apropriado baseado no error_code
                        $httpCode = 500;
                        if ($singleResult['error_code'] === 'SESSION_DISCONNECTED') {
                            $httpCode = 409; // Conflict
                        } elseif ($singleResult['error_code'] === 'UNAUTHORIZED' || $singleResult['error_code'] === 'CHANNEL_NOT_FOUND') {
                            $httpCode = 400; // Bad Request
                        } elseif ($singleResult['error_code'] === 'TIMEOUT') {
                            $httpCode = 504; // Gateway Timeout
                        }
                        
                        $payload = [
                            'success' => false,
                            'error' => $singleResult['error'],
                            'error_code' => $singleResult['error_code'],
                            'channel_id' => $singleResult['channel_id'],
                            'request_id' => $requestId
                        ];
                        if (!empty($singleResult['resp_headers_preview'])) {
                            $payload['resp_headers_preview'] = $singleResult['resp_headers_preview'];
                        }
                        if (isset($singleResult['body_preview'])) {
                            $payload['body_preview'] = $singleResult['body_preview'];
                        }
                        if (!empty($singleResult['secret_sent'])) {
                            $payload['secret_sent'] = $singleResult['secret_sent'];
                        }
                        if (isset($singleResult['effective_url']) && $singleResult['effective_url'] !== '') {
                            $payload['effective_url'] = $singleResult['effective_url'];
                        }
                        if (!empty($singleResult['origin'])) {
                            $payload['origin'] = $singleResult['origin'];
                        }
                        if (!empty($singleResult['reason'])) {
                            $payload['reason'] = $singleResult['reason'];
                        }
                        if (!empty($singleResult['gateway_html_error'])) {
                            $payload['gateway_html_error'] = $singleResult['gateway_html_error'];
                        }
                        if (!empty($singleResult['timeout_info'])) {
                            $payload['timeout_info'] = $singleResult['timeout_info'];
                        }
                        $this->json($payload, $httpCode);
                    }
                } else {
                    // Novo comportamento: retorna resultado múltiplo
                    $successCount = count(array_filter($sendResults, function($r) { return $r['success']; }));
                    $totalCount = count($sendResults);
                    
                    error_log("[CommunicationHub::send] ===== RESULTADO FINAL ENCAMINHAMENTO =====");
                    error_log("[CommunicationHub::send] Total de canais: {$totalCount} | Sucessos: {$successCount} | Falhas: " . ($totalCount - $successCount));
                    error_log("[CommunicationHub::send] ===== FIM LOG DIAGNÓSTICO =====");
                    
                    $payload = [
                        'success' => $hasAnySuccess,
                        'forwarded' => true,
                        'total_channels' => $totalCount,
                        'success_count' => $successCount,
                        'failure_count' => $totalCount - $successCount,
                        'results' => $sendResults,
                        'message' => $hasAnySuccess 
                            ? "Mensagem enviada para {$successCount} de {$totalCount} canal(is)" 
                            : "Falha ao enviar para todos os canais",
                        'request_id' => $requestId
                    ];
                    $this->json($payload, $hasAnySuccess ? 200 : 500);
                }
            } else {
                $this->json(['success' => false, 'error' => "Canal {$channel} não implementado ainda", 'request_id' => $requestId], 400);
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
                'error' => $isLocal && !empty($e->getMessage()) ? $e->getMessage() : 'Erro interno do servidor',
                'error_code' => 'CONTROLLER_EXCEPTION',
                'request_id' => $requestId ?? null,
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
                // Verifica se tabela communication_events existe (com cache)
                if (self::tableExists($db, 'communication_events')) {
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
     * Busca sessões WhatsApp disponíveis
     * OTIMIZADO: Usa cache de 60s para evitar chamadas frequentes ao gateway
     * 
     * @param PDO $db Conexão com o banco
     * @return array Lista de sessões [['id' => 'pixel12digital', 'name' => 'Pixel12 Digital', 'status' => 'connected'], ...]
     */
    private function getWhatsAppSessions(PDO $db): array
    {
        $sessions = [];

        // 1. Whapi.Cloud — canais ativos em whatsapp_provider_configs
        try {
            $stmt = $db->query("
                SELECT session_name, whapi_channel_id, config_metadata
                FROM whatsapp_provider_configs
                WHERE provider_type = 'whapi'
                  AND is_active = 1
                ORDER BY is_global DESC, id ASC
            ");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $sessionName = $row['session_name'] ?? '';
                if (!$sessionName) continue;
                $metadata    = json_decode($row['config_metadata'] ?? '{}', true) ?: [];
                $displayName = $metadata['display_name'] ?? ucwords(str_replace(['_', '-'], ' ', $sessionName));
                $sessions[] = [
                    'id'     => $sessionName,
                    'name'   => $displayName,
                    'status' => 'connected',
                    'source' => 'whapi',
                ];
            }
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar canais Whapi: " . $e->getMessage());
        }

        // 2. Meta Official API — número ativo em whatsapp_provider_configs
        try {
            $stmt = $db->query("
                SELECT meta_phone_number_id, config_metadata
                FROM whatsapp_provider_configs
                WHERE provider_type = 'meta_official'
                  AND is_global = TRUE
                  AND is_active = 1
                LIMIT 1
            ");
            $meta = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($meta && !empty($meta['meta_phone_number_id'])) {
                $phoneId   = $meta['meta_phone_number_id'];
                $metadata  = json_decode($meta['config_metadata'] ?? '{}', true);
                $displayPhone = !empty($metadata['display_phone']) ? $metadata['display_phone'] : null;
                $sessions[] = [
                    'id'     => $phoneId,
                    'name'   => 'Meta: ' . ($displayPhone ?: $phoneId),
                    'status' => 'connected',
                    'source' => 'meta_official',
                ];
            }
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar config Meta: " . $e->getMessage());
        }

        return $sessions;
    }

    /**
     * Busca threads de WhatsApp (via tabela conversations - fonte de verdade)
     * 
     * @param PDO $db Conexão com banco
     * @param int|null $tenantId Filtro por tenant
     * @param string $status Filtro por status
     * @param string|null $sessionId Filtro por sessão WhatsApp (channel_id)
     * @param string|null $search Filtro de busca por nome ou telefone
     */
    private function getWhatsAppThreads(PDO $db, ?int $tenantId, string $status, ?string $sessionId = null, ?string $search = null): array
    {
        // Usa diretamente a tabela conversations (sempre existe)
        return $this->getWhatsAppThreadsFromConversations($db, $tenantId, $status, $sessionId, $search);
    }

    /**
     * Busca threads de WhatsApp da tabela conversations (fonte de verdade)
     * 
     * @param PDO $db Conexão com banco
     * @param int|null $tenantId Filtro por tenant
     * @param string $status Filtro por status
     * @param string|null $sessionId Filtro por sessão WhatsApp (channel_id)
     * @param string|null $search Filtro de busca por nome ou telefone
     */
    private function getWhatsAppThreadsFromConversations(PDO $db, ?int $tenantId, string $status, ?string $sessionId = null, ?string $search = null): array
    {
        $where = ["c.channel_type = 'whatsapp'"];
        $params = [];

        if ($tenantId) {
            $where[] = "c.tenant_id = ?";
            $params[] = $tenantId;
        }
        
        // Filtro por sessão WhatsApp (channel_id)
        if ($sessionId) {
            $where[] = "c.channel_id = ?";
            $params[] = $sessionId;
        }
        
        // Filtro de busca por nome ou telefone
        if ($search) {
            $searchPattern = '%' . $search . '%';
            $where[] = "(
                c.contact_name LIKE ? OR
                c.contact_external_id LIKE ? OR
                t.name LIKE ? OR
                t.phone LIKE ? OR
                l.name LIKE ? OR
                l.phone LIKE ?
            )";
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        // Filtro de status
        // IMPORTANTE: Exclui conversas com status='ignored' e 'archived' da lista de ativas
        if ($status === 'active') {
            $where[] = "c.status NOT IN ('closed', 'archived', 'ignored')";
        } elseif ($status === 'unread') {
            $where[] = "c.status NOT IN ('closed', 'archived', 'ignored')";
            $where[] = "c.unread_count > 0";
        } elseif ($status === 'archived') {
            $where[] = "c.status = 'archived'";
        } elseif ($status === 'ignored') {
            $where[] = "c.status = 'ignored'";
        } elseif ($status === 'closed') {
            $where[] = "c.status IN ('closed')";
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
                    c.provider_type,
                    c.contact_external_id,
                    c.contact_name,
                    c.tenant_id,
                    c.lead_id,
                    c.is_incoming_lead,
                    c.status,
                    c.assigned_to,
                    c.last_message_at,
                    c.last_message_direction,
                    c.message_count,
                    c.unread_count,
                    c.created_at,
                    c.source,
                    COALESCE(t.name, 'Sem tenant') as tenant_name,
                    t.phone as tenant_phone,
                    l.name as lead_name,
                    l.phone as lead_phone,
                    l.status as lead_status,
                    u.name as assigned_to_name,
                    COALESCE(
                        JSON_UNQUOTE(JSON_EXTRACT(wpc.config_metadata, '$.display_name')),
                        tmc.name,
                        c.channel_id
                    ) as channel_display_name
                FROM conversations c
                LEFT JOIN tenants t ON c.tenant_id = t.id
                LEFT JOIN leads l ON c.lead_id = l.id
                LEFT JOIN users u ON c.assigned_to = u.id
                LEFT JOIN tenant_message_channels tmc ON c.channel_id = tmc.channel_id AND tmc.provider = 'whapi'
                LEFT JOIN whatsapp_provider_configs wpc ON wpc.session_name = c.channel_id AND wpc.provider_type = 'whapi' AND wpc.is_active = 1
                {$whereClause}
                ORDER BY c.is_incoming_lead DESC, COALESCE(c.last_message_at, c.created_at) DESC, c.created_at DESC
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
        // CORREÇÃO: Sempre resolve @lid para obter número real, independente de tenant_phone
        foreach ($conversations as $conv) {
            if (!empty($conv['contact_external_id'])) {
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

        // OTIMIZAÇÃO: Desabilitado temporariamente para melhorar performance
        // A resolução de channel_id estava causando lentidão devido a múltiplas queries complexas
        // TODO: Reimplementar de forma mais eficiente se necessário
        $resolvedChannelIds = [];
        
        // // CORREÇÃO: Resolve channel_id NULL consultando eventos recentes ou tenant_message_channels
        // // Coleta conversas que precisam de resolução de channel_id
        // $conversationsNeedingChannelId = [];
        // foreach ($conversations as $idx => $conv) {
        //     if (empty($conv['channel_id'])) {
        //         $conversationsNeedingChannelId[$idx] = $conv;
        //     }
        // }
        // 
        // // Resolve channel_id em lote para otimização
        // $resolvedChannelIds = [];
        // if (!empty($conversationsNeedingChannelId)) {
        //     $resolvedChannelIds = $this->resolveMissingChannelIds($db, $conversationsNeedingChannelId);
        // }

        // Formata para o formato esperado pela UI
        $threads = [];
        foreach ($conversations as $idx => $conv) {
                    // CORREÇÃO: Usa channel_id resolvido se o original estava NULL
                    $resolvedChannelId = $resolvedChannelIds[$idx] ?? null;
                    $finalChannelId = !empty($conv['channel_id']) ? $conv['channel_id'] : $resolvedChannelId;
                    
                    // CORREÇÃO CRÍTICA: O número exibido deve ser SEMPRE o contact_external_id da conversa
                    // NUNCA usar tenant.phone porque isso mistura números de contatos diferentes
                    // que pertencem ao mesmo cliente (ex: Luiz, Renato, Alessandra do mesmo tenant)
                    $realPhone = null;
                    
                    if (!empty($conv['contact_external_id'])) {
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
                            // Busca número real no mapa pré-carregado (cache wa_pnlid_cache)
                            $realPhone = $lidPhoneMap[$lidId] ?? null;
                        }
                    }
                    
                    $threads[] = [
                        'thread_id' => "whatsapp_{$conv['id']}",
                        'conversation_id' => $conv['id'],
                        'conversation_key' => $conv['conversation_key'],
                        'tenant_id' => $conv['tenant_id'] ?: null,
                        'tenant_name' => (!empty($conv['tenant_id']) && $conv['tenant_name'] !== 'Sem tenant') ? $conv['tenant_name'] : null,
                        'lead_id' => $conv['lead_id'] ?? null,
                        'lead_name' => $conv['lead_name'] ?? null,
                        'lead_phone' => !empty($conv['lead_id']) ? ($conv['lead_phone'] ?? null) : null,
                        'lead_status' => $conv['lead_status'] ?? null,
                        'contact' => ContactHelper::formatContactId($conv['contact_external_id'], $realPhone),
                        'contact_name' => $conv['contact_name'],
                        'last_activity' => $conv['last_message_at'] ?: $conv['created_at'],
                        'message_count' => (int) $conv['message_count'],
                        'inbound_count' => $conv['last_message_direction'] === 'inbound' ? 1 : 0, // Aproximação
                        'channel' => 'whatsapp',
                        'channel_type' => $conv['channel_type'], // Adiciona contexto
                        'channel_id' => $finalChannelId, // CORREÇÃO: Usa channel_id resolvido se necessário
                        'channel_display_name' => $conv['channel_display_name'] ?? $finalChannelId,
                        'status' => $conv['status'],
                        'unread_count' => (int) $conv['unread_count'],
                        'assigned_to' => $conv['assigned_to'],
                        'assigned_to_name' => $conv['assigned_to_name'],
                        'is_incoming_lead' => (bool) ($conv['is_incoming_lead'] ?? 0), // Flag de incoming lead
                        'source' => $conv['source'] ?? '',
                        'last_message_direction' => $conv['last_message_direction'] ?? ''
                    ];
        }

        return $threads;
    }

    /**
     * Busca threads de WhatsApp via eventos (fallback para compatibilidade)
     */
    private function getWhatsAppThreadsFromEvents(PDO $db, ?int $tenantId, string $status): array
    {
        // Verifica se a tabela existe (com cache)
        if (!self::tableExists($db, 'communication_events')) {
            return []; // Tabela não existe ainda
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
                // CORREÇÃO CRÍTICA: O número exibido deve ser baseado no $from (contact_external_id),
                // NUNCA usar tenant.phone porque isso mistura números de contatos diferentes
                $realPhone = null;
                if (strpos($from, '@lid') !== false) {
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
        // Verifica se a tabela existe (com cache)
        if (!self::tableExists($db, 'chat_threads')) {
            return []; // Tabela não existe ainda
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
            
            $url = rtrim($baseUrl, '/') . "/api/" . rawurlencode($sessionId) . "/contact/pn-lid/" . rawurlencode($pnLid);
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
            curl_close($ch);

            if ($code < 200 || $code >= 300 || !$raw) {
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
                    $e164 = $normalizePhoneE164($cand);
                    if ($e164) {
                        return $e164;
                    }
                }
            }
            
            // Se vier no formato JID:
            if (!empty($j['jid'])) {
                $e164 = $normalizePhoneE164($j['jid']);
                if ($e164) {
                    return $e164;
                }
            }

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
        $lidResolvedPhones = []; // Números resolvidos a partir de @lid (para contact_external_id com @lid)
        if (!empty($contactExternalId) && preg_match('/^[0-9]+$/', $contactExternalId)) {
            $lidStmt = $db->prepare("
                SELECT business_id 
                FROM whatsapp_business_ids 
                WHERE phone_number = ?
            ");
            $lidStmt->execute([$contactExternalId]);
            $lidMappings = $lidStmt->fetchAll(PDO::FETCH_COLUMN);
            $lidBusinessIds = $lidMappings ?: [];
            
        }
        // CORREÇÃO: Se contact_external_id é @lid, busca phone_number mapeado para esse business_id
        // Eventos podem ter from/to como número (557187799910@c.us) em vez de @lid
        if (!empty($contactExternalId) && strpos($contactExternalId, '@lid') !== false) {
            $lidPhoneStmt = $db->prepare("
                SELECT phone_number 
                FROM whatsapp_business_ids 
                WHERE business_id = ?
            ");
            $lidPhoneStmt->execute([$contactExternalId]);
            $lidPhones = $lidPhoneStmt->fetchAll(PDO::FETCH_COLUMN);
            $lidResolvedPhones = array_filter($lidPhones ?: []);
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
        
        if (empty($normalizedContactExternalId) && strpos($contactExternalId, '@lid') === false) {
            return [];
        }

        // FASE 1 (indexada): busca eventos vinculados diretamente por conversation_id
        // Usa índice idx_conversation_id — O(log n) em vez de full table scan
        $phase1Events = [];
        try {
            $p1Stmt = $db->prepare("
                SELECT ce.event_id, ce.event_type, ce.created_at, ce.payload, ce.metadata, ce.tenant_id
                FROM communication_events ce
                WHERE ce.conversation_id = ?
                  AND ce.deleted_at IS NULL
                  AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                ORDER BY ce.created_at ASC
                LIMIT 500
            ");
            $p1Stmt->execute([$conversationId]);
            $phase1Events = $p1Stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            // Ignora: fallback para busca por padrão de contato
        }

        // FASE 2: busca por padrão de contato — cobre eventos antigos sem conversation_id
        // Restringe a ce.conversation_id IS NULL para não rescanear o que a Fase 1 já encontrou
        $where = [
            "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')",
            "ce.conversation_id IS NULL",
            // FILTRO: Exclui eventos técnicos que não são mensagens reais
            "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.type')) IS NULL
            )",
            "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.message.type')) IS NULL
            )",
            "(
                JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) NOT IN ('e2e_notification', 'notification_template', 'ciphertext')
                OR JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.raw.payload.type')) IS NULL
            )"
        ];
        $params = [];

        // CORREÇÃO: Filtro mais robusto que pega variações do telefone
        // Usa múltiplos padrões para pegar: número puro, com @c.us, com 9º dígito, etc
        $contactPatterns = [];
        
        // IMPORTANTE: Se contact_external_id tem @lid, adiciona padrão COM @lid primeiro
        // Isso garante que eventos com @lid sejam encontrados mesmo quando o número normalizado não bate
        if (strpos($contactExternalId, '@lid') !== false) {
            $contactPatterns[] = "%{$contactExternalId}%";
        }
        
        // CORREÇÃO: Adiciona números resolvidos a partir de @lid (eventos usam número em from/to)
        foreach ($lidResolvedPhones as $phone) {
            $contactPatterns[] = "%{$phone}%";
            if (strlen($phone) >= 12 && substr($phone, 0, 2) === '55') {
                if (strlen($phone) === 13) {
                    $without9th = substr($phone, 0, 4) . substr($phone, 5);
                    $contactPatterns[] = "%{$without9th}%";
                } elseif (strlen($phone) === 12) {
                    $with9th = substr($phone, 0, 4) . '9' . substr($phone, 4);
                    $contactPatterns[] = "%{$with9th}%";
                }
            }
        }
        
        // Sempre adiciona número normalizado (pode ser usado como fallback)
        if ($normalizedContactExternalId) {
            $contactPatterns[] = "%{$normalizedContactExternalId}%";
        }
        
        // Se for número BR (começa com 55), adiciona variação com/sem 9º dígito
        if ($normalizedContactExternalId && strlen($normalizedContactExternalId) >= 12 && substr($normalizedContactExternalId, 0, 2) === '55') {
            // Tenta adicionar 9º dígito (se não tiver)
            if (strlen($normalizedContactExternalId) === 13) {
                $without9th = substr($normalizedContactExternalId, 0, 4) . substr($normalizedContactExternalId, 5);
                $contactPatterns[] = "%{$without9th}%";
            } elseif (strlen($normalizedContactExternalId) === 12) {
                $with9th = substr($normalizedContactExternalId, 0, 4) . '9' . substr($normalizedContactExternalId, 4);
                $contactPatterns[] = "%{$with9th}%";
            }
        }
        
        // CORREÇÃO: Adiciona busca por @lid mapeado (se houver)
        if (!empty($lidBusinessIds)) {
            foreach ($lidBusinessIds as $lid) {
                $contactPatterns[] = "%{$lid}%";
            }
        }
        
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
        // CORREÇÃO CRÍTICA: Se houver channel_id, NÃO filtra por tenant_id na query SQL
        // porque o channel_id já garante isolamento. Isso resolve casos onde eventos têm
        // tenant_id diferente da conversa (ex: eventos com tenant_id=121 mas conversa com tenant_id=25)
        // IMPORTANTE: Se tenant_id é NULL na conversa, não filtra por tenant_id (permite encontrar eventos com qualquer tenant_id)
        $whereWithTenant = $where;
        $paramsWithTenant = $params;
        
        // PATCH K: Filtro adicional por channel_id para garantir isolamento por sessão
        // CORREÇÃO: Usa comparação case-insensitive para channel_id (resolve problema "imobsites" vs "ImobSites")
        $hasChannelId = !empty($sessionId);
        
        if ($hasChannelId) {
            // Se há channel_id, adiciona filtro de channel_id (garante isolamento)
            // CORREÇÃO: Adiciona payload.channel_id para eventos outbound (que não têm metadata.channel_id)
            // e usa normalização case-insensitive com remoção de espaços para todas as comparações
            $normalizedSessionId = strtolower(str_replace(' ', '', $sessionId));
            $whereWithTenant[] = "(
                LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.metadata, '$.channel_id')), ' ', ''))) = ?
                OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channel_id')), ' ', ''))) = ?
                OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.session.id')), ' ', ''))) = ?
                OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.sessionId')), ' ', ''))) = ?
                OR LOWER(TRIM(REPLACE(JSON_UNQUOTE(JSON_EXTRACT(ce.payload, '$.channelId')), ' ', ''))) = ?
            )";
            $paramsWithTenant[] = $normalizedSessionId;
            $paramsWithTenant[] = $normalizedSessionId;
            $paramsWithTenant[] = $normalizedSessionId;
            $paramsWithTenant[] = $normalizedSessionId;
            $paramsWithTenant[] = $normalizedSessionId;
            
            // NÃO adiciona filtro de tenant_id quando há channel_id (channel_id já garante isolamento)
            // Isso permite encontrar eventos mesmo quando tenant_id é diferente
        } elseif ($tenantId) {
            // Se NÃO há channel_id mas há tenant_id, filtra por tenant_id
            $whereWithTenant[] = "ce.tenant_id = ?";
            $paramsWithTenant[] = $tenantId;
        }

        $whereClause = "WHERE ce.deleted_at IS NULL AND " . implode(" AND ", $whereWithTenant);

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
        // OTIMIZAÇÃO CRÍTICA: Pula Fase 2 (full table scan via JSON_EXTRACT+LIKE) quando
        // Fase 1 já encontrou eventos vinculados por conversation_id. Fase 2 só é necessária
        // para conversas muito antigas cujos eventos não têm conversation_id no banco.
        if (!empty($phase1Events)) {
            $filteredEvents = [];
        } else {
            $stmt->execute($paramsWithTenant);
            $filteredEvents = $stmt->fetchAll();

            // CORREÇÃO: Se não encontrou eventos, tenta buscar sem filtro de tenant_id/channel_id
            // Isso resolve casos onde:
            // 1. A conversa tem tenant_id NULL (não vinculada) mas os eventos têm tenant_id
            // 2. A conversa tem tenant_id incorreto mas os eventos têm tenant_id diferente (e não há channel_id)
            // IMPORTANTE: Se há channel_id, a query inicial já não filtra por tenant_id, então esta busca
            // só será executada quando não houver channel_id ou quando os filtros de contato não encontrarem nada
            if (empty($filteredEvents)) {
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
            }
        }

        // Combina Fase 1 (por conversation_id) + Fase 2 (por padrão de contato)
        // Deduplicao por event_id — Fase 1 tem prioridade (dados mais diretos)
        if (!empty($phase1Events)) {
            $merged = array_column($phase1Events, null, 'event_id');
            foreach ($filteredEvents as $ev) {
                if (!isset($merged[$ev['event_id']])) {
                    $merged[$ev['event_id']] = $ev;
                }
            }
            $filteredEvents = array_values($merged);
            if (count($filteredEvents) > 1) {
                usort($filteredEvents, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
            }
        }

        // Validação final em PHP (garantir que mensagem pertence à conversa)
        // A query SQL já filtra a maioria, mas validação final garante precisão
        $messages = [];
        $excludedCount = 0;
        
        // OTIMIZAÇÃO: Batch query para todas as mídias de uma vez (em vez de N queries)
        // Isso reduz de 500+ queries para apenas 1 query
        $mediaCache = [];
        if (!empty($filteredEvents)) {
            $eventIds = array_column($filteredEvents, 'event_id');
            if (!empty($eventIds)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
                    $mediaStmt = $db->prepare("
                        SELECT * FROM communication_media 
                        WHERE event_id IN ({$placeholders})
                    ");
                    $mediaStmt->execute($eventIds);
                    $allMedia = $mediaStmt->fetchAll(\PDO::FETCH_ASSOC);
                    
                    // Indexa por event_id para lookup O(1)
                    foreach ($allMedia as $media) {
                        // Gera URL da mídia
                        $mediaUrl = null;
                        if (!empty($media['stored_path'])) {
                            $mediaUrl = \PixelHub\Services\WhatsAppMediaService::getMediaUrl($media['stored_path']);
                        }
                        $media['url'] = $mediaUrl;
                        $mediaCache[$media['event_id']] = $media;
                    }
                } catch (\Exception $e) {
                    error_log("[CommunicationHub] Erro no batch query de mídias: " . $e->getMessage());
                }
            }
        }

        // OTIMIZAÇÃO: Pré-carrega mapeamentos lid↔phone em batch antes do loop
        // Elimina N+1 queries (antes: até 3 queries por mensagem × 500 msgs = 1500+ queries)
        $lidBatchCache = []; // [businessId => phoneNumber]
        $batchLidIds = [];
        if (strpos($conversationRemoteKey ?? '', 'lid:') === 0) {
            $batchLidIds[] = substr($conversationRemoteKey, 4) . '@lid';
        }
        foreach ($filteredEvents as $_bev) {
            $_bp = json_decode($_bev['payload'], true);
            foreach (['from', 'to'] as $_bf) {
                $_bj = $_bp[$_bf] ?? $_bp['message'][$_bf] ?? null;
                if ($_bj && preg_match('/^([0-9]+)@lid$/', $_bj, $_bm)) {
                    $batchLidIds[] = $_bm[1] . '@lid';
                }
            }
        }
        if (!empty($batchLidIds)) {
            $batchLidIds = array_unique($batchLidIds);
            try {
                $_lph = implode(',', array_fill(0, count($batchLidIds), '?'));
                $_lst = $db->prepare("SELECT business_id, phone_number FROM whatsapp_business_ids WHERE business_id IN ({$_lph})");
                $_lst->execute($batchLidIds);
                foreach ($_lst->fetchAll(\PDO::FETCH_ASSOC) as $_lr) {
                    $lidBatchCache[$_lr['business_id']] = $_lr['phone_number'];
                }
            } catch (\Exception $_le) { /* ignora */ }
        }
        // Pré-resolve phone do @lid da conversa (CASO 2) — mesmo valor para TODOS os eventos
        $conversationPhoneFromLidPre = null;
        if (strpos($conversationRemoteKey ?? '', 'lid:') === 0) {
            $conversationPhoneFromLidPre = $lidBatchCache[substr($conversationRemoteKey, 4) . '@lid'] ?? null;
        }

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
            
            // CORREÇÃO: Se não bateu por remote_key, verifica mapeamentos lid <-> tel
            if (!$isFromThisContact && !$isToThisContact && !empty($conversationRemoteKey)) {
                
                // CASO 1: Conversa tem tel:, evento tem lid: (conversa usa número, evento usa @lid)
                if (strpos($conversationRemoteKey, 'tel:') === 0) {
                    $conversationPhone = substr($conversationRemoteKey, 4);
                    
                    if ($eventFromKey && strpos($eventFromKey, 'lid:') === 0) {
                        $lidId = substr($eventFromKey, 4);
                        $lidBusinessId = $lidId . '@lid';
                        if (($lidBatchCache[$lidBusinessId] ?? null) === $conversationPhone) {
                            $isFromThisContact = true;
                        }
                    }
                    
                    if ($eventToKey && strpos($eventToKey, 'lid:') === 0) {
                        $lidId = substr($eventToKey, 4);
                        $lidBusinessId = $lidId . '@lid';
                        if (($lidBatchCache[$lidBusinessId] ?? null) === $conversationPhone) {
                            $isToThisContact = true;
                        }
                    }
                }
                
                // CASO 2: Conversa tem lid:, evento tem tel: (conversa usa @lid, evento outbound usa número)
                // Este é o caso do Luiz: conversa tem lid:103066917425370, evento outbound tem to=5511988427530
                if (strpos($conversationRemoteKey, 'lid:') === 0) {
                    // Usa valor pré-carregado (evita query por mensagem)
                    $conversationPhoneFromLid = $conversationPhoneFromLidPre;
                    
                    if ($conversationPhoneFromLid) {
                        // Verifica se eventFromKey (tel:xxx) bate com o telefone do @lid
                        if ($eventFromKey && strpos($eventFromKey, 'tel:') === 0) {
                            $eventFromPhone = substr($eventFromKey, 4);
                            // Compara com normalização (remove 9º dígito se necessário)
                            if ($eventFromPhone === $conversationPhoneFromLid ||
                                self::normalizePhone($eventFromPhone) === self::normalizePhone($conversationPhoneFromLid)) {
                                $isFromThisContact = true;
                            }
                        }
                        
                        // Verifica se eventToKey (tel:xxx) bate com o telefone do @lid
                        if ($eventToKey && strpos($eventToKey, 'tel:') === 0) {
                            $eventToPhone = substr($eventToKey, 4);
                            // Compara com normalização (remove 9º dígito se necessário)
                            if ($eventToPhone === $conversationPhoneFromLid ||
                                self::normalizePhone($eventToPhone) === self::normalizePhone($conversationPhoneFromLid)) {
                                $isToThisContact = true;
                            }
                        }
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
            
            if (!$isFromThisContact && !$isToThisContact) {
                $excludedCount++;
                continue;
            }
            
            // Verifica se tenant_id bate (se ambos tiverem tenant_id definido)
            // CORREÇÃO: Se houver channel_id, permite eventos com tenant_id diferente
            // (channel_id já garante isolamento, e isso resolve casos onde a conversa tem tenant_id incorreto)
            if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
                // Se não há channel_id ou o channel_id não garante isolamento, rejeita
                // Se há channel_id, aceita mesmo com tenant_id diferente (pode ser erro de mapeamento)
                if (empty($sessionId)) {
                    continue;
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
            // CORREÇÃO: Inclui caption para imagens/vídeos com legenda
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body']
                ?? $payload['caption']
                ?? $payload['message']['caption']
                ?? $payload['raw']['payload']['caption']  // WPPConnect image/video caption
                ?? '';
            $content = $this->resolveTemplateBody($db, $content);
            
            // OTIMIZAÇÃO: Usa cache de mídia (batch query) em vez de queries individuais
            // Antes: processMediaFromEvent + getMediaByEventId = 2 queries por mensagem (N*2 queries)
            // Agora: lookup no cache = O(1), apenas 1 query total para todas as mídias
            $mediaInfo = $mediaCache[$event['event_id']] ?? null;
            
            // Se mídia não está no cache mas evento indica que TEM mídia, processa sob demanda
            $payloadType = $payload['type'] ?? $payload['raw']['payload']['type'] ?? null;
            $hasMediaIndicator = $payloadType && in_array($payloadType, ['audio', 'ptt', 'image', 'video', 'document', 'sticker']);

            if (!$mediaInfo && $hasMediaIndicator) {
                // CORREÇÃO: Verifica se mediaUrl já existe no payload antes de tentar processar
                $mediaUrl = $payload['message']['mediaUrl'] ?? null;
                $mediaData = $payload['message']['media'] ?? [];
                
                if ($mediaUrl) {
                    $mediaInfo = [
                        'url' => $mediaUrl,
                        'media_type' => $payloadType === 'ptt' ? 'audio' : ($mediaData['type'] ?? $payloadType),
                        'mime_type' => $mediaData['mimetype'] ?? ($payloadType === 'ptt' || $payloadType === 'audio' ? 'audio/ogg' : null),
                        'file_size' => $mediaData['size'] ?? null,
                    ];
                } else {
                    // Tenta processar mídia sob demanda
                    try {
                        $processedMedia = \PixelHub\Services\WhatsAppMediaService::processMediaFromEvent($event);
                        if ($processedMedia) {
                            $mediaInfo = $processedMedia;
                        }
                    } catch (\Exception $e) {
                        error_log("[CommunicationHub] Erro ao processar mídia sob demanda: " . $e->getMessage());
                    }
                    
                    // Se ainda não tem mídia após tentativa: placeholder para UI exibir "Mídia não disponível"
                    if (!$mediaInfo) {
                        $mediaInfo = [
                            'media_failed' => true,
                            'media_type' => $payloadType === 'ptt' ? 'audio' : $payloadType,
                            'mime_type' => $payloadType === 'ptt' || $payloadType === 'audio' ? 'audio/ogg' : ($payloadType === 'image' ? 'image/jpeg' : null),
                        ];
                    }
                }
            }
            
            // Se encontrou mídia, limpa conteúdo se for base64/binário
            // CORREÇÃO: Não zera texto legítimo (com espaços/quebras) mesmo que seja longo
            if ($mediaInfo && !empty($content)) {
                $hasSpacesOrNewlines = strpos($content, ' ') !== false || strpos($content, "\n") !== false;
                if (strlen($content) > 100 && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $content)) {
                    $textCleaned = preg_replace('/\s+/', '', $content);
                    $decoded = base64_decode($textCleaned, true);
                    if ($decoded !== false) {
                        $isOgg = substr($decoded, 0, 4) === 'OggS';
                        $isJpeg = substr($textCleaned, 0, 4) === '/9j/';
                        $isPng = substr($textCleaned, 0, 12) === 'iVBORw0KGgo';
                        if ($isOgg || $isJpeg || $isPng || strlen($decoded) > 1000) {
                            $content = '';
                        }
                    }
                } elseif (strlen($content) > 500 && !$hasSpacesOrNewlines) {
                    $content = '';
                }
            }
            
            // Se não encontrou mídia e não há conteúdo, mostra tipo de mídia
            // Inclui raw.payload.type (WPPConnect: ciphertext = mensagem criptografada E2E)
            if (empty($content) && !$mediaInfo) {
                $payloadType = $payload['type']
                    ?? $payload['message']['type']
                    ?? $payload['raw']['payload']['type']
                    ?? null;
                if ($payloadType) {
                    $content = ($payloadType === 'ciphertext')
                        ? '[Mensagem criptografada]'
                        : "[{$payloadType}]";
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
                'media' => $mediaInfo, // Informações da mídia (se houver)
                // Campos para identificação do remetente
                'sent_by_name' => $eventMetadata['sent_by_name'] ?? null, // Nome do operador que enviou (outbound)
                'sent_by' => $eventMetadata['sent_by'] ?? null // ID do operador que enviou (outbound)
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
            
            // CORREÇÃO: Inclui caption para imagens/vídeos com legenda
            $content = $payload['body'] 
                ?? $payload['text'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body']
                ?? $payload['caption']
                ?? $payload['message']['caption']
                ?? $payload['raw']['payload']['caption']  // WPPConnect image/video caption
                ?? '';
            $content = $this->resolveTemplateBody($db, $content);
            
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

            $payloadType = $payload['type'] ?? $payload['message']['type'] ?? $payload['raw']['payload']['type'] ?? null;
            $hasMediaIndicator = $payloadType && in_array($payloadType, ['audio', 'ptt', 'image', 'video', 'document', 'sticker']);
            if (!$mediaInfo && $hasMediaIndicator) {
                $mediaInfo = [
                    'media_failed' => true,
                    'media_type' => $payloadType === 'ptt' ? 'audio' : $payloadType,
                    'mime_type' => $payloadType === 'ptt' || $payloadType === 'audio' ? 'audio/ogg' : ($payloadType === 'image' ? 'image/jpeg' : null),
                ];
            }
            
            // Se não encontrou mídia e não há conteúdo, mostra tipo de mídia
            // Inclui raw.payload.type (WPPConnect: ciphertext = mensagem criptografada E2E)
            if (empty($content) && !$mediaInfo) {
                if ($payloadType) {
                    $content = ($payloadType === 'ciphertext')
                        ? '[Mensagem criptografada]'
                        : "[{$payloadType}]";
                }
            }
            
            $eventMetadata = json_decode($event['metadata'] ?? '{}', true);
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => $eventMetadata,
                'media' => $mediaInfo, // Informações da mídia (se houver) - objeto completo
                // Campos para identificação do remetente
                'sent_by_name' => $eventMetadata['sent_by_name'] ?? null, // Nome do operador que enviou (outbound)
                'sent_by' => $eventMetadata['sent_by'] ?? null // ID do operador que enviou (outbound)
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
                    l.name as lead_name,
                    l.phone as lead_phone,
                    tmc.channel_id as tenant_channel_id,
                    u.name as assigned_to_name
                FROM conversations c
                LEFT JOIN tenants t ON c.tenant_id = t.id
                LEFT JOIN leads l ON c.lead_id = l.id
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
                                $channelId = trim((string) $metadata['channel_id']);
                                error_log("[CommunicationHub::getWhatsAppThreadInfo] PRIORIDADE 2.10: channel_id encontrado (metadata.channel_id): {$channelId}");
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
                
                // CORREÇÃO CRÍTICA: O número exibido deve ser SEMPRE o contact_external_id da conversa
                // NUNCA usar tenant.phone porque isso mistura números de contatos diferentes
                $realPhone = null;
                if (strpos($conversation['contact_external_id'] ?? '', '@lid') !== false) {
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
                    'lead_id' => $conversation['lead_id'] ?? null,
                    'lead_name' => $conversation['lead_name'] ?? null,
                    'lead_phone' => $conversation['lead_phone'] ?? null,
                    'contact' => ContactHelper::formatContactId($conversation['contact_external_id'], $realPhone),
                    'contact_name' => $conversation['contact_name']
                        ?: ($conversation['tenant_name'] ?: null)
                        ?: ($conversation['lead_name'] ?: (!empty($conversation['lead_id']) ? ('Lead #' . $conversation['lead_id']) : null)),
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
                // CORREÇÃO CRÍTICA: O número exibido deve ser baseado no $from (contact_external_id),
                // NUNCA usar tenant.phone porque isso mistura números de contatos diferentes
                $realPhone = null;
                if (strpos($from, '@lid') !== false) {
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
     * Retorna opções para filtros do Inbox (tenants e sessões WhatsApp)
     * 
     * GET /communication-hub/filter-options
     * GET /communication-hub/filter-options?for_link=1  (tenants com email, phone, cpf_cnpj para modal Vincular)
     * Retorna {success: bool, tenants: array, whatsapp_sessions: array}
     */
    public function getFilterOptions(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $db = DB::getConnection();
        $forLink = isset($_GET['for_link']) && $_GET['for_link'] === '1';

        try {
            $tenants = [];
            $whatsappSessions = [];

            if ($forLink) {
                $tenantsStmt = $db->query("
                    SELECT id, name, email, phone, COALESCE(cpf_cnpj, document, '') as cpf_cnpj
                    FROM tenants 
                    WHERE (is_archived IS NULL OR is_archived = 0)
                    ORDER BY name 
                    LIMIT 500
                ");
            } else {
                $tenantsStmt = $db->query("
                    SELECT id, name FROM tenants 
                    WHERE is_archived = 0 
                    ORDER BY name 
                    LIMIT 100
                ");
            }
            if ($tenantsStmt) {
                $tenants = $tenantsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }

            $whatsappSessions = $this->getWhatsAppSessions($db);

            $this->json([
                'success' => true,
                'tenants' => $tenants,
                'whatsapp_sessions' => $whatsappSessions
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar filter-options: " . $e->getMessage());
            $this->json(['success' => false, 'error' => 'Erro ao carregar opções'], 500);
        }
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
        // FIX: Agora lê session_id do GET para manter consistência com os filtros da página
        $sessionId = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? $_GET['session_id'] : null;
        $search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;

        $db = DB::getConnection();

        try {
            // Busca threads de WhatsApp (agora com session_id e search)
            $whatsappThreads = $this->getWhatsAppThreads($db, $tenantId, $status, $sessionId, $search);
            
            // Busca threads de chat interno
            $chatThreads = $this->getChatThreads($db, $tenantId, $status);

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
                    
                    // Reordena após filtrar (array_filter pode desordenar)
                    usort($allThreads, function($a, $b) {
                        $timeA = strtotime($a['last_activity'] ?? '1970-01-01');
                        $timeB = strtotime($b['last_activity'] ?? '1970-01-01');
                        return $timeB <=> $timeA; // Mais recente primeiro
                    });
                }
                
                // Separa incoming leads das conversas normais
                foreach ($allThreads as $thread) {
                    if (!empty($thread['is_incoming_lead'])) {
                        // Oculta prospecções sem resposta (source=prospecting + última mensagem outbound)
                        if (($thread['source'] ?? '') === 'prospecting' && ($thread['last_message_direction'] ?? '') === 'outbound') {
                            continue;
                        }
                        $incomingLeads[] = $thread;
                    } else {
                        $normalThreads[] = $thread;
                    }
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
                      AND NOT (COALESCE(c.source,'') = 'prospecting' AND COALESCE(c.last_message_direction,'') = 'outbound')
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
     * Busca a conversa WhatsApp mais recente de um tenant
     * 
     * GET /communication-hub/find-tenant-conversation?tenant_id=X
     * 
     * Retorna {found: bool, thread_id: string, channel: string} se encontrou conversa ativa
     * ou {found: false} se não encontrou
     */
    public function findTenantConversation(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $tenantId = isset($_GET['tenant_id']) ? (int) $_GET['tenant_id'] : 0;

        if ($tenantId <= 0) {
            $this->json(['found' => false, 'error' => 'tenant_id é obrigatório']);
            return;
        }

        $db = DB::getConnection();

        try {
            // Busca a conversa WhatsApp mais recente do tenant (ativa ou qualquer status exceto closed)
            $stmt = $db->prepare("
                SELECT 
                    c.id,
                    c.conversation_key,
                    c.channel_type,
                    c.contact_external_id,
                    c.contact_name,
                    c.status,
                    c.last_message_at,
                    c.message_count
                FROM conversations c
                WHERE c.tenant_id = ?
                  AND c.channel_type = 'whatsapp'
                ORDER BY c.last_message_at DESC
                LIMIT 1
            ");
            $stmt->execute([$tenantId]);
            $conversation = $stmt->fetch();

            if ($conversation) {
                $threadId = 'whatsapp_' . $conversation['id'];
                $this->json([
                    'found' => true,
                    'thread_id' => $threadId,
                    'channel' => 'whatsapp',
                    'conversation_id' => (int) $conversation['id'],
                    'contact_name' => $conversation['contact_name'],
                    'status' => $conversation['status'],
                    'last_message_at' => $conversation['last_message_at'],
                    'message_count' => (int) $conversation['message_count'],
                ]);
            } else {
                $this->json(['found' => false]);
            }
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar conversa do tenant: " . $e->getMessage());
            $this->json(['found' => false, 'error' => 'Erro ao buscar conversa']);
        }
    }

    /**
     * Retorna apenas a contagem de mensagens não lidas (endpoint leve para badge no header)
     *
     * GET /communication-hub/unread-count
     *
     * Retorna {success: bool, total_unread: int} sem fazer JOIN ou resolução de @lid
     */
    public function getUnreadCount(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(SUM(c.unread_count), 0) AS total_unread
                FROM conversations c
                WHERE c.channel_type = 'whatsapp'
                  AND c.status NOT IN ('closed', 'archived', 'ignored')
            ");
            $stmt->execute();
            $row = $stmt->fetch();

            $this->json([
                'success' => true,
                'total_unread' => (int) ($row['total_unread'] ?? 0)
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar unread-count: " . $e->getMessage());
            $this->json(['success' => false, 'total_unread' => 0], 500);
        }
    }

    /**
     * Retorna lista de sessões WhatsApp disponíveis para o modal Nova Mensagem
     * GET /communication-hub/sessions
     */
    public function getSessions(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $db = DB::getConnection();
        $sessions = [];

        try {
            $stmt = $db->query("
                SELECT session_name, whapi_channel_id, config_metadata
                FROM whatsapp_provider_configs
                WHERE provider_type = 'whapi'
                  AND is_active = 1
                ORDER BY is_global DESC, id ASC
            ");
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $sessionName = $row['session_name'] ?? '';
                if (!$sessionName) continue;
                $metadata    = json_decode($row['config_metadata'] ?? '{}', true) ?: [];
                $displayName = $metadata['display_name'] ?? ucwords(str_replace(['_', '-'], ' ', $sessionName));
                $sessions[]  = ['id' => $sessionName, 'name' => $displayName];
            }

            $this->json(['success' => true, 'sessions' => $sessions]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar sessions: " . $e->getMessage());
            $this->json(['success' => false, 'sessions' => []], 500);
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

            // IMPORTANTE: Exclui conversas com status='ignored' e 'archived' da lista de ativas
            if ($status === 'active') {
                $where[] = "c.status NOT IN ('closed', 'archived', 'ignored')";
            } elseif ($status === 'archived') {
                $where[] = "c.status = 'archived'";
            } elseif ($status === 'ignored') {
                $where[] = "c.status = 'ignored'";
            } elseif ($status === 'closed') {
                $where[] = "c.status IN ('closed')";
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
     * GET /communication-hub/messages/check?thread_id=X&after_timestamp=Y
     *
     * OTIMIZADO: Usa apenas conversations.last_message_at (lookup por PK — sem JSON_EXTRACT)
     */
    public function checkNewMessages(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $threadId = $_GET['thread_id'] ?? null;
        $afterTimestamp = $_GET['after_timestamp'] ?? null;

        if (empty($threadId)) {
            $this->json(['success' => false, 'error' => 'thread_id é obrigatório'], 400);
            return;
        }

        // Extrai conversation_id do thread_id (formato: whatsapp_{id} ou chat_{id})
        $conversationId = null;
        if (preg_match('/^(?:whatsapp|chat)_(\d+)$/', $threadId, $m)) {
            $conversationId = (int) $m[1];
        }

        if (!$conversationId) {
            $this->json(['success' => true, 'has_new' => false]);
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                SELECT last_message_at, message_count
                FROM conversations
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$conversationId]);
            $conv = $stmt->fetch();

            if (!$conv) {
                $this->json(['success' => false, 'error' => 'Thread não encontrado'], 404);
                return;
            }

            $hasNew = false;
            if ($afterTimestamp && !empty($conv['last_message_at'])) {
                $hasNew = strtotime($conv['last_message_at']) > strtotime($afterTimestamp);
            }

            $this->json([
                'success' => true,
                'has_new' => $hasNew,
                'last_message_at' => $conv['last_message_at']
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
                    
                    // CORREÇÃO: Se evento tem conversation_id, valida diretamente
                    // Isso é mais confiável que comparar números que podem ter formatos diferentes
                    if (!empty($event['conversation_id'])) {
                        $expectedConversationId = $conversationData['id'] ?? null;
                        if ($event['conversation_id'] != $expectedConversationId) {
                            error_log("[CommunicationHub::getMessage] REJEITADO - conversation_id não bate. event.conversation_id={$event['conversation_id']} expected={$expectedConversationId}");
                            $this->json(['success' => false, 'error' => 'Mensagem não pertence à thread'], 403);
                            return;
                        }
                        // Validação por conversation_id passou, continua
                    } else {
                        // Fallback: valida por from/to (para eventos antigos sem conversation_id)
                        $normalizeContact = function($contact) {
                            if (empty($contact)) return null;
                            // Remove @server suffix e caracteres não-numéricos
                            $cleaned = preg_replace('/@.*$/', '', (string) $contact);
                            return preg_replace('/\D/', '', $cleaned);
                        };
                        
                        $normalizedContact = $normalizeContact($conversationData['contact_external_id']);
                        $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
                        $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
                        
                        // CORREÇÃO: Para outbound, também verifica se os últimos dígitos batem
                        // (evita falsos negativos por diferença de formato de número)
                        $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContact;
                        $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContact;
                        
                        // CORREÇÃO: Se não bateu exato, tenta pelos últimos 8-10 dígitos
                        if (!$isFromThisContact && !$isToThisContact) {
                            $last8Contact = substr($normalizedContact, -8);
                            $last8From = $normalizedFrom ? substr($normalizedFrom, -8) : null;
                            $last8To = $normalizedTo ? substr($normalizedTo, -8) : null;
                            
                            $isFromThisContact = $last8From && $last8From === $last8Contact;
                            $isToThisContact = $last8To && $last8To === $last8Contact;
                            
                            if ($isFromThisContact || $isToThisContact) {
                                error_log("[CommunicationHub::getMessage] Validação por últimos 8 dígitos - PASSOU");
                            }
                        }
                        
                        if (!$isFromThisContact && !$isToThisContact) {
                            // CORREÇÃO: Se é outbound do sistema, permite (confia no tenant_id)
                            $isSystemOutbound = $event['event_type'] === 'whatsapp.outbound.message' && 
                                               !empty($event['tenant_id']) &&
                                               $event['tenant_id'] == ($conversationData['tenant_id'] ?? null);
                            
                            if ($isSystemOutbound) {
                                error_log("[CommunicationHub::getMessage] Outbound do sistema - permitido por tenant_id match");
                            } else {
                                error_log("[CommunicationHub::getMessage] REJEITADO - Mensagem não pertence à thread. event_id={$eventId} thread_id={$threadId} contact={$normalizedContact} from={$normalizedFrom} to={$normalizedTo}");
                                $this->json(['success' => false, 'error' => 'Mensagem não pertence à thread'], 403);
                                return;
                            }
                        }
                    }
                }
            }

            $payload = json_decode($event['payload'], true);
            $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            
            // CORREÇÃO: Inclui caption para imagens/vídeos com legenda
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body']
                ?? $payload['caption']
                ?? $payload['message']['caption']
                ?? $payload['raw']['payload']['caption']  // WPPConnect image/video caption
                ?? '';
            $content = $this->resolveTemplateBody($db, $content);
            
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
            
            $eventMetadata = json_decode($event['metadata'] ?? '{}', true);
            $message = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => $eventMetadata,
                'media' => $mediaInfo, // Inclui objeto media completo quando existir
                // Campos para identificação do remetente
                'sent_by_name' => $eventMetadata['sent_by_name'] ?? null, // Nome do operador que enviou (outbound)
                'sent_by' => $eventMetadata['sent_by'] ?? null // ID do operador que enviou (outbound)
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
            
            // CORREÇÃO: Inclui caption para imagens/vídeos com legenda
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body']
                ?? $payload['caption']
                ?? $payload['message']['caption']
                ?? $payload['raw']['payload']['caption']  // WPPConnect image/video caption
                ?? '';
            $content = $this->resolveTemplateBody($db, $content);
            
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

            $payloadType = $payload['type'] ?? $payload['message']['type'] ?? $payload['raw']['payload']['type'] ?? null;
            $hasMediaIndicator = $payloadType && in_array($payloadType, ['audio', 'ptt', 'image', 'video', 'document', 'sticker']);
            if (!$mediaInfo && $hasMediaIndicator) {
                $mediaInfo = [
                    'media_failed' => true,
                    'media_type' => $payloadType === 'ptt' ? 'audio' : $payloadType,
                    'mime_type' => $payloadType === 'ptt' || $payloadType === 'audio' ? 'audio/ogg' : ($payloadType === 'image' ? 'image/jpeg' : null),
                ];
            }
            
            // Se não encontrou mídia e não há conteúdo, mostra tipo de mídia
            // Inclui raw.payload.type (WPPConnect: ciphertext = mensagem criptografada E2E)
            if (empty($content) && !$mediaInfo) {
                if ($payloadType) {
                    $content = ($payloadType === 'ciphertext')
                        ? '[Mensagem criptografada]'
                        : "[{$payloadType}]";
                }
            }
            
            // Sanitiza mensagens muito longas sem quebra
            $content = self::sanitizeLongMessage($content);
            
            $eventMetadata = json_decode($event['metadata'] ?? '{}', true);
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => $eventMetadata,
                'media' => $mediaInfo, // Informações da mídia (se houver)
                // Campos para identificação do remetente
                'sent_by_name' => $eventMetadata['sent_by_name'] ?? null, // Nome do operador que enviou (outbound)
                'sent_by' => $eventMetadata['sent_by'] ?? null // ID do operador que enviou (outbound)
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
     * Normaliza número de telefone para comparação
     * Remove 9º dígito em números BR se necessário para matching
     * @param string $phone Número de telefone
     * @return string Número normalizado
     */
    private static function normalizePhone(string $phone): string
    {
        // Apenas dígitos
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // Se não é BR ou é muito curto, retorna como está
        if (strlen($digits) < 12 || substr($digits, 0, 2) !== '55') {
            return $digits;
        }
        
        // Número BR completo: 55 + DDD (2) + número (8 ou 9)
        // Se tem 13 dígitos (com 9º dígito), normaliza para 12 (sem 9º dígito)
        if (strlen($digits) === 13) {
            // Remove 9º dígito: 55 + DDD(2) + 9 + 8dig => 55 + DDD(2) + 8dig
            return substr($digits, 0, 4) . substr($digits, 5);
        }
        
        return $digits;
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

        // Flag: se force_create=true, ignora duplicidade (usuário confirmou)
        $forceCreate = !empty($input['force_create']);

        $db = DB::getConnection();

        try {
            // Busca a conversa
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if (!$conversation) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            // Proteção contra duplicidade por telefone
            $phoneToCheck = $phone ?: ($conversation['contact_external_id'] ?? '');
            if (!$forceCreate && !empty($phoneToCheck)) {
                $duplicates = \PixelHub\Services\LeadService::findDuplicatesByPhone($phoneToCheck);
                $totalDuplicates = count($duplicates['leads']) + count($duplicates['tenants']);
                
                // Converte para formato esperado pelo frontend
                $formattedDuplicates = self::convertDuplicatesToContactFormat($duplicates);
                
                if ($totalDuplicates > 0) {
                    $this->json([
                        'success' => false,
                        'code' => 'DUPLICATE_PHONE',
                        'match_type' => 'phone',
                        'message' => 'Já existe(m) registro(s) com este telefone. Deseja vincular a um existente ou criar mesmo assim?',
                        'duplicates' => $formattedDuplicates,
                        'conversation_id' => $conversationId,
                    ]);
                    return;
                }
            }

            // Proteção contra duplicidade por e-mail
            if (!$forceCreate && !empty($email)) {
                $emailDuplicates = \PixelHub\Services\LeadService::findDuplicatesByEmail($email);
                $totalEmailDuplicates = count($emailDuplicates['leads']) + count($emailDuplicates['tenants']);
                if ($totalEmailDuplicates > 0) {
                    $this->json([
                        'success' => false,
                        'code' => 'DUPLICATE_PHONE',
                        'match_type' => 'email',
                        'message' => 'Já existe(m) registro(s) com este e-mail. Deseja vincular a um existente ou criar mesmo assim?',
                        'duplicates' => self::convertDuplicatesToContactFormat($emailDuplicates),
                        'conversation_id' => $conversationId,
                    ]);
                    return;
                }
            }

            $db->beginTransaction();

            // Cria o tenant
            $stmt = $db->prepare("
                INSERT INTO tenants 
                (name, phone, email, person_type, status, created_at, updated_at)
                VALUES (?, ?, ?, 'pf', 'active', NOW(), NOW())
            ");
            $stmt->execute([
                $name,
                $phoneToCheck ?: null,
                $email ?: null
            ]);

            $tenantId = (int) $db->lastInsertId();

            // Atualiza a conversa vinculando ao tenant
            $updateStmt = $db->prepare("
                UPDATE conversations 
                SET tenant_id = ?,
                    lead_id = NULL,
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
            if ($db->inTransaction()) $db->rollBack();
            error_log("[CommunicationHub] Erro ao criar tenant: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cria lead a partir de uma conversa não vinculada
     * 
     * POST /communication-hub/incoming-lead/create-lead
     */
    public function createLeadFromConversation(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;
        $name = trim($input['name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $email = trim($input['email'] ?? '');
        $notes = trim($input['notes'] ?? '');

        if ($conversationId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id é obrigatório'], 400);
            return;
        }

        if (empty($name)) {
            $this->json(['success' => false, 'error' => 'Nome é obrigatório'], 400);
            return;
        }

        // Flag: se force_create=true, ignora duplicidade (usuário confirmou)
        $forceCreate = !empty($input['force_create']);

        $db = DB::getConnection();

        try {
            // Busca a conversa
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if (!$conversation) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            // Proteção contra duplicidade por telefone
            $phoneToCheck = $phone ?: ($conversation['contact_external_id'] ?? '');
            if (!$forceCreate && !empty($phoneToCheck)) {
                $duplicates = \PixelHub\Services\LeadService::findDuplicatesByPhone($phoneToCheck);
                $totalDuplicates = count($duplicates['leads']) + count($duplicates['tenants']);
                
                // Converte para formato esperado pelo frontend
                $formattedDuplicates = self::convertDuplicatesToContactFormat($duplicates);
                
                if ($totalDuplicates > 0) {
                    $this->json([
                        'success' => false,
                        'code' => 'DUPLICATE_PHONE',
                        'match_type' => 'phone',
                        'message' => 'Já existe(m) registro(s) com este telefone. Deseja vincular a um existente ou criar mesmo assim?',
                        'duplicates' => $formattedDuplicates,
                        'conversation_id' => $conversationId,
                    ]);
                    return;
                }
            }

            // Proteção contra duplicidade por e-mail
            if (!$forceCreate && !empty($email)) {
                $emailDuplicates = \PixelHub\Services\LeadService::findDuplicatesByEmail($email);
                $totalEmailDuplicates = count($emailDuplicates['leads']) + count($emailDuplicates['tenants']);
                if ($totalEmailDuplicates > 0) {
                    $this->json([
                        'success' => false,
                        'code' => 'DUPLICATE_PHONE',
                        'match_type' => 'email',
                        'message' => 'Já existe(m) registro(s) com este e-mail. Deseja vincular a um existente ou criar mesmo assim?',
                        'duplicates' => self::convertDuplicatesToContactFormat($emailDuplicates),
                        'conversation_id' => $conversationId,
                    ]);
                    return;
                }
            }

            // Busca última mensagem da conversa para detecção de tracking
            $lastMessage = self::getLastConversationMessage($conversationId);

            // Cria o lead na tabela leads (legada)
            $leadId = self::createLeadInLegacyTable([
                'name' => $name,
                'company' => null, // Não informado no modal do Inbox
                'phone' => $phoneToCheck ?: null,
                'email' => $email ?: null,
                'source' => 'whatsapp',
                'notes' => $notes ?: null,
                'message' => $lastMessage, // Para detecção de tracking
                'created_by' => $_SESSION['user_id'] ?? null,
            ]);

            // Vincula conversa ao lead
            self::linkConversationToLeadInternal($conversationId, $leadId);

            // Aplica tracking na oportunidade vinculada ao lead recém-criado
            self::applyTrackingToOpportunity($conversationId, $leadId);

            $this->json([
                'success' => true,
                'lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'message' => 'Lead criado e conversa vinculada com sucesso'
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao criar lead: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cria lead na tabela leads (legada)
     * 
     * @param array $data Dados do lead
     * @return int ID do lead criado
     */
    private static function createLeadInLegacyTable(array $data): int
    {
        $db = DB::getConnection();

        // Validação básica
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }

        // Detecção automática de tracking (se houver mensagem)
        $trackingData = self::detectTrackingInMessage($data['message'] ?? '');

        // Prepara campos para tabela leads
        $fields = [
            'name' => trim($data['name']),
            'company' => !empty($data['company']) ? trim($data['company']) : null,
            'phone' => !empty($data['phone']) ? trim($data['phone']) : null,
            'email' => !empty($data['email']) ? trim($data['email']) : null,
            'source' => $trackingData['origin'] ?? $data['source'] ?? 'unknown',
            'status' => 'new',
            'notes' => $data['notes'] ?? null,
            'tracking_code' => $trackingData['tracking_code'] ?? null,
            'tracking_metadata' => !empty($trackingData['tracking_metadata']) ? json_encode($trackingData['tracking_metadata']) : null,
            'tracking_auto_detected' => $trackingData['tracking_auto_detected'] ?? false,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Constrói SQL dinamicamente
        $columns = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        
        $stmt = $db->prepare("
            INSERT INTO leads ({$columns})
            VALUES ({$placeholders})
        ");

        $stmt->execute(array_values($fields));

        $leadId = (int) $db->lastInsertId();
        
        error_log("[CommunicationHub] Lead {$leadId} criado na tabela leads (legada)");
        
        return $leadId;
    }

    /**
     * Detecta tracking code em uma mensagem
     * 
     * @param string $message Mensagem para analisar
     * @return array Dados do tracking detectado ou fallback
     */
    private static function detectTrackingInMessage(string $message): array
    {
        try {
            $trackingService = new \PixelHub\Services\TrackingDetectionService();
            $detected = $trackingService->detectInMessage($message);
            
            if ($detected) {
                error_log("[CommunicationHub] Tracking detectado: {$detected['tracking_code']} (origem: {$detected['origin']})");
                return $detected;
            }
            
            return $trackingService->getUnknownFallback();
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro na detecção de tracking: " . $e->getMessage());
            return [
                'tracking_code' => null,
                'origin' => 'unknown',
                'tracking_metadata' => null,
                'tracking_auto_detected' => false
            ];
        }
    }

    /**
     * Busca última mensagem de uma conversa para detecção de tracking
     * 
     * @param int $conversationId ID da conversa
     * @return string|null Texto da última mensagem ou null
     */
    private static function getLastConversationMessage(int $conversationId): ?string
    {
        try {
            $db = DB::getConnection();
            
            $stmt = $db->prepare("
                SELECT payload 
                FROM communication_events 
                WHERE conversation_id = ? 
                AND event_type = 'whatsapp.inbound.message'
                ORDER BY created_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$conversationId]);
            $event = $stmt->fetch();
            
            if ($event && !empty($event['payload'])) {
                $data = json_decode($event['payload'], true);
                return $data['message']['text'] ?? $data['text']['body'] ?? null;
            }
            
            return null;
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao buscar última mensagem: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Converte formato LeadService para formato ContactService (frontend)
     * 
     * @param array $duplicates Formato ['leads' => [...], 'tenants' => [...]]
     * @return array Formato unificado de contatos
     */
    private static function convertDuplicatesToContactFormat(array $duplicates): array
    {
        $result = [];
        
        // Adiciona leads
        foreach ($duplicates['leads'] as $lead) {
            $result[] = [
                'id' => $lead['id'],
                'name' => $lead['name'],
                'phone' => $lead['phone'],
                'email' => $lead['email'],
                'type' => 'lead',
                'contact_type' => 'lead',
                'company' => $lead['company'] ?? null,
                'source' => $lead['source'] ?? null,
                'status' => $lead['status'] ?? null,
            ];
        }
        
        // Adiciona tenants
        foreach ($duplicates['tenants'] as $tenant) {
            $result[] = [
                'id' => $tenant['id'],
                'name' => $tenant['name'],
                'phone' => $tenant['phone'],
                'email' => $tenant['email'],
                'type' => 'tenant',
                'contact_type' => $tenant['contact_type'] ?? 'client',
                'company' => null,
                'source' => $tenant['source'] ?? null,
                'status' => $tenant['status'] ?? null,
            ];
        }
        
        return $result;
    }

    /**
     * Vincula conversa a um lead (método interno)
     * 
     * @param int $conversationId ID da conversa
     * @param int $leadId ID do lead
     * @return bool Sucesso da operação
     */
    private static function linkConversationToLeadInternal(int $conversationId, int $leadId): bool
    {
        $db = DB::getConnection();
        
        try {
            // Atualiza conversa com lead_id e remove flag de incoming lead
            $stmt = $db->prepare("
                UPDATE conversations 
                SET lead_id = ?, is_incoming_lead = 0, updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$leadId, $conversationId]);
            
            if ($result) {
                error_log("[CommunicationHub] Conversa {$conversationId} vinculada ao lead {$leadId}");
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao vincular conversa {$conversationId} ao lead {$leadId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lê toda a conversa e aplica tracking na oportunidade vinculada ao lead.
     * Chamado apenas no momento da criação ou vinculação do lead — nunca periodicamente.
     * Não sobrescreve tracking já existente na oportunidade.
     */
    private static function applyTrackingToOpportunity(int $conversationId, int $leadId): void
    {
        try {
            $db = DB::getConnection();

            // Busca oportunidade vinculada ao lead (mais recente)
            $oppStmt = $db->prepare("
                SELECT id, tracking_code, origin FROM opportunities
                WHERE lead_id = ? AND status = 'active'
                ORDER BY created_at DESC LIMIT 1
            ");
            $oppStmt->execute([$leadId]);
            $opp = $oppStmt->fetch();

            if (!$opp || !empty($opp['tracking_code'])) {
                return; // Sem oportunidade ou tracking já preenchido
            }

            // Lê todas as mensagens inbound da conversa
            $msgsStmt = $db->prepare("
                SELECT payload FROM communication_events
                WHERE conversation_id = ?
                AND event_type = 'whatsapp.inbound.message'
                ORDER BY created_at ASC
            ");
            $msgsStmt->execute([$conversationId]);
            $msgs = $msgsStmt->fetchAll();

            $fullText = '';
            foreach ($msgs as $msg) {
                $data = json_decode($msg['payload'], true);
                $text = $data['message']['text']
                    ?? $data['message']['body']
                    ?? $data['text']
                    ?? $data['body']
                    ?? '';
                if ($text) {
                    $fullText .= ' ' . $text;
                }
            }

            if (empty(trim($fullText))) {
                return;
            }

            $detected = \PixelHub\Services\TrackingCodesService::detectFromMessage(trim($fullText));
            if (!$detected || empty($detected['tracking_code'])) {
                return;
            }

            // Usa channel como origin (valor do OriginCatalog), fallback para source legado
            $originFromTracking = $detected['tracking_channel'] ?? $detected['tracking_source'] ?? null;
            $currentOrigin = $opp['origin'] ?? 'unknown';
            $newOrigin = ($currentOrigin === 'unknown' || empty($currentOrigin))
                ? ($originFromTracking ?? $currentOrigin)
                : $currentOrigin;

            $updStmt = $db->prepare("
                UPDATE opportunities
                SET tracking_code = ?,
                    tracking_metadata = ?,
                    tracking_auto_detected = 1,
                    origin = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updStmt->execute([
                $detected['tracking_code'],
                $detected['tracking_metadata'] ?? null,
                $newOrigin,
                (int) $opp['id']
            ]);

            error_log(sprintf(
                '[TrackingAutoDetect] Aplicado na oportunidade: opp_id=%d, code=%s, origin=%s',
                (int) $opp['id'],
                $detected['tracking_code'],
                $newOrigin
            ));

        } catch (\Throwable $e) {
            error_log('[TrackingAutoDetect] Erro (não crítico): ' . $e->getMessage());
        }
    }

    /**
     * Vincula conversa a um lead existente
     * 
     * POST /communication-hub/incoming-lead/link-lead
     */
    public function linkConversationToLead(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;
        $leadId = isset($input['lead_id']) ? (int) $input['lead_id'] : 0;

        if ($conversationId <= 0 || $leadId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id e lead_id são obrigatórios'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            // Verifica se a conversa existe
            $stmt = $db->prepare("SELECT * FROM conversations WHERE id = ?");
            $stmt->execute([$conversationId]);
            $conversation = $stmt->fetch();

            if (!$conversation) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            // Verifica se o lead existe (tabela leads legada)
            $stmt = $db->prepare("SELECT * FROM leads WHERE id = ?");
            $stmt->execute([$leadId]);
            $lead = $stmt->fetch();
            
            if (!$lead) {
                $this->json(['success' => false, 'error' => 'Lead não encontrado'], 404);
                return;
            }

            // Vincula (inclui criação automática de opportunity se necessário)
            self::linkConversationToLeadInternal($conversationId, $leadId);

            // Aplica tracking na oportunidade vinculada ao lead
            self::applyTrackingToOpportunity($conversationId, $leadId);

            // Verifica se opportunity foi criada automaticamente para dar feedback
            $db = DB::getConnection();
            $stmt = $db->prepare("
                SELECT id, stage FROM opportunities 
                WHERE lead_id = ? AND status = 'active' 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$leadId]);
            $opportunity = $stmt->fetch();

            $message = 'Conversa vinculada ao lead com sucesso';
            if ($opportunity && $opportunity['stage'] === 'new') {
                $message .= '. Oportunidade criada automaticamente em "Novo"';
            }

            $this->json([
                'success' => true,
                'lead_id' => $leadId,
                'conversation_id' => $conversationId,
                'opportunity_id' => $opportunity['id'] ?? null,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao vincular lead: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista leads para modal de vincular
     * 
     * GET /communication-hub/leads-list
     */
    public function getLeadsList(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $search = $_GET['search'] ?? null;

        try {
            $leads = \PixelHub\Services\ContactService::searchLeads($search, 200);
            $this->json(['success' => true, 'leads' => $leads]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao listar leads: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verifica duplicidade por telefone (leads + tenants)
     * 
     * GET /communication-hub/check-phone-duplicates?phone=5547999999999
     */
    public function checkPhoneDuplicates(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $phone = $_GET['phone'] ?? '';

        if (empty($phone)) {
            $this->json(['success' => true, 'duplicates' => ['leads' => [], 'tenants' => []], 'total' => 0]);
            return;
        }

        try {
            $duplicates = \PixelHub\Services\LeadService::findDuplicatesByPhone($phone);
            $formattedDuplicates = self::convertDuplicatesToContactFormat($duplicates);
            $total = count($formattedDuplicates);
            $this->json([
                'success' => true,
                'duplicates' => $formattedDuplicates,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao verificar duplicidade: " . $e->getMessage());
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

            // Permite vincular se:
            // 1. É um incoming lead (is_incoming_lead = 1), OU
            // 2. Não tem tenant definido (tenant_id IS NULL)
            // Isso permite vincular conversas que foram desvinculadas anteriormente
            if (!$conversation['is_incoming_lead'] && !empty($conversation['tenant_id'])) {
                $db->rollBack();
                $this->json([
                    'success' => false, 
                    'error' => 'Esta conversa já está vinculada a um cliente. Use a opção "Alterar cliente" para mudar o vínculo.'
                ], 400);
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
     * Atualiza o status de uma conversa (arquivar, ignorar, reativar)
     * 
     * POST /communication-hub/conversation/update-status
     * 
     * Body JSON:
     * - conversation_id: int (obrigatório)
     * - status: string (obrigatório) - 'active', 'archived', 'ignored'
     */
    public function updateConversationStatus(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;
        $newStatus = $input['status'] ?? '';

        // Validação
        if ($conversationId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id é obrigatório'], 400);
            return;
        }

        $allowedStatuses = ['active', 'archived', 'ignored'];
        if (!in_array($newStatus, $allowedStatuses)) {
            $this->json(['success' => false, 'error' => 'Status inválido. Use: ' . implode(', ', $allowedStatuses)], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            // Verifica se a conversa existe
            $checkStmt = $db->prepare("SELECT id, status, contact_name FROM conversations WHERE id = ?");
            $checkStmt->execute([$conversationId]);
            $conversation = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$conversation) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            // Atualiza o status
            $stmt = $db->prepare("
                UPDATE conversations 
                SET status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newStatus, $conversationId]);

            // Mensagens de feedback
            $messages = [
                'active' => 'Conversa reativada',
                'archived' => 'Conversa arquivada',
                'ignored' => 'Conversa ignorada'
            ];

            error_log(sprintf(
                "[CommunicationHub] Status da conversa %d alterado: %s -> %s (contato: %s)",
                $conversationId,
                $conversation['status'],
                $newStatus,
                $conversation['contact_name'] ?? 'N/A'
            ));

            $this->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'old_status' => $conversation['status'],
                'new_status' => $newStatus,
                'message' => $messages[$newStatus] ?? 'Status atualizado'
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao atualizar status da conversa: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Exclui uma conversa permanentemente (exclusão REAL como no WhatsApp)
     * 
     * POST /communication-hub/conversation/delete
     * 
     * Body JSON:
     * - conversation_id: int (obrigatório)
     * 
     * CORREÇÃO: Agora deleta também eventos órfãos (sem conversation_id) que batem
     * com o número de telefone. Isso garante que o histórico não "volte" quando
     * uma nova mensagem é recebida desse contato.
     */
    public function deleteConversation(): void
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
            $db->beginTransaction();

            // Verifica se a conversa existe e pega dados para exclusão abrangente
            $checkStmt = $db->prepare("
                SELECT id, contact_name, contact_external_id, channel_id, session_id, status 
                FROM conversations WHERE id = ?
            ");
            $checkStmt->execute([$conversationId]);
            $conversation = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$conversation) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            $contactExternalId = $conversation['contact_external_id'] ?? null;
            $channelId = $conversation['channel_id'] ?? $conversation['session_id'] ?? null;
            
            // 1. Remove mídias associadas aos eventos da conversa
            $deleteMediaStmt = $db->prepare("
                DELETE FROM communication_media 
                WHERE event_id IN (SELECT event_id FROM communication_events WHERE conversation_id = ?)
            ");
            $deleteMediaStmt->execute([$conversationId]);
            $deletedMedia = $deleteMediaStmt->rowCount();
            
            // 1b. Remove eventos do chatbot (chatbot_events)
            try {
                $db->prepare("DELETE FROM chatbot_events WHERE conversation_id = ?")->execute([$conversationId]);
            } catch (\Exception $e) {
                error_log("[CommunicationHub] Aviso: chatbot_events não deletados: " . $e->getMessage());
            }

            // 2. Remove eventos associados por conversation_id
            $deleteEventsStmt = $db->prepare("DELETE FROM communication_events WHERE conversation_id = ?");
            $deleteEventsStmt->execute([$conversationId]);
            $deletedByConvId = $deleteEventsStmt->rowCount();

            // 2. Remove eventos órfãos (sem conversation_id) que batem com o número de telefone
            // Isso é CRÍTICO para garantir exclusão permanente real
            $deletedOrphans = 0;
            if (!empty($contactExternalId)) {
                // Normaliza número para busca
                $normalizedNumber = preg_replace('/[^0-9]/', '', preg_replace('/@.*$/', '', $contactExternalId));
                
                if (!empty($normalizedNumber)) {
                    // Cria padrões de busca (com/sem 9º dígito para números BR)
                    $patterns = ["%{$normalizedNumber}%"];
                    
                    // Se for número BR, adiciona variação
                    if (strlen($normalizedNumber) >= 12 && substr($normalizedNumber, 0, 2) === '55') {
                        if (strlen($normalizedNumber) === 13) {
                            // Remove 9º dígito
                            $without9th = substr($normalizedNumber, 0, 4) . substr($normalizedNumber, 5);
                            $patterns[] = "%{$without9th}%";
                        } elseif (strlen($normalizedNumber) === 12) {
                            // Adiciona 9º dígito
                            $with9th = substr($normalizedNumber, 0, 4) . '9' . substr($normalizedNumber, 4);
                            $patterns[] = "%{$with9th}%";
                        }
                    }
                    
                    // Adiciona padrão com @lid se existir
                    if (strpos($contactExternalId, '@lid') !== false) {
                        $patterns[] = "%{$contactExternalId}%";
                    }
                    
                    // Deleta eventos órfãos que batem com os padrões
                    foreach ($patterns as $pattern) {
                        $deleteOrphansStmt = $db->prepare("
                            DELETE FROM communication_events 
                            WHERE conversation_id IS NULL
                            AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                            AND (
                                JSON_UNQUOTE(JSON_EXTRACT(payload, '$.from')) LIKE ?
                                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.from')) LIKE ?
                                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.to')) LIKE ?
                                OR JSON_UNQUOTE(JSON_EXTRACT(payload, '$.message.to')) LIKE ?
                            )
                        ");
                        $deleteOrphansStmt->execute([$pattern, $pattern, $pattern, $pattern]);
                        $deletedOrphans += $deleteOrphansStmt->rowCount();
                    }
                }
            }

            // 3. Remove a conversa
            $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
            $deleteStmt->execute([$conversationId]);

            $db->commit();

            $totalDeleted = $deletedByConvId + $deletedOrphans;

            error_log(sprintf(
                "[CommunicationHub] Conversa %d EXCLUÍDA PERMANENTEMENTE: contato=%s, numero=%s, midias=%d, eventos_por_conv_id=%d, eventos_orfaos=%d, total_eventos=%d",
                $conversationId,
                $conversation['contact_name'] ?? 'N/A',
                $contactExternalId ?? 'N/A',
                $deletedMedia,
                $deletedByConvId,
                $deletedOrphans,
                $totalDeleted
            ));

            $this->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'deleted_media' => $deletedMedia,
                'deleted_events' => $totalDeleted,
                'deleted_by_conversation_id' => $deletedByConvId,
                'deleted_orphan_events' => $deletedOrphans,
                'message' => "Conversa excluída permanentemente. {$totalDeleted} mensagens e {$deletedMedia} mídias removidas."
            ]);

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[CommunicationHub] Erro ao excluir conversa: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Exclui uma mensagem individual permanentemente
     * 
     * POST /communication-hub/message/delete
     * 
     * Body JSON:
     * - event_id: string (obrigatório) - ID do evento/mensagem
     * 
     * Implementa soft delete através de campo deleted_at na tabela communication_events
     */
    public function deleteMessage(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $eventId = $input['event_id'] ?? '';

        if (empty($eventId)) {
            $this->json(['success' => false, 'error' => 'event_id é obrigatório'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            // Verifica se o evento existe e é uma mensagem
            $checkStmt = $db->prepare("
                SELECT id, event_id, event_type, conversation_id, 
                       JSON_EXTRACT(payload, '$.content') as content,
                       JSON_EXTRACT(payload, '$.message.body') as body,
                       created_at
                FROM communication_events 
                WHERE event_id = ? 
                AND event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                AND deleted_at IS NULL
            ");
            $checkStmt->execute([$eventId]);
            $event = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$event) {
                $this->json(['success' => false, 'error' => 'Mensagem não encontrada ou já excluída'], 404);
                return;
            }

            // Soft delete - marca como excluído
            $deleteStmt = $db->prepare("
                UPDATE communication_events 
                SET deleted_at = NOW() 
                WHERE event_id = ?
            ");
            $deleteStmt->execute([$eventId]);

            // Remove mídia associada se existir
            $deleteMediaStmt = $db->prepare("
                DELETE FROM communication_media 
                WHERE event_id = ?
            ");
            $deleteMediaStmt->execute([$eventId]);
            $deletedMedia = $deleteMediaStmt->rowCount();

            // Log da operação
            $messageContent = substr($event['content'] ?: $event['body'] ?: 'Mídia', 0, 100);
            error_log(sprintf(
                "[CommunicationHub] Mensagem %s EXCLUÍDA: event_id=%s, conversation_id=%s, content=%s, midias=%d",
                $eventId,
                $event['id'],
                $event['conversation_id'],
                $messageContent,
                $deletedMedia
            ));

            $this->json([
                'success' => true,
                'event_id' => $eventId,
                'deleted_media' => $deletedMedia,
                'message' => "Mensagem excluída permanentemente."
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao excluir mensagem: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Desvincula uma conversa de um tenant (move para "Não vinculados")
     * 
     * POST /communication-hub/conversation/unlink
     * 
     * Body JSON:
     * - conversation_id: int (obrigatório)
     * 
     * Resultado:
     * - tenant_id = NULL
     * - is_incoming_lead = 1
     * - Conversa aparece em "Não vinculados"
     */
    public function unlinkConversation(): void
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
            // Verifica se a conversa existe
            $checkStmt = $db->prepare("
                SELECT id, contact_name, contact_external_id, tenant_id, is_incoming_lead 
                FROM conversations WHERE id = ?
            ");
            $checkStmt->execute([$conversationId]);
            $conversation = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$conversation) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            if (empty($conversation['tenant_id']) && !empty($conversation['is_incoming_lead'])) {
                $this->json(['success' => false, 'error' => 'Conversa já está em não vinculados'], 400);
                return;
            }

            // Move para não-vinculados: tenant_id = NULL, is_incoming_lead = 1
            $updateStmt = $db->prepare("
                UPDATE conversations 
                SET tenant_id = NULL, 
                    is_incoming_lead = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$conversationId]);

            error_log(sprintf(
                "[CommunicationHub] Conversa %d movida para NAO-VINCULADOS: contato=%s, numero=%s, antigo_tenant_id=%s",
                $conversationId,
                $conversation['contact_name'] ?? 'N/A',
                $conversation['contact_external_id'] ?? 'N/A',
                $conversation['tenant_id'] ?? 'NULL'
            ));

            $this->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'message' => 'Conversa movida para "Não vinculados" com sucesso.'
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao desvincular conversa: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza o nome de exibição do contato em uma conversa
     * 
     * POST /communication-hub/conversation/update-contact-name
     * Body JSON:
     * - conversation_id: int (obrigatório)
     * - contact_name: string (obrigatório) - novo nome do contato
     */
    public function updateContactName(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $conversationId = isset($input['conversation_id']) ? (int) $input['conversation_id'] : 0;
        $newName = trim($input['contact_name'] ?? '');

        // Validação
        if ($conversationId <= 0) {
            $this->json(['success' => false, 'error' => 'conversation_id é obrigatório'], 400);
            return;
        }

        if (empty($newName)) {
            $this->json(['success' => false, 'error' => 'contact_name é obrigatório'], 400);
            return;
        }

        if (strlen($newName) > 255) {
            $this->json(['success' => false, 'error' => 'contact_name muito longo (máx 255 caracteres)'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            // Verifica se a conversa existe
            $checkStmt = $db->prepare("SELECT id, contact_name, contact_external_id FROM conversations WHERE id = ?");
            $checkStmt->execute([$conversationId]);
            $conversation = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$conversation) {
                $this->json(['success' => false, 'error' => 'Conversa não encontrada'], 404);
                return;
            }

            $oldName = $conversation['contact_name'];

            // Atualiza o nome do contato
            $stmt = $db->prepare("
                UPDATE conversations 
                SET contact_name = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newName, $conversationId]);

            error_log(sprintf(
                "[CommunicationHub] Nome do contato atualizado: conv_id=%d, numero=%s, de='%s' para='%s'",
                $conversationId,
                $conversation['contact_external_id'],
                $oldName ?? 'N/A',
                $newName
            ));

            $this->json([
                'success' => true,
                'conversation_id' => $conversationId,
                'old_name' => $oldName,
                'new_name' => $newName,
                'message' => 'Nome do contato atualizado com sucesso'
            ]);

        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao atualizar nome do contato: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Unifica duas conversas duplicadas (move eventos e deleta a conversa de origem)
     * 
     * POST /communication-hub/conversation/merge
     * Body JSON:
     * - source_conversation_id: int (obrigatório) - conversa que será absorvida e deletada
     * - target_conversation_id: int (obrigatório) - conversa que receberá os eventos
     * - delete_source: bool (opcional, default true) - se deve deletar a conversa de origem
     */
    public function mergeConversations(): void
    {
        Auth::requireInternal();

        $input = json_decode(file_get_contents('php://input'), true);
        $sourceId = isset($input['source_conversation_id']) ? (int) $input['source_conversation_id'] : 0;
        $targetId = isset($input['target_conversation_id']) ? (int) $input['target_conversation_id'] : 0;
        $deleteSource = $input['delete_source'] ?? true;

        // Validação
        if ($sourceId <= 0) {
            $this->json(['success' => false, 'error' => 'source_conversation_id é obrigatório'], 400);
            return;
        }

        if ($targetId <= 0) {
            $this->json(['success' => false, 'error' => 'target_conversation_id é obrigatório'], 400);
            return;
        }

        if ($sourceId === $targetId) {
            $this->json(['success' => false, 'error' => 'source e target não podem ser iguais'], 400);
            return;
        }

        $db = DB::getConnection();

        try {
            $db->beginTransaction();

            // Verifica se ambas conversas existem
            $checkStmt = $db->prepare("SELECT id, contact_name, contact_external_id, message_count FROM conversations WHERE id IN (?, ?)");
            $checkStmt->execute([$sourceId, $targetId]);
            $conversations = $checkStmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($conversations) !== 2) {
                $db->rollBack();
                $this->json(['success' => false, 'error' => 'Uma ou ambas as conversas não foram encontradas'], 404);
                return;
            }

            $source = null;
            $target = null;
            foreach ($conversations as $conv) {
                if ($conv['id'] == $sourceId) $source = $conv;
                if ($conv['id'] == $targetId) $target = $conv;
            }

            // Move eventos da conversa de origem para o destino
            $moveStmt = $db->prepare("
                UPDATE communication_events 
                SET conversation_id = ? 
                WHERE conversation_id = ?
            ");
            $moveStmt->execute([$targetId, $sourceId]);
            $movedEvents = $moveStmt->rowCount();

            // Atualiza contadores da conversa destino
            $updateTargetStmt = $db->prepare("
                UPDATE conversations 
                SET 
                    message_count = COALESCE(message_count, 0) + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateTargetStmt->execute([$movedEvents, $targetId]);

            $deletedSource = false;
            if ($deleteSource) {
                // Remove eventos restantes (se houver) e deleta a conversa de origem
                $db->prepare("DELETE FROM communication_events WHERE conversation_id = ?")->execute([$sourceId]);
                $deleteStmt = $db->prepare("DELETE FROM conversations WHERE id = ?");
                $deleteStmt->execute([$sourceId]);
                $deletedSource = $deleteStmt->rowCount() > 0;
            }

            $db->commit();

            error_log(sprintf(
                "[CommunicationHub] Conversas unificadas: source=%d (%s) -> target=%d (%s), eventos_movidos=%d, source_deletada=%s",
                $sourceId,
                $source['contact_name'] ?? 'N/A',
                $targetId,
                $target['contact_name'] ?? 'N/A',
                $movedEvents,
                $deletedSource ? 'sim' : 'não'
            ));

            $this->json([
                'success' => true,
                'source_conversation_id' => $sourceId,
                'target_conversation_id' => $targetId,
                'moved_events' => $movedEvents,
                'source_deleted' => $deletedSource,
                'message' => "Conversa unificada com sucesso. {$movedEvents} eventos movidos."
            ]);

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[CommunicationHub] Erro ao unificar conversas: " . $e->getMessage());
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
        
        // Garante que começa com whatsapp-media e não contém path traversal
        if ($pathParts[0] !== 'whatsapp-media' || strpos($path, '..') !== false) {
            http_response_code(403);
            echo "Caminho inválido";
            exit;
        }
        
        $storageBase = realpath(__DIR__ . '/../../storage') ?: (__DIR__ . '/../../storage');
        $absolutePath = $storageBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        
        // Resolve links simbólicos e garante que está dentro de storage (path traversal)
        $resolved = realpath($absolutePath);
        if ($resolved !== false) {
            $baseReal = realpath($storageBase);
            if ($baseReal !== false && strpos($resolved, $baseReal) !== 0) {
                http_response_code(403);
                echo "Caminho inválido";
                exit;
            }
            $absolutePath = $resolved;
        }
        
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
        
        // Envia arquivo com suporte a Range Requests (necessário para metadados de áudio)
        $fileSize = filesize($absolutePath);
        
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline; filename="' . htmlspecialchars($fileName) . '"');
        header('Cache-Control: private, max-age=31536000'); // Cache por 1 ano
        header('Accept-Ranges: bytes');
        
        // Verifica se é um Range Request
        if (isset($_SERVER['HTTP_RANGE'])) {
            // Parse do header Range (ex: "bytes=0-1023")
            preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
            $start = $matches[1] !== '' ? intval($matches[1]) : 0;
            $end = $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
            
            // Validação
            if ($start > $end || $start >= $fileSize) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header('Content-Range: bytes */' . $fileSize);
                exit;
            }
            
            $length = $end - $start + 1;
            
            header('HTTP/1.1 206 Partial Content');
            header('Content-Length: ' . $length);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
            
            // Envia apenas a parte solicitada
            $fp = fopen($absolutePath, 'rb');
            fseek($fp, $start);
            echo fread($fp, $length);
            fclose($fp);
        } else {
            // Request normal - envia arquivo completo
            header('Content-Length: ' . $fileSize);
            readfile($absolutePath);
        }
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
        // CORREÇÃO: Normalização mais robusta que remove TODOS os espaços e converte para lowercase
        // Isso permite comparar "Pixel 12 Digital" com "Pixel12 Digital" e "pixel12digital"
        $sessionIdTrimmed = trim($sessionId);
        $sessionIdNormalized = strtolower(preg_replace('/\s+/', '', $sessionIdTrimmed));
        
        // CORREÇÃO: Também normaliza removendo espaços do valor recebido para comparação
        // Isso garante que "Pixel 12 Digital" seja comparado como "pixel12digital"
        if ($sessionIdColumn === 'session_id') {
            // Se tem coluna session_id, compara direto nela
            $where[] = "(session_id = ? OR LOWER(TRIM(session_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(session_id, ' ', '')) = ? OR LOWER(REPLACE(session_id, ' ', '')) = LOWER(REPLACE(?, ' ', '')))";
            $params[] = $sessionId;
            $params[] = $sessionId;
            $params[] = $sessionIdNormalized;
            $params[] = $sessionId; // Para comparação com REPLACE em ambos os lados
        } else {
            // Fallback: usa channel_id
            // CORREÇÃO: Compara normalizando ambos os lados (remove espaços e converte para lowercase)
            $where[] = "(channel_id = ? OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(channel_id, ' ', '')) = ? OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', '')))";
            $params[] = $sessionId;
            $params[] = $sessionId;
            $params[] = $sessionIdNormalized;
            $params[] = $sessionId; // Para comparação com REPLACE em ambos os lados
        }
        
        // CORREÇÃO: NÃO filtrar por tenant_id ao resolver canal
        // O channel_id é identificador único suficiente
        // Filtrar por tenant_id impede envio para leads (que não têm tenant_id)
        // mas o canal existe e está habilitado
        // Tenant é apenas contexto CRM, não requisito técnico para envio
        
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
            
            // Log para diagnóstico
            error_log("[CommunicationHub::validateGatewaySessionId] ✅ Canal encontrado: sessionId='{$sessionId}', tenantId=" . ($tenantId ?: 'NULL') . ", channel.id={$channel['id']}, channel.tenant_id=" . ($channel['tenant_id'] ?: 'NULL') . ", channel.channel_id={$channel['channel_id']}, canonicalSessionId={$canonicalSessionId}");
            
            return [
                'id' => $channel['id'],
                'channel_id' => $channel['channel_id'] ?? null,
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
            // CORREÇÃO: Usa mesma normalização robusta
            if ($sessionIdColumn === 'session_id') {
                $whereFallback[] = "(session_id = ? OR LOWER(TRIM(session_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(session_id, ' ', '')) = ? OR LOWER(REPLACE(session_id, ' ', '')) = LOWER(REPLACE(?, ' ', '')))";
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionIdNormalized;
                $paramsFallback[] = $sessionId; // Para comparação com REPLACE em ambos os lados
            } else {
                $whereFallback[] = "(channel_id = ? OR LOWER(TRIM(channel_id)) = LOWER(TRIM(?)) OR LOWER(REPLACE(channel_id, ' ', '')) = ? OR LOWER(REPLACE(channel_id, ' ', '')) = LOWER(REPLACE(?, ' ', '')))";
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionId;
                $paramsFallback[] = $sessionIdNormalized;
                $paramsFallback[] = $sessionId; // Para comparação com REPLACE em ambos os lados
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
                
                error_log("[CommunicationHub::validateGatewaySessionId] ✅ Canal encontrado via fallback (sem filtro de tenant): sessionId='{$sessionId}', tenantId=" . ($tenantId ?: 'NULL') . ", channel.id={$channelFallback['id']}, channel.tenant_id=" . ($channelFallback['tenant_id'] ?: 'NULL') . ", channel.channel_id={$channelFallback['channel_id']}, canonicalSessionId={$canonicalSessionId}");
                
                return [
                    'id' => $channelFallback['id'],
                    'channel_id' => $channelFallback['channel_id'] ?? null,
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
    
    // =========================================================================
    // TRANSCRIÇÃO DE ÁUDIO
    // =========================================================================
    
    /**
     * Transcreve um áudio sob demanda
     * 
     * POST /communication-hub/transcribe
     * Body: event_id (string) - ID do evento que contém o áudio
     * 
     * @return void JSON response
     */
    public function transcribe(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');
        
        // Aceita tanto form data quanto JSON
        $eventId = $_POST['event_id'] ?? null;
        if (!$eventId) {
            $input = json_decode(file_get_contents('php://input'), true);
            $eventId = $input['event_id'] ?? null;
        }
        
        if (empty($eventId)) {
            $this->json(['success' => false, 'error' => 'event_id é obrigatório'], 400);
            return;
        }
        
        try {
            $result = \PixelHub\Services\AudioTranscriptionService::transcribeByEventId($eventId);
            
            if ($result['success']) {
                $this->json([
                    'success' => true,
                    'status' => $result['status'] ?? 'completed',
                    'transcription' => $result['transcription'] ?? null,
                    'cached' => $result['cached'] ?? false,
                    'message' => $result['message'] ?? null
                ]);
            } else {
                $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Erro desconhecido'
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro na transcrição: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Retorna status da transcrição de um áudio
     * 
     * GET /communication-hub/transcription-status?event_id=xxx
     * 
     * @return void JSON response
     */
    public function getTranscriptionStatus(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');
        
        $eventId = $_GET['event_id'] ?? null;
        
        if (empty($eventId)) {
            $this->json(['success' => false, 'error' => 'event_id é obrigatório'], 400);
            return;
        }
        
        try {
            $result = \PixelHub\Services\AudioTranscriptionService::getStatus($eventId);
            $this->json($result);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro ao obter status da transcrição: " . $e->getMessage());
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Envia mensagem via Meta API usando template aprovado
     * 
     * @param int $templateId ID do template aprovado
     * @param string $to Telefone do destinatário (formato E.164)
     * @param int $tenantId ID do tenant (cliente)
     * @param string $requestId ID da requisição para logs
     * @return array Resultado do envio
     */
    private function sendViaMetaAPI(int $templateId, string $to, ?int $tenantId, string $requestId): array
    {
        error_log("[CommunicationHub::sendViaMetaAPI] ===== INÍCIO =====");
        error_log("[CommunicationHub::sendViaMetaAPI] Parâmetros recebidos:");
        error_log("[CommunicationHub::sendViaMetaAPI]   template_id={$templateId}");
        error_log("[CommunicationHub::sendViaMetaAPI]   to={$to}");
        error_log("[CommunicationHub::sendViaMetaAPI]   tenant_id={$tenantId}");
        error_log("[CommunicationHub::sendViaMetaAPI]   request_id={$requestId}");
        
        try {
            $db = DB::getConnection();
            error_log("[CommunicationHub::sendViaMetaAPI] ✅ Conexão DB obtida");
        } catch (\Exception $e) {
            error_log("[CommunicationHub::sendViaMetaAPI] ❌ ERRO ao obter conexão DB: " . $e->getMessage());
            throw $e;
        }
        
        // 1. Busca template aprovado
        try {
            error_log("[CommunicationHub::sendViaMetaAPI] Buscando template ID={$templateId}...");
            $template = \PixelHub\Services\MetaTemplateService::getById($templateId);
            error_log("[CommunicationHub::sendViaMetaAPI] ✅ Template retornado: " . ($template ? 'SIM' : 'NULL'));
        } catch (\Exception $e) {
            error_log("[CommunicationHub::sendViaMetaAPI] ❌ EXCEÇÃO ao buscar template: " . $e->getMessage());
            error_log("[CommunicationHub::sendViaMetaAPI] Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
        
        if (!$template || $template['status'] !== 'approved') {
            error_log("[CommunicationHub::sendViaMetaAPI] Template não encontrado ou não aprovado");
            return [
                'success' => false,
                'error' => 'Template não encontrado ou não está aprovado',
                'request_id' => $requestId
            ];
        }
        
        error_log("[CommunicationHub::sendViaMetaAPI] ✅ Template encontrado: {$template['template_name']} (status: {$template['status']})");
        
        // 2. Busca configuração Meta API (global)
        $stmt = $db->prepare("
            SELECT meta_business_account_id, meta_access_token, meta_phone_number_id
            FROM whatsapp_provider_configs 
            WHERE provider_type = 'meta_official' 
            AND is_active = 1
            AND is_global = 1
            LIMIT 1
        ");
        $stmt->execute();
        $config = $stmt->fetch();
        
        if (!$config) {
            error_log("[CommunicationHub::sendViaMetaAPI] Configuração Meta não encontrada");
            return [
                'success' => false,
                'error' => 'Configuração Meta API não encontrada',
                'request_id' => $requestId
            ];
        }
        
        // 3. Descriptografa token
        $accessToken = $config['meta_access_token'];
        if (strpos($accessToken, 'encrypted:') === 0) {
            $accessToken = \PixelHub\Core\CryptoHelper::decrypt(substr($accessToken, 10));
        }
        
        $phoneNumberId = $config['meta_phone_number_id'];
        
        // 4. Normaliza telefone para formato E.164
        $normalizedPhone = \PixelHub\Services\PhoneNormalizer::toE164OrNull($to, 'BR', false);
        
        if (!$normalizedPhone) {
            error_log("[CommunicationHub::sendViaMetaAPI] Erro ao normalizar telefone: {$to}");
            return [
                'success' => false,
                'error' => 'Número de telefone inválido',
                'request_id' => $requestId
            ];
        }
        
        error_log("[CommunicationHub::sendViaMetaAPI] Telefone normalizado: {$to} → {$normalizedPhone}");
        
        // 4.5. Busca dados do tenant para variáveis do template
        $stmt = $db->prepare("SELECT name, phone, email FROM tenants WHERE id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        $clientName = $tenant ? $tenant['name'] : 'Cliente';
        
        // 5. Processa variáveis do template
        $templateVariables = [];
        if (!empty($template['variables'])) {
            $variables = json_decode($template['variables'], true);
            if (is_array($variables)) {
                // Recebe variáveis do POST (se enviadas)
                $customVars = isset($_POST['template_vars']) ? json_decode($_POST['template_vars'], true) : [];
                
                foreach ($variables as $index => $var) {
                    $varName = $var['name'] ?? "var" . ($index + 1);
                    
                    // Prioridade: 1) Valor customizado do POST, 2) Auto-preenchimento, 3) Exemplo
                    if (isset($customVars[$varName])) {
                        $value = $customVars[$varName];
                    } elseif ($varName === 'nome' || $varName === 'name' || $varName === 'cliente') {
                        $value = $clientName;
                    } else {
                        $value = $var['example'] ?? '';
                    }
                    
                    $templateVariables[] = ['type' => 'text', 'text' => $value];
                }
                
                error_log("[CommunicationHub::sendViaMetaAPI] Variáveis processadas: " . json_encode($templateVariables));
            }
        }
        
        // 6. Monta payload para Meta API
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $normalizedPhone,
            'type' => 'template',
            'template' => [
                'name' => $template['template_name'],
                'language' => [
                    'code' => $template['language']
                ]
            ]
        ];
        
        // Adiciona componentes se houver variáveis
        if (!empty($templateVariables)) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $templateVariables
                ]
            ];
        }
        
        error_log("[CommunicationHub::sendViaMetaAPI] Payload montado: " . json_encode($payload));
        
        // 6. Envia para Meta API
        $url = "https://graph.facebook.com/v18.0/{$phoneNumberId}/messages";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        error_log("[CommunicationHub::sendViaMetaAPI] Resposta Meta API (HTTP {$httpCode}): " . $response);
        
        if ($httpCode !== 200 || !isset($result['messages'][0]['id'])) {
            $errorMsg = $result['error']['message'] ?? 'Erro desconhecido';
            error_log("[CommunicationHub::sendViaMetaAPI] Erro ao enviar: {$errorMsg}");
            
            return [
                'success' => false,
                'error' => "Erro ao enviar via Meta API: {$errorMsg}",
                'request_id' => $requestId
            ];
        }
        
        $messageId = $result['messages'][0]['id'];
        
        error_log("[CommunicationHub::sendViaMetaAPI] Mensagem enviada com sucesso! message_id={$messageId}");
        
        // 7. Cria ou atualiza conversa no banco
        $conversationKey = 'whatsapp_shared_' . $normalizedPhone;
        $contactName = trim($_POST['contact_name'] ?? '');
        $conversationSource = trim($_POST['conversation_source'] ?? '');
        $leadId = isset($_POST['lead_id']) && $_POST['lead_id'] !== '' ? (int) $_POST['lead_id'] : null;

        // Busca conversa existente por contact_external_id + provider_type
        $stmt = $db->prepare("
            SELECT id, contact_name, channel_id FROM conversations
            WHERE contact_external_id = ?
              AND channel_type = 'whatsapp'
              AND provider_type = 'meta_official'
            ORDER BY last_message_at DESC
            LIMIT 1
        ");
        $tenantIdForDb = ($tenantId && $tenantId > 0) ? $tenantId : null;
        $stmt->execute([$normalizedPhone]);
        $conversation = $stmt->fetch();

        if ($conversation) {
            $conversationId = $conversation['id'];
            error_log("[CommunicationHub::sendViaMetaAPI] Conversa existente encontrada: {$conversationId}");
            $updateContactName = (!empty($contactName) && empty($conversation['contact_name'])) ? $contactName : null;
            $updateChannelId   = empty($conversation['channel_id']) ? $phoneNumberId : null;
            $stmt = $db->prepare("
                UPDATE conversations
                SET updated_at = NOW()
                    " . ($updateContactName ? ", contact_name = ?" : "") . "
                    " . ($updateChannelId   ? ", channel_id = ?"   : "") . "
                WHERE id = ?
            ");
            $params = [];
            if ($updateContactName) $params[] = $updateContactName;
            if ($updateChannelId)   $params[] = $updateChannelId;
            $params[] = $conversationId;
            $stmt->execute($params);
        } else {
            $stmt = $db->prepare("
                INSERT INTO conversations (
                    conversation_key, tenant_id, lead_id,
                    contact_name, contact_external_id,
                    channel_type, channel_id, provider_type,
                    source, status, is_incoming_lead,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, 'whatsapp', ?, 'meta_official', ?, 'active', ?, NOW(), NOW())
            ");
            $isIncomingLead = $leadId ? 1 : 0;
            $stmt->execute([
                $conversationKey, $tenantIdForDb, $leadId,
                $contactName ?: null, $normalizedPhone,
                $phoneNumberId,
                $conversationSource ?: null,
                $isIncomingLead
            ]);
            $conversationId = (int) $db->lastInsertId();
            error_log("[CommunicationHub::sendViaMetaAPI] Nova conversa criada: {$conversationId}" . ($leadId ? " (lead_id={$leadId})" : "") . ($conversationSource ? " (source={$conversationSource})" : ""));
        }

        // Monta body legível para exibição no Inbox
        $templateBodyText = $template['content'] ?? $template['body_text'] ?? $template['body'] ?? '';
        if (!empty($templateBodyText) && !empty($templateVariables)) {
            foreach ($templateVariables as $i => $var) {
                $templateBodyText = str_replace('{{' . ($i + 1) . '}}', $var['text'] ?? '', $templateBodyText);
            }
        }
        $messageBody = $templateBodyText ?: '[Template: ' . $template['template_name'] . ']';

        // 8. Registra evento de envio via EventIngestionService
        try {
            $eventData = [
                'event_type' => 'whatsapp.outbound.message',
                'source_system' => 'meta_official',
                'payload' => [
                    'message_id' => $messageId,
                    'to' => $normalizedPhone,
                    'body' => $messageBody,
                    'type' => 'template',
                    'from_me' => true,
                    'template' => $template['template_name'],
                    'status' => 'sent',
                    'timestamp' => time()
                ],
                'trace_id' => $requestId
            ];
            
            EventIngestionService::ingest($eventData);
            
            error_log("[CommunicationHub::sendViaMetaAPI] Evento registrado via EventIngestionService");
        } catch (\Exception $e) {
            error_log("[CommunicationHub::sendViaMetaAPI] Erro ao registrar evento: " . $e->getMessage());
            // Não falha o envio se o registro falhar
        }
        
        return [
            'success' => true,
            'message' => 'Mensagem enviada com sucesso!',
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'request_id' => $requestId
        ];
    }

    /**
     * Se o conteúdo for um placeholder "[Template: nome]", resolve para o texto real do template.
     */
    private function resolveTemplateBody(PDO $db, string $content): string
    {
        if (!preg_match('/^\[Template: (.+)\]$/', $content, $m)) {
            return $content;
        }
        try {
            $stmt = $db->prepare(
                "SELECT content FROM whatsapp_message_templates WHERE template_name = ? AND status = 'approved' LIMIT 1"
            );
            $stmt->execute([$m[1]]);
            $row = $stmt->fetch();
            if ($row && !empty($row['content'])) {
                return $row['content'];
            }
        } catch (\Throwable $e) {
            // silencioso: fallback para placeholder original
        }
        return $content;
    }
}

