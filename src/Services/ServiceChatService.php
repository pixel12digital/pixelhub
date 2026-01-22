<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Service para gerenciar threads e mensagens do chat vinculadas a pedidos
 * 
 * Regra importante: O chat sempre nasce com order_id - nunca existe solto.
 */
class ServiceChatService
{
    /**
     * Cria um novo thread de chat vinculado a um pedido
     * 
     * @param int $orderId ID do pedido (OBRIGATÓRIO)
     * @param int|null $customerId ID do cliente (opcional, pode ser NULL inicialmente)
     * @return int ID do thread criado
     */
    public static function createThread(int $orderId, ?int $customerId = null): int
    {
        $db = DB::getConnection();
        
        // Verifica se o pedido existe
        $order = ServiceOrderService::findOrder($orderId);
        if (!$order) {
            throw new \InvalidArgumentException('Pedido não encontrado');
        }
        
        // Verifica se já existe thread para este pedido
        $existing = self::findThreadByOrder($orderId);
        if ($existing) {
            return (int) $existing['id'];
        }
        
        // Obtém customer_id do pedido se não informado
        if (empty($customerId) && !empty($order['tenant_id'])) {
            $customerId = (int) $order['tenant_id'];
        }
        
        // Obtém service_slug do pedido
        $serviceSlug = $order['service_slug'] ?? null;
        if (empty($serviceSlug) && !empty($order['service_id'])) {
            // Busca service_slug do serviço se não estiver no pedido
            $service = ServiceService::findService((int) $order['service_id']);
            if ($service && !empty($service['slug'])) {
                $serviceSlug = $service['slug'];
            }
        }
        
        // Define step inicial baseado no service_slug
        $currentStep = 'step_0_welcome';
        if ($serviceSlug === 'business_card_express') {
            $currentStep = 'step_0_welcome';
        }
        
        // Cria thread
        $stmt = $db->prepare("
            INSERT INTO chat_threads 
            (customer_id, order_id, status, current_step, metadata, created_at, updated_at)
            VALUES (?, ?, 'open', ?, JSON_OBJECT('service_slug', ?), NOW(), NOW())
        ");
        
        $stmt->execute([$customerId, $orderId, $currentStep, $serviceSlug]);
        
        $threadId = (int) $db->lastInsertId();
        
        // Log
        error_log(sprintf(
            '[ServiceChat] thread_created: thread_id=%d, order_id=%d, customer_id=%s',
            $threadId,
            $orderId,
            $customerId ?: 'NULL'
        ));
        
        return $threadId;
    }
    
    /**
     * Busca thread por ID
     * 
     * @param int $threadId ID do thread
     * @return array|null Thread ou null se não encontrado
     */
    public static function findThread(int $threadId): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT ct.*, 
                   o.service_slug,
                   o.status as order_status,
                   o.tenant_id as order_tenant_id,
                   t.name as customer_name,
                   t.email as customer_email
            FROM chat_threads ct
            INNER JOIN service_orders o ON ct.order_id = o.id
            LEFT JOIN tenants t ON ct.customer_id = t.id
            WHERE ct.id = ?
        ");
        $stmt->execute([$threadId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Busca thread por order_id
     * 
     * @param int $orderId ID do pedido
     * @return array|null Thread ou null se não encontrado
     */
    public static function findThreadByOrder(int $orderId): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM chat_threads 
            WHERE order_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Adiciona mensagem ao thread
     * 
     * @param int $threadId ID do thread
     * @param string $role system | assistant | user | tool
     * @param string $content Conteúdo da mensagem
     * @param array|null $metadata Metadados adicionais
     * @return int ID da mensagem criada
     */
    public static function addMessage(int $threadId, string $role, string $content, ?array $metadata = null): int
    {
        $db = DB::getConnection();
        
        // Valida role
        $allowedRoles = ['system', 'assistant', 'user', 'tool'];
        if (!in_array($role, $allowedRoles)) {
            throw new \InvalidArgumentException('Role inválida: ' . $role);
        }
        
        // Valida thread existe
        $thread = self::findThread($threadId);
        if (!$thread) {
            throw new \InvalidArgumentException('Thread não encontrado');
        }
        
        // Insere mensagem
        $metadataJson = $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $db->prepare("
            INSERT INTO chat_messages 
            (thread_id, role, content, metadata, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$threadId, $role, $content, $metadataJson]);
        
        $messageId = (int) $db->lastInsertId();
        
        // Atualiza updated_at do thread
        $db->prepare("UPDATE chat_threads SET updated_at = NOW() WHERE id = ?")
           ->execute([$threadId]);
        
        return $messageId;
    }
    
    /**
     * Busca mensagens do thread
     * 
     * @param int $threadId ID do thread
     * @param int|null $limit Limite de mensagens (null = todas)
     * @return array Lista de mensagens ordenadas por created_at ASC
     */
    public static function getMessages(int $threadId, ?int $limit = null): array
    {
        $db = DB::getConnection();
        
        $sql = "
            SELECT * FROM chat_messages 
            WHERE thread_id = ?
            ORDER BY created_at ASC
        ";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$threadId]);
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Atualiza status do thread
     * 
     * @param int $threadId ID do thread
     * @param string $status open | waiting_user | waiting_ai | escalated | closed
     * @return bool Sucesso
     */
    public static function updateStatus(int $threadId, string $status): bool
    {
        $db = DB::getConnection();
        
        $allowedStatuses = ['open', 'waiting_user', 'waiting_ai', 'escalated', 'closed'];
        if (!in_array($status, $allowedStatuses)) {
            throw new \InvalidArgumentException('Status inválido: ' . $status);
        }
        
        $stmt = $db->prepare("
            UPDATE chat_threads 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        return $stmt->execute([$status, $threadId]);
    }
    
    /**
     * Atualiza step atual do thread
     * 
     * @param int $threadId ID do thread
     * @param string $step Step atual (ex: step_1_identity)
     * @return bool Sucesso
     */
    public static function updateStep(int $threadId, string $step): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE chat_threads 
            SET current_step = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        return $stmt->execute([$step, $threadId]);
    }
    
    /**
     * Atualiza metadata do thread
     * 
     * @param int $threadId ID do thread
     * @param array $metadata Metadados (será mergeado com existente)
     * @return bool Sucesso
     */
    public static function updateMetadata(int $threadId, array $metadata): bool
    {
        $db = DB::getConnection();
        
        // Busca metadata atual
        $thread = self::findThread($threadId);
        if (!$thread) {
            throw new \InvalidArgumentException('Thread não encontrado');
        }
        
        $currentMetadata = [];
        if (!empty($thread['metadata'])) {
            $currentMetadata = json_decode($thread['metadata'], true) ?: [];
        }
        
        // Faz merge
        $mergedMetadata = array_merge($currentMetadata, $metadata);
        
        $stmt = $db->prepare("
            UPDATE chat_threads 
            SET metadata = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        return $stmt->execute([json_encode($mergedMetadata, JSON_UNESCAPED_UNICODE), $threadId]);
    }
    
    /**
     * Verifica se existe thread para um pedido (e cria se não existir)
     * 
     * Garante que o chat sempre existe quando necessário.
     * 
     * @param int $orderId ID do pedido
     * @return int ID do thread (criado ou existente)
     */
    public static function ensureThreadForOrder(int $orderId): int
    {
        $thread = self::findThreadByOrder($orderId);
        if ($thread) {
            return (int) $thread['id'];
        }
        
        return self::createThread($orderId);
    }
}

