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
     * Envia mensagem
     * 
     * POST /communication-hub/send
     */
    public function send(): void
    {
        Auth::requireInternal();
        header('Content-Type: application/json');

        $channel = $_POST['channel'] ?? null;
        $threadId = $_POST['thread_id'] ?? null;
        $to = $_POST['to'] ?? null; // phone, email, etc
        $message = trim($_POST['message'] ?? '');
        $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;

        if (empty($channel) || empty($message)) {
            $this->json(['success' => false, 'error' => 'Canal e mensagem são obrigatórios'], 400);
            return;
        }

        try {
            if ($channel === 'whatsapp') {
                if (empty($tenantId) || empty($to)) {
                    $this->json(['success' => false, 'error' => 'tenant_id e to são obrigatórios para WhatsApp'], 400);
                    return;
                }

                // Busca channel do tenant
                $db = DB::getConnection();
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

                // Normaliza telefone
                $phoneNormalized = WhatsAppBillingService::normalizePhone($to);
                if (empty($phoneNormalized)) {
                    $this->json(['success' => false, 'error' => 'Telefone inválido'], 400);
                    return;
                }

                // Envia via gateway
                $gateway = new WhatsAppGatewayClient();
                $result = $gateway->sendText($channelData['channel_id'], $phoneNormalized, $message, [
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
                            'channel_id' => $channelData['channel_id']
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
     * Busca threads de WhatsApp (via eventos)
     */
    private function getWhatsAppThreads(PDO $db, ?int $tenantId, string $status): array
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

        if ($tenantId) {
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
            $from = $payload['from'] ?? $payload['to'] ?? null;
            
            if (!$from) continue;
            
            $tenantId = $event['tenant_id'] ?? 0;
            $threadKey = "{$tenantId}_{$from}";
            
            if (!isset($threadsMap[$threadKey])) {
                $threadsMap[$threadKey] = [
                    'thread_id' => "whatsapp_{$tenantId}_{$from}",
                    'tenant_id' => $tenantId,
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
     */
    private function getWhatsAppMessages(PDO $db, string $threadId): array
    {
        // Extrai tenant_id e from do thread_id (formato: whatsapp_{tenant_id}_{from})
        if (preg_match('/whatsapp_(\d+)_(.+)/', $threadId, $matches)) {
            $tenantId = (int) $matches[1];
            $from = $matches[2];

            // Busca todos os eventos e filtra em PHP (mais compatível)
            $stmt = $db->prepare("
                SELECT 
                    ce.event_id,
                    ce.event_type,
                    ce.created_at,
                    ce.payload,
                    ce.metadata
                FROM communication_events ce
                WHERE ce.tenant_id = ?
                AND ce.event_type IN ('whatsapp.inbound.message', 'whatsapp.outbound.message')
                ORDER BY ce.created_at ASC
            ");
            $stmt->execute([$tenantId]);
            $events = $stmt->fetchAll();

            // Filtra e formata mensagens
            $messages = [];
            foreach ($events as $event) {
                $payload = json_decode($event['payload'], true);
                $eventFrom = $payload['from'] ?? null;
                $eventTo = $payload['to'] ?? null;
                
                // Verifica se é desta conversa
                if ($eventFrom !== $from && $eventTo !== $from) {
                    continue;
                }
                
                $direction = $event['event_type'] === 'whatsapp.inbound.message' ? 'inbound' : 'outbound';
                
                $messages[] = [
                    'id' => $event['event_id'],
                    'direction' => $direction,
                    'content' => $payload['body'] ?? $payload['text'] ?? '',
                    'timestamp' => $event['created_at'],
                    'metadata' => json_decode($event['metadata'] ?? '{}', true)
                ];
            }

            return $messages;
        }

        return [];
    }

    /**
     * Busca informações do thread WhatsApp
     */
    private function getWhatsAppThreadInfo(PDO $db, string $threadId): ?array
    {
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
}

