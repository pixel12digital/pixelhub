<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\EventIngestionService;
use PixelHub\Services\EventRouterService;
use PixelHub\Services\EventNormalizationService;
use PixelHub\Services\WhatsAppBillingService;
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
            'whatsapp_active' => count(array_filter($allThreads, function($t) {
                return ($t['channel'] ?? '') === 'whatsapp' && ($t['status'] ?? '') === 'active';
            })),
            'chat_active' => count(array_filter($allThreads, function($t) {
                return ($t['channel'] ?? '') === 'chat' && ($t['status'] ?? '') === 'active';
            })),
            'total_unread' => count(array_filter($allThreads, function($t) {
                return ($t['unread_count'] ?? 0) > 0;
            }))
        ];

        $this->view('communication_hub.index', [
            'threads' => $allThreads ?? [],
            'tenants' => $tenants ?? [],
            'stats' => $stats ?? ['whatsapp_active' => 0, 'chat_active' => 0, 'total_unread' => 0],
            'filters' => [
                'channel' => $channel,
                'tenant_id' => $tenantId,
                'status' => $status
            ]
        ]);
        } catch (\Exception $e) {
            error_log("[CommunicationHub] Erro fatal no index: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            http_response_code(500);
            echo "<h1>Erro interno do servidor</h1>";
            echo "<p>Ocorreu um erro ao carregar o painel de comunicação.</p>";
            if (defined('APP_DEBUG') && APP_DEBUG) {
                echo "<pre>" . htmlspecialchars($e->getMessage()) . "\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
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
        $channelId = isset($_POST['channel_id']) && $_POST['channel_id'] !== '' ? (int) $_POST['channel_id'] : null;

        if (empty($channel) || empty($message)) {
            $this->json(['success' => false, 'error' => 'Canal e mensagem são obrigatórios'], 400);
            return;
        }

        try {
            if ($channel === 'whatsapp') {
                if (empty($to)) {
                    $this->json(['success' => false, 'error' => 'to (telefone) é obrigatório para WhatsApp'], 400);
                    return;
                }
                
                $db = DB::getConnection();
                
                // PRIORIDADE 1: Usa channel_id fornecido diretamente (vem da thread)
                if ($channelId) {
                    // Valida que o canal existe e está habilitado
                    $channelStmt = $db->prepare("
                        SELECT channel_id 
                        FROM tenant_message_channels 
                        WHERE channel_id = ? 
                        AND provider = 'wpp_gateway' 
                        AND is_enabled = 1
                        LIMIT 1
                    ");
                    $channelStmt->execute([$channelId]);
                    $channelData = $channelStmt->fetch();
                    
                    if (!$channelData) {
                        $this->json(['success' => false, 'error' => 'Canal WhatsApp fornecido não está disponível ou habilitado'], 400);
                        return;
                    }
                    // channelId já está definido, continua
                } else {
                    // PRIORIDADE 2: Se não tem channel_id, tenta inferir da thread/conversa
                    if (!$tenantId && !empty($threadId) && preg_match('/^whatsapp_(\d+)$/', $threadId, $matches)) {
                        $conversationId = (int) $matches[1];
                        $convStmt = $db->prepare("SELECT tenant_id FROM conversations WHERE id = ?");
                        $convStmt->execute([$conversationId]);
                        $conv = $convStmt->fetch();
                        if ($conv && $conv['tenant_id']) {
                            $tenantId = (int) $conv['tenant_id'];
                        }
                    }

                    // PRIORIDADE 3: Busca channel do tenant
                    if ($tenantId) {
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

                        if (!$channelData) {
                            $this->json(['success' => false, 'error' => 'Channel WhatsApp não configurado para este tenant'], 400);
                            return;
                        }
                        $channelId = (int) $channelData['channel_id'];
                    } else {
                        // PRIORIDADE 4: Fallback: tenta usar canal compartilhado/default (qualquer canal habilitado)
                        $channelStmt = $db->prepare("
                            SELECT channel_id 
                            FROM tenant_message_channels 
                            WHERE provider = 'wpp_gateway' 
                            AND is_enabled = 1
                            LIMIT 1
                        ");
                        $channelStmt->execute();
                        $channelData = $channelStmt->fetch();

                        if (!$channelData) {
                            $this->json(['success' => false, 'error' => 'Nenhum canal WhatsApp configurado no sistema'], 400);
                            return;
                        }
                        $channelId = (int) $channelData['channel_id'];
                    }
                }

                // Normaliza telefone
                $phoneNormalized = WhatsAppBillingService::normalizePhone($to);
                if (empty($phoneNormalized)) {
                    $this->json(['success' => false, 'error' => 'Telefone inválido'], 400);
                    return;
                }

                // Envia via gateway
                $gateway = new WhatsAppGatewayClient();
                $result = $gateway->sendText($channelId, $phoneNormalized, $message, [
                    'sent_by' => Auth::user()['id'] ?? null,
                    'sent_by_name' => Auth::user()['name'] ?? null
                ]);

                if ($result['success']) {
                    // Cria evento de envio
                    $eventId = EventIngestionService::ingest([
                        'event_type' => 'whatsapp.outbound.message',
                        'source_system' => 'pixelhub_operator',
                        'payload' => [
                            'to' => $phoneNormalized,
                            'text' => $message,
                            'channel_id' => $channelId
                        ],
                        'tenant_id' => $tenantId,
                        'metadata' => [
                            'sent_by' => Auth::user()['id'] ?? null,
                            'sent_by_name' => Auth::user()['name'] ?? null,
                            'message_id' => $result['message_id'] ?? null
                        ]
                    ]);

                    $this->json([
                        'success' => true,
                        'event_id' => $eventId,
                        'message_id' => $result['message_id'] ?? null
                    ]);
                } else {
                    $this->json(['success' => false, 'error' => $result['error'] ?? 'Erro ao enviar mensagem'], 500);
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
                ORDER BY c.last_message_at DESC, c.created_at DESC
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
                        'assigned_to_name' => $conv['assigned_to_name']
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
     */
    private function getWhatsAppMessagesFromConversation(PDO $db, int $conversationId): array
    {
        // Busca a conversa para pegar o contact_external_id
        $convStmt = $db->prepare("
            SELECT conversation_key, contact_external_id, tenant_id, channel_type
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

        // Normaliza contact_external_id (remove sufixos @c.us, @lid, etc)
        // CORRIGIDO: Regex agora remove tudo após @ (incluindo @c.us, @lid, etc)
        $normalizeContact = function($contact) {
            if (empty($contact)) return null;
            // Remove tudo após @ (ex: 554796164699@c.us -> 554796164699)
            return preg_replace('/@.*$/', '', (string) $contact);
        };
        $normalizedContactExternalId = $normalizeContact($contactExternalId);

        // Busca TODOS os eventos WhatsApp (tenant_id pode ser NULL)
        // Filtra em PHP para garantir que pega todas as variações do contato
        $stmt = $db->prepare("
            SELECT 
                ce.event_id,
                ce.event_type,
                ce.created_at,
                ce.payload,
                ce.metadata,
                ce.tenant_id
            FROM communication_events ce
            WHERE ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
            ORDER BY ce.created_at ASC
        ");
        $stmt->execute();
        $allEvents = $stmt->fetchAll();

        // Filtra eventos desta conversa pelo contact_external_id (normalizado)
        $messages = [];
        foreach ($allEvents as $event) {
            $payload = json_decode($event['payload'], true);
            $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
            $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
            
            // Normaliza para comparar
            $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
            $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
            
            // Verifica se é desta conversa (inbound ou outbound)
            // CORRIGIDO: Verificação mais robusta (compara strings normalizadas)
            $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContactExternalId;
            $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContactExternalId;
            
            if (!$isFromThisContact && !$isToThisContact) {
                continue;
            }
            
            // Verifica se tenant_id bate (se ambos tiverem tenant_id definido)
            if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
                continue;
            }
            
            // Se conversation tem tenant_id mas evento não tem, aceita (fallback)
            // Se evento tem tenant_id mas conversation não, aceita (atualização)
            
            $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
            
            // Extrai conteúdo da mensagem (suporta diferentes formatos de payload)
            $content = $payload['text'] 
                ?? $payload['body'] 
                ?? $payload['message']['text'] 
                ?? $payload['message']['body'] 
                ?? '';
            
            // Se for mídia, mostra tipo
            if (empty($content)) {
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
                'metadata' => json_decode($event['metadata'] ?? '{}', true)
            ];
        }

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
            
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $payload['body'] ?? $payload['text'] ?? $payload['message']['text'] ?? '',
                'timestamp' => $event['created_at'],
                'metadata' => json_decode($event['metadata'] ?? '{}', true)
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
                    tmc.channel_id,
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
                return [
                    'thread_id' => $threadId,
                    'conversation_id' => $conversationId,
                    'conversation_key' => $conversation['conversation_key'],
                    'tenant_id' => $conversation['tenant_id'],
                    'tenant_name' => $conversation['tenant_name'],
                    'contact' => $conversation['contact_external_id'],
                    'contact_name' => $conversation['contact_name'],
                    'channel' => 'whatsapp',
                    'channel_id' => $conversation['channel_id'],
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
            // Resolve thread para pegar dados da conversa
            $conversationData = $this->resolveThreadToConversation($db, $threadId);
            if (!$conversationData) {
                $this->json(['success' => false, 'error' => 'Thread não encontrado'], 404);
                return;
            }

            $contactExternalId = $conversationData['contact_external_id'];
            $tenantId = $conversationData['tenant_id'];
            
            $normalizeContact = function($contact) {
                if (empty($contact)) return null;
                return preg_replace('/@.*$/', '', (string) $contact);
            };
            $normalizedContact = $normalizeContact($contactExternalId);

            // Query leve: verifica existência sem carregar payload completo
            // Usa mesma lógica de marcador que getWhatsAppMessagesIncremental
            $where = [
                "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
            ];
            $params = [];

            if ($afterTimestamp) {
                $where[] = "(ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))";
                $params[] = $afterTimestamp;
                $params[] = $afterTimestamp;
                $params[] = $afterEventId ?? '';
            }

            $whereClause = "WHERE " . implode(" AND ", $where);

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

            // Filtra rapidamente para verificar se há mensagens desta conversa
            $hasNew = false;

            foreach ($events as $event) {
                $payload = json_decode($event['payload'], true);
                $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
                $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
                
                $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
                $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
                
                $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContact;
                $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContact;
                
                if ($isFromThisContact || $isToThisContact) {
                    $hasNew = true;
                    break; // Encontrou uma, não precisa verificar mais
                }
            }

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

            $message = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => json_decode($event['metadata'] ?? '{}', true)
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
        $normalizeContact = function($contact) {
            if (empty($contact)) return null;
            return preg_replace('/@.*$/', '', (string) $contact);
        };
        $normalizedContactExternalId = $normalizeContact($contactExternalId);

        // Build query incremental (usando índice created_at + tie-breaker event_id)
        $where = [
            "ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')"
        ];
        $params = [];

        if ($afterTimestamp) {
            // Filtro incremental: created_at > timestamp OU (created_at = timestamp E event_id > after_event_id)
            $where[] = "(ce.created_at > ? OR (ce.created_at = ? AND ce.event_id > ?))";
            $params[] = $afterTimestamp;
            $params[] = $afterTimestamp;
            $params[] = $afterEventId ?? '';
        }

        $whereClause = "WHERE " . implode(" AND ", $where);

        // Busca eventos incrementais (limitado para não sobrecarregar)
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
        $allEvents = $stmt->fetchAll();

        // Filtra eventos desta conversa (mesma lógica do método original)
        $messages = [];
        foreach ($allEvents as $event) {
            $payload = json_decode($event['payload'], true);
            $eventFrom = $payload['from'] ?? $payload['message']['from'] ?? null;
            $eventTo = $payload['to'] ?? $payload['message']['to'] ?? null;
            
            $normalizedFrom = $eventFrom ? $normalizeContact($eventFrom) : null;
            $normalizedTo = $eventTo ? $normalizeContact($eventTo) : null;
            
            $isFromThisContact = !empty($normalizedFrom) && $normalizedFrom === $normalizedContactExternalId;
            $isToThisContact = !empty($normalizedTo) && $normalizedTo === $normalizedContactExternalId;
            
            if (!$isFromThisContact && !$isToThisContact) {
                continue;
            }
            
            if ($tenantId && $event['tenant_id'] && $event['tenant_id'] != $tenantId) {
                continue;
            }
            
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
            
            $messages[] = [
                'id' => $event['event_id'],
                'direction' => $direction,
                'content' => $content,
                'timestamp' => $event['created_at'],
                'metadata' => json_decode($event['metadata'] ?? '{}', true)
            ];
        }

        return $messages;
    }
}

