<?php

namespace PixelHub\Services;

use PDO;
use PixelHub\Core\DB;

/**
 * Service para inspeção completa de templates WhatsApp
 * 
 * Lê do backend (fonte de verdade):
 * - Fluxos de chatbot vinculados aos botões do template
 * - Eventos registrados (cliques, execuções)
 * - Logs de execução real
 * 
 * Data: 2026-03-05
 */
class TemplateInspectorService
{
    /**
     * Retorna dados completos para o Template Inspector
     * 
     * @param int $templateId ID do template
     * @return array Estrutura completa com flows, events, logs
     */
    public static function getInspectorData(int $templateId): array
    {
        $template = MetaTemplateService::getById($templateId);
        
        if (!$template) {
            return ['error' => 'Template não encontrado'];
        }
        
        $buttons = !empty($template['buttons']) ? json_decode($template['buttons'], true) : [];
        
        return [
            'template' => $template,
            'buttons' => $buttons,
            'flows' => self::getFlowsForTemplate($buttons, $template['tenant_id']),
            'events' => self::getEventsForTemplate($templateId),
            'logs' => self::getLogsForTemplate($templateId),
            'stats' => self::getStatsForTemplate($templateId)
        ];
    }
    
    /**
     * Busca fluxos de chatbot vinculados aos botões do template
     * 
     * @param array $buttons Lista de botões do template
     * @param int|null $tenantId ID do tenant
     * @return array Mapeamento button_id => flow
     */
    private static function getFlowsForTemplate(array $buttons, ?int $tenantId): array
    {
        if (empty($buttons)) {
            return [];
        }
        
        $db = DB::getConnection();
        $flows = [];
        
        foreach ($buttons as $button) {
            $buttonId = $button['id'] ?? null;
            
            if (!$buttonId) {
                continue;
            }
            
            // Busca fluxo correspondente ao botão
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
                WHERE f.trigger_type = 'template_button'
                AND f.trigger_value = ?
                AND (f.tenant_id = ? OR f.tenant_id IS NULL)
                AND f.is_active = 1
                ORDER BY f.priority DESC
                LIMIT 1
            ");
            
            $stmt->execute([$buttonId, $tenantId]);
            $flow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($flow) {
                // Decodifica JSON fields
                $flow['next_buttons'] = !empty($flow['next_buttons']) ? json_decode($flow['next_buttons'], true) : [];
                $flow['add_tags'] = !empty($flow['add_tags']) ? json_decode($flow['add_tags'], true) : [];
                
                $flows[$buttonId] = $flow;
            } else {
                $flows[$buttonId] = null; // Botão sem fluxo configurado
            }
        }
        
        return $flows;
    }
    
    /**
     * Busca eventos registrados para o template
     * 
     * @param int $templateId ID do template
     * @return array Lista de eventos únicos (tipos de evento que ocorreram)
     */
    private static function getEventsForTemplate(int $templateId): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                event_type,
                COUNT(*) as count,
                MAX(created_at) as last_occurrence
            FROM chatbot_events
            WHERE template_id = ?
            GROUP BY event_type
            ORDER BY count DESC
        ");
        
        $stmt->execute([$templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    /**
     * Busca logs de execução real do template
     * 
     * @param int $templateId ID do template
     * @param int $limit Número máximo de logs
     * @return array Lista de execuções recentes
     */
    private static function getLogsForTemplate(int $templateId, int $limit = 50): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                ce.*,
                l.name as lead_name,
                cf.name as flow_name,
                cf.response_type as flow_response_type
            FROM chatbot_events ce
            LEFT JOIN leads l ON ce.lead_id = l.id
            LEFT JOIN chatbot_flows cf ON ce.flow_id = cf.id
            WHERE ce.template_id = ?
            ORDER BY ce.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$templateId, $limit]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        // Decodifica event_data JSON
        foreach ($logs as &$log) {
            $log['event_data'] = !empty($log['event_data']) ? json_decode($log['event_data'], true) : [];
        }
        
        return $logs;
    }
    
    /**
     * Calcula estatísticas do template
     * 
     * @param int $templateId ID do template
     * @return array Estatísticas agregadas
     */
    private static function getStatsForTemplate(int $templateId): array
    {
        $db = DB::getConnection();
        
        // Total de envios
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_sent
            FROM chatbot_events
            WHERE template_id = ?
            AND event_type = 'template_sent'
        ");
        $stmt->execute([$templateId]);
        $totalSent = (int) $stmt->fetchColumn();
        
        // Total de cliques em botões
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_clicks
            FROM chatbot_events
            WHERE template_id = ?
            AND event_type = 'button_clicked'
        ");
        $stmt->execute([$templateId]);
        $totalClicks = (int) $stmt->fetchColumn();
        
        // Total de fluxos executados
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_flows
            FROM chatbot_events
            WHERE template_id = ?
            AND event_type = 'flow_executed'
        ");
        $stmt->execute([$templateId]);
        $totalFlows = (int) $stmt->fetchColumn();
        
        // Taxa de clique (CTR)
        $ctr = $totalSent > 0 ? round(($totalClicks / $totalSent) * 100, 2) : 0;
        
        // Botão mais clicado
        $stmt = $db->prepare("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(event_data, '$.button_id')) as button_id,
                COUNT(*) as clicks
            FROM chatbot_events
            WHERE template_id = ?
            AND event_type = 'button_clicked'
            GROUP BY button_id
            ORDER BY clicks DESC
            LIMIT 1
        ");
        $stmt->execute([$templateId]);
        $topButton = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_sent' => $totalSent,
            'total_clicks' => $totalClicks,
            'total_flows_executed' => $totalFlows,
            'click_through_rate' => $ctr,
            'top_button' => $topButton ?: null
        ];
    }
    
    /**
     * Simula execução de um botão (para aba Testar)
     * 
     * @param int $templateId ID do template
     * @param string $buttonId ID do botão
     * @param int|null $tenantId ID do tenant
     * @return array Resultado da simulação
     */
    public static function simulateButtonClick(int $templateId, string $buttonId, ?int $tenantId): array
    {
        $template = MetaTemplateService::getById($templateId);
        
        if (!$template) {
            return ['success' => false, 'message' => 'Template não encontrado'];
        }
        
        // Busca fluxo correspondente
        $flow = ChatbotFlowService::findByTrigger('template_button', $buttonId, $tenantId);
        
        if (!$flow) {
            return [
                'success' => false,
                'message' => 'Nenhum fluxo configurado para este botão',
                'button_id' => $buttonId
            ];
        }
        
        // Simula execução (não executa de verdade, apenas mostra o que aconteceria)
        $simulation = [
            'success' => true,
            'message' => 'Simulação concluída',
            'button_id' => $buttonId,
            'flow' => [
                'id' => $flow['id'],
                'name' => $flow['name'],
                'trigger_type' => $flow['trigger_type'],
                'trigger_value' => $flow['trigger_value']
            ],
            'actions' => []
        ];
        
        // Ação 1: Enviar resposta
        if ($flow['response_type'] === 'text' && !empty($flow['response_message'])) {
            $simulation['actions'][] = [
                'type' => 'send_message',
                'description' => 'Enviar mensagem de texto',
                'content' => $flow['response_message']
            ];
        } elseif ($flow['response_type'] === 'template' && !empty($flow['response_template_id'])) {
            $simulation['actions'][] = [
                'type' => 'send_template',
                'description' => 'Enviar template WhatsApp',
                'template_id' => $flow['response_template_id'],
                'template_name' => $flow['response_template_name'] ?? 'Template #' . $flow['response_template_id']
            ];
        } elseif ($flow['response_type'] === 'media' && !empty($flow['response_media_url'])) {
            $simulation['actions'][] = [
                'type' => 'send_media',
                'description' => 'Enviar mídia (' . $flow['response_media_type'] . ')',
                'media_url' => $flow['response_media_url']
            ];
        }
        
        // Ação 2: Adicionar tags
        if (!empty($flow['add_tags'])) {
            $tags = json_decode($flow['add_tags'], true);
            $simulation['actions'][] = [
                'type' => 'add_tags',
                'description' => 'Adicionar tags ao lead',
                'tags' => $tags
            ];
        }
        
        // Ação 3: Atualizar status do lead
        if (!empty($flow['update_lead_status'])) {
            $simulation['actions'][] = [
                'type' => 'update_lead_status',
                'description' => 'Atualizar status do lead',
                'new_status' => $flow['update_lead_status']
            ];
        }
        
        // Ação 4: Encaminhar para humano
        if ($flow['forward_to_human']) {
            $simulation['actions'][] = [
                'type' => 'forward_to_human',
                'description' => 'Encaminhar para atendimento humano',
                'assign_to' => $flow['assign_to_user_name'] ?? 'Qualquer atendente'
            ];
        }
        
        // Ação 5: Próximos botões
        if (!empty($flow['next_buttons'])) {
            $nextButtons = json_decode($flow['next_buttons'], true);
            $simulation['actions'][] = [
                'type' => 'show_buttons',
                'description' => 'Exibir botões de próxima interação',
                'buttons' => $nextButtons
            ];
        }
        
        return $simulation;
    }
}
