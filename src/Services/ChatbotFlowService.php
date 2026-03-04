<?php

namespace PixelHub\Services;

use PDO;
use PixelHub\Core\DB;

/**
 * Service para gerenciar fluxos de automação do chatbot
 * 
 * Responsável por:
 * - CRUD de fluxos de chatbot
 * - Execução de fluxos baseados em gatilhos
 * - Registro de eventos de chatbot
 * - Gestão de estado de conversas no chatbot
 * 
 * Data: 2026-03-04
 */
class ChatbotFlowService
{
    /**
     * Lista todos os fluxos, opcionalmente filtrados
     * 
     * @param int|null $tenantId Filtrar por tenant
     * @param string|null $triggerType Filtrar por tipo de gatilho
     * @param bool|null $isActive Filtrar por status ativo
     * @return array Lista de fluxos
     */
    public static function listFlows(?int $tenantId = null, ?string $triggerType = null, ?bool $isActive = null): array
    {
        $db = DB::getConnection();
        
        $where = [];
        $params = [];
        
        if ($tenantId !== null) {
            $where[] = "tenant_id = ?";
            $params[] = $tenantId;
        }
        
        if ($triggerType !== null) {
            $where[] = "trigger_type = ?";
            $params[] = $triggerType;
        }
        
        if ($isActive !== null) {
            $where[] = "is_active = ?";
            $params[] = $isActive ? 1 : 0;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $stmt = $db->prepare("
            SELECT 
                f.*,
                t.name as tenant_name,
                rt.template_name as response_template_name,
                u.name as assign_to_user_name
            FROM chatbot_flows f
            LEFT JOIN tenants t ON f.tenant_id = t.id
            LEFT JOIN whatsapp_message_templates rt ON f.response_template_id = rt.id
            LEFT JOIN users u ON f.assign_to_user_id = u.id
            {$whereClause}
            ORDER BY f.priority DESC, f.created_at DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Busca fluxo por ID
     * 
     * @param int $id ID do fluxo
     * @return array|null Fluxo ou null se não encontrado
     */
    public static function getById(int $id): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                f.*,
                t.name as tenant_name,
                rt.template_name as response_template_name
            FROM chatbot_flows f
            LEFT JOIN tenants t ON f.tenant_id = t.id
            LEFT JOIN whatsapp_message_templates rt ON f.response_template_id = rt.id
            WHERE f.id = ?
        ");
        
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Busca fluxo por gatilho
     * 
     * @param string $triggerType Tipo de gatilho
     * @param string $triggerValue Valor do gatilho
     * @param int|null $tenantId Tenant (NULL = global)
     * @return array|null Fluxo ou null se não encontrado
     */
    public static function findByTrigger(string $triggerType, string $triggerValue, ?int $tenantId = null): ?array
    {
        $db = DB::getConnection();
        
        $where = ["trigger_type = ?", "trigger_value = ?", "is_active = 1"];
        $params = [$triggerType, $triggerValue];
        
        if ($tenantId !== null) {
            $where[] = "(tenant_id = ? OR tenant_id IS NULL)";
            $params[] = $tenantId;
        } else {
            $where[] = "tenant_id IS NULL";
        }
        
        $stmt = $db->prepare("
            SELECT * FROM chatbot_flows
            WHERE " . implode(" AND ", $where) . "
            ORDER BY priority DESC, tenant_id DESC
            LIMIT 1
        ");
        
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Cria um novo fluxo
     * 
     * @param array $data Dados do fluxo
     * @return int ID do fluxo criado
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO chatbot_flows (
                tenant_id,
                name,
                trigger_type,
                trigger_value,
                response_type,
                response_message,
                response_template_id,
                response_media_url,
                response_media_type,
                next_buttons,
                forward_to_human,
                assign_to_user_id,
                add_tags,
                update_lead_status,
                priority,
                is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['tenant_id'] ?? null,
            $data['name'],
            $data['trigger_type'],
            $data['trigger_value'],
            $data['response_type'] ?? 'text',
            $data['response_message'] ?? null,
            $data['response_template_id'] ?? null,
            $data['response_media_url'] ?? null,
            $data['response_media_type'] ?? null,
            isset($data['next_buttons']) ? json_encode($data['next_buttons']) : null,
            $data['forward_to_human'] ?? 0,
            $data['assign_to_user_id'] ?? null,
            isset($data['add_tags']) ? json_encode($data['add_tags']) : null,
            $data['update_lead_status'] ?? null,
            $data['priority'] ?? 0,
            $data['is_active'] ?? 1
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Atualiza um fluxo existente
     * 
     * @param int $id ID do fluxo
     * @param array $data Dados para atualizar
     * @return bool Sucesso
     */
    public static function update(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'name', 'trigger_type', 'trigger_value', 'response_type', 'response_message',
            'response_template_id', 'response_media_url', 'response_media_type',
            'next_buttons', 'forward_to_human', 'assign_to_user_id', 'add_tags',
            'update_lead_status', 'priority', 'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                
                // JSON encode para campos JSON
                if (in_array($field, ['next_buttons', 'add_tags']) && is_array($data[$field])) {
                    $params[] = json_encode($data[$field]);
                } else {
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $stmt = $db->prepare("
            UPDATE chatbot_flows 
            SET " . implode(", ", $fields) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($params);
    }
    
    /**
     * Deleta um fluxo
     * 
     * @param int $id ID do fluxo
     * @return bool Sucesso
     */
    public static function delete(int $id): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("DELETE FROM chatbot_flows WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Executa um fluxo de chatbot
     * 
     * @param int $flowId ID do fluxo
     * @param int $conversationId ID da conversa
     * @param array $context Contexto adicional (variáveis, dados do lead, etc)
     * @return array ['success' => bool, 'message' => string, 'response' => array|null]
     */
    public static function executeFlow(int $flowId, int $conversationId, array $context = []): array
    {
        $flow = self::getById($flowId);
        
        if (!$flow) {
            return ['success' => false, 'message' => 'Fluxo não encontrado', 'response' => null];
        }
        
        if (!$flow['is_active']) {
            return ['success' => false, 'message' => 'Fluxo inativo', 'response' => null];
        }
        
        $db = DB::getConnection();
        
        // Atualiza estado da conversa
        $stmt = $db->prepare("
            UPDATE conversations 
            SET bot_last_flow_id = ?,
                bot_context = ?,
                is_bot_active = 1
            WHERE id = ?
        ");
        
        $stmt->execute([
            $flowId,
            json_encode($context),
            $conversationId
        ]);
        
        // Prepara resposta baseada no tipo
        $response = [
            'type' => $flow['response_type'],
            'content' => null,
            'buttons' => null
        ];
        
        switch ($flow['response_type']) {
            case 'text':
                $response['content'] = self::renderMessage($flow['response_message'], $context);
                break;
                
            case 'template':
                // TODO: Buscar template e renderizar
                $response['content'] = 'Template: ' . $flow['response_template_id'];
                break;
                
            case 'media':
                $response['content'] = $flow['response_media_url'];
                $response['media_type'] = $flow['response_media_type'];
                break;
                
            case 'forward_to_human':
                $response['content'] = 'Encaminhando para atendimento humano...';
                break;
        }
        
        // Adiciona botões se houver
        if (!empty($flow['next_buttons'])) {
            $response['buttons'] = json_decode($flow['next_buttons'], true);
        }
        
        // Registra execução do fluxo
        self::logEvent($conversationId, 'flow_executed', [
            'flow_id' => $flowId,
            'flow_name' => $flow['name'],
            'trigger_type' => $flow['trigger_type'],
            'trigger_value' => $flow['trigger_value']
        ]);
        
        // Encaminha para humano se configurado
        if ($flow['forward_to_human']) {
            $stmt = $db->prepare("
                UPDATE conversations 
                SET is_bot_active = 0,
                    assigned_to = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $flow['assign_to_user_id'],
                $conversationId
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Fluxo executado com sucesso',
            'response' => $response
        ];
    }
    
    /**
     * Renderiza mensagem substituindo variáveis
     * 
     * @param string $message Mensagem com variáveis
     * @param array $context Contexto com valores
     * @return string Mensagem renderizada
     */
    private static function renderMessage(string $message, array $context): string
    {
        foreach ($context as $key => $value) {
            $message = str_replace("{{" . $key . "}}", $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Registra evento de chatbot
     * 
     * @param int $conversationId ID da conversa
     * @param string $eventType Tipo de evento
     * @param array $eventData Dados do evento
     * @return int ID do evento criado
     */
    public static function logEvent(int $conversationId, string $eventType, array $eventData = []): int
    {
        $db = DB::getConnection();
        
        // Busca dados da conversa
        $stmt = $db->prepare("
            SELECT lead_id, tenant_id, contact_external_id 
            FROM conversations 
            WHERE id = ?
        ");
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        
        // Extrai telefone do contact_external_id
        $phone = null;
        if ($conv && !empty($conv['contact_external_id'])) {
            // Remove sufixos @c.us, @lid, etc
            $phone = preg_replace('/@.*$/', '', $conv['contact_external_id']);
        }
        
        $stmt = $db->prepare("
            INSERT INTO chatbot_events (
                conversation_id,
                lead_id,
                tenant_id,
                phone_number,
                event_type,
                event_source,
                flow_id,
                event_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $conversationId,
            $conv['lead_id'] ?? null,
            $conv['tenant_id'] ?? null,
            $phone,
            $eventType,
            'chatbot',
            $eventData['flow_id'] ?? null,
            json_encode($eventData)
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Busca eventos de uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @param int $limit Limite de eventos
     * @return array Lista de eventos
     */
    public static function getConversationEvents(int $conversationId, int $limit = 50): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT * FROM chatbot_events
            WHERE conversation_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$conversationId, $limit]);
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Desativa chatbot em uma conversa (passa para atendimento humano)
     * 
     * @param int $conversationId ID da conversa
     * @return bool Sucesso
     */
    public static function deactivateBot(int $conversationId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE conversations 
            SET is_bot_active = 0
            WHERE id = ?
        ");
        
        return $stmt->execute([$conversationId]);
    }
    
    /**
     * Ativa chatbot em uma conversa
     * 
     * @param int $conversationId ID da conversa
     * @return bool Sucesso
     */
    public static function activateBot(int $conversationId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE conversations 
            SET is_bot_active = 1
            WHERE id = ?
        ");
        
        return $stmt->execute([$conversationId]);
    }
}
