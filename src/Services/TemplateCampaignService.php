<?php

namespace PixelHub\Services;

use PDO;
use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsApp\MetaOfficialProvider;

/**
 * Service para gerenciar campanhas de envio em massa de templates
 * 
 * Responsável por:
 * - CRUD de campanhas
 * - Envio em lotes com rate limiting
 * - Rastreamento de métricas (enviados, entregues, cliques)
 * - Gestão de status de campanha
 * 
 * Data: 2026-03-04
 */
class TemplateCampaignService
{
    /**
     * Lista todas as campanhas, opcionalmente filtradas
     * 
     * @param int|null $tenantId Filtrar por tenant
     * @param string|null $status Filtrar por status
     * @return array Lista de campanhas
     */
    public static function listCampaigns(?int $tenantId = null, ?string $status = null): array
    {
        $db = DB::getConnection();
        
        $where = [];
        $params = [];
        
        if ($tenantId !== null) {
            $where[] = "c.tenant_id = ?";
            $params[] = $tenantId;
        }
        
        if ($status !== null) {
            $where[] = "c.status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $stmt = $db->prepare("
            SELECT 
                c.*,
                t.name as tenant_name,
                tpl.template_name,
                tpl.category as template_category,
                u.name as created_by_name
            FROM template_campaigns c
            LEFT JOIN tenants t ON c.tenant_id = t.id
            LEFT JOIN whatsapp_message_templates tpl ON c.template_id = tpl.id
            LEFT JOIN users u ON c.created_by = u.id
            {$whereClause}
            ORDER BY c.created_at DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Busca campanha por ID
     * 
     * @param int $id ID da campanha
     * @return array|null Campanha ou null se não encontrado
     */
    public static function getById(int $id): ?array
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            SELECT 
                c.*,
                t.name as tenant_name,
                tpl.template_name,
                tpl.content as template_content,
                tpl.buttons as template_buttons
            FROM template_campaigns c
            LEFT JOIN tenants t ON c.tenant_id = t.id
            LEFT JOIN whatsapp_message_templates tpl ON c.template_id = tpl.id
            WHERE c.id = ?
        ");
        
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Cria uma nova campanha
     * 
     * @param array $data Dados da campanha
     * @return int ID da campanha criada
     */
    public static function create(array $data): int
    {
        $db = DB::getConnection();
        
        // Valida template
        $template = MetaTemplateService::getById($data['template_id']);
        if (!$template || $template['status'] !== 'approved') {
            throw new \Exception('Template não aprovado ou não encontrado');
        }
        
        // Processa lista de telefones
        $targetList = $data['target_list'];
        if (is_string($targetList)) {
            $targetList = json_decode($targetList, true);
        }
        
        $totalCount = count($targetList);
        
        $stmt = $db->prepare("
            INSERT INTO template_campaigns (
                tenant_id,
                template_id,
                name,
                description,
                target_list,
                batch_size,
                batch_delay_seconds,
                status,
                scheduled_at,
                total_count,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['tenant_id'] ?? null,
            $data['template_id'],
            $data['name'],
            $data['description'] ?? null,
            json_encode($targetList),
            $data['batch_size'] ?? 50,
            $data['batch_delay_seconds'] ?? 60,
            $data['status'] ?? 'draft',
            $data['scheduled_at'] ?? null,
            $totalCount,
            $data['created_by'] ?? null
        ]);
        
        return (int) $db->lastInsertId();
    }
    
    /**
     * Atualiza uma campanha
     * 
     * @param int $id ID da campanha
     * @param array $data Dados para atualizar
     * @return bool Sucesso
     */
    public static function update(int $id, array $data): bool
    {
        $db = DB::getConnection();
        
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'name', 'description', 'target_list', 'batch_size', 
            'batch_delay_seconds', 'status', 'scheduled_at'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                
                if ($field === 'target_list' && is_array($data[$field])) {
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
            UPDATE template_campaigns 
            SET " . implode(", ", $fields) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($params);
    }
    
    /**
     * Inicia execução de uma campanha
     * 
     * @param int $campaignId ID da campanha
     * @return array ['success' => bool, 'message' => string]
     */
    public static function start(int $campaignId): array
    {
        $campaign = self::getById($campaignId);
        
        if (!$campaign) {
            return ['success' => false, 'message' => 'Campanha não encontrada'];
        }
        
        if ($campaign['status'] !== 'draft' && $campaign['status'] !== 'scheduled') {
            return ['success' => false, 'message' => 'Campanha não pode ser iniciada neste status'];
        }
        
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE template_campaigns 
            SET status = 'running', started_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$campaignId]);
        
        return ['success' => true, 'message' => 'Campanha iniciada'];
    }
    
    /**
     * Pausa execução de uma campanha
     * 
     * @param int $campaignId ID da campanha
     * @return bool Sucesso
     */
    public static function pause(int $campaignId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE template_campaigns 
            SET status = 'paused'
            WHERE id = ? AND status = 'running'
        ");
        
        return $stmt->execute([$campaignId]);
    }
    
    /**
     * Retoma execução de uma campanha pausada
     * 
     * @param int $campaignId ID da campanha
     * @return bool Sucesso
     */
    public static function resume(int $campaignId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE template_campaigns 
            SET status = 'running'
            WHERE id = ? AND status = 'paused'
        ");
        
        return $stmt->execute([$campaignId]);
    }
    
    /**
     * Processa próximo lote de envios de uma campanha
     * 
     * @param int $campaignId ID da campanha
     * @return array ['success' => bool, 'sent' => int, 'failed' => int, 'completed' => bool]
     */
    public static function processNextBatch(int $campaignId): array
    {
        $campaign = self::getById($campaignId);
        
        if (!$campaign || $campaign['status'] !== 'running') {
            return ['success' => false, 'sent' => 0, 'failed' => 0, 'completed' => false];
        }
        
        $targetList = json_decode($campaign['target_list'], true);
        $batchSize = (int) $campaign['batch_size'];
        $sentCount = (int) $campaign['sent_count'];
        $failedCount = (int) $campaign['failed_count'];
        
        // Pega próximo lote
        $batch = array_slice($targetList, $sentCount + $failedCount, $batchSize);
        
        if (empty($batch)) {
            // Campanha concluída
            self::markAsCompleted($campaignId);
            return ['success' => true, 'sent' => 0, 'failed' => 0, 'completed' => true];
        }
        
        $sent = 0;
        $failed = 0;
        $errorLog = json_decode($campaign['error_log'] ?? '[]', true) ?: [];
        
        // Processa cada item do lote
        foreach ($batch as $item) {
            $phone = $item['phone'] ?? null;
            $variables = $item['variables'] ?? [];
            
            if (!$phone) {
                $failed++;
                $errorLog[] = [
                    'phone' => 'N/A',
                    'error' => 'Telefone não fornecido',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                continue;
            }
            
            try {
                // TODO: Implementar envio via MetaOfficialProvider
                // Por enquanto, apenas simula sucesso
                $sent++;
                
                // Registra evento de envio
                ChatbotFlowService::logEvent(0, 'template_sent', [
                    'campaign_id' => $campaignId,
                    'template_id' => $campaign['template_id'],
                    'phone' => $phone
                ]);
                
                // Rate limiting: aguarda entre envios
                usleep(100000); // 100ms entre mensagens
                
            } catch (\Exception $e) {
                $failed++;
                $errorLog[] = [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Atualiza contadores da campanha
        $db = DB::getConnection();
        $stmt = $db->prepare("
            UPDATE template_campaigns 
            SET sent_count = sent_count + ?,
                failed_count = failed_count + ?,
                error_log = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $sent,
            $failed,
            json_encode($errorLog),
            $campaignId
        ]);
        
        // Verifica se completou
        $newSentCount = $sentCount + $sent;
        $newFailedCount = $failedCount + $failed;
        $totalCount = (int) $campaign['total_count'];
        
        $completed = ($newSentCount + $newFailedCount) >= $totalCount;
        
        if ($completed) {
            self::markAsCompleted($campaignId);
        }
        
        return [
            'success' => true,
            'sent' => $sent,
            'failed' => $failed,
            'completed' => $completed
        ];
    }
    
    /**
     * Marca campanha como concluída
     * 
     * @param int $campaignId ID da campanha
     * @return bool Sucesso
     */
    private static function markAsCompleted(int $campaignId): bool
    {
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE template_campaigns 
            SET status = 'completed', completed_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$campaignId]);
    }
    
    /**
     * Atualiza métricas de entrega da campanha
     * 
     * @param int $campaignId ID da campanha
     * @param string $metric Métrica a atualizar (delivered, read, clicked)
     * @param int $increment Valor a incrementar
     * @return bool Sucesso
     */
    public static function updateMetric(int $campaignId, string $metric, int $increment = 1): bool
    {
        $allowedMetrics = ['delivered_count', 'read_count', 'clicked_count'];
        
        if (!in_array($metric, $allowedMetrics)) {
            return false;
        }
        
        $db = DB::getConnection();
        
        $stmt = $db->prepare("
            UPDATE template_campaigns 
            SET {$metric} = {$metric} + ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$increment, $campaignId]);
    }
    
    /**
     * Busca campanhas pendentes para processamento
     * 
     * @return array Lista de campanhas
     */
    public static function getPendingCampaigns(): array
    {
        $db = DB::getConnection();
        
        $stmt = $db->query("
            SELECT * FROM template_campaigns
            WHERE status = 'running'
            AND (sent_count + failed_count) < total_count
            ORDER BY scheduled_at ASC, created_at ASC
            LIMIT 10
        ");
        
        return $stmt->fetchAll() ?: [];
    }
    
    /**
     * Deleta uma campanha
     * 
     * @param int $id ID da campanha
     * @return bool Sucesso
     */
    public static function delete(int $id): bool
    {
        $campaign = self::getById($id);
        
        if ($campaign && $campaign['status'] === 'running') {
            return false; // Não pode deletar campanha em execução
        }
        
        $db = DB::getConnection();
        
        $stmt = $db->prepare("DELETE FROM template_campaigns WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Gera relatório de métricas da campanha
     * 
     * @param int $campaignId ID da campanha
     * @return array Métricas consolidadas
     */
    public static function getMetrics(int $campaignId): array
    {
        $campaign = self::getById($campaignId);
        
        if (!$campaign) {
            return [];
        }
        
        $totalCount = (int) $campaign['total_count'];
        $sentCount = (int) $campaign['sent_count'];
        $deliveredCount = (int) $campaign['delivered_count'];
        $readCount = (int) $campaign['read_count'];
        $clickedCount = (int) $campaign['clicked_count'];
        $failedCount = (int) $campaign['failed_count'];
        
        return [
            'total' => $totalCount,
            'sent' => $sentCount,
            'delivered' => $deliveredCount,
            'read' => $readCount,
            'clicked' => $clickedCount,
            'failed' => $failedCount,
            'pending' => $totalCount - $sentCount - $failedCount,
            'delivery_rate' => $sentCount > 0 ? round(($deliveredCount / $sentCount) * 100, 2) : 0,
            'read_rate' => $deliveredCount > 0 ? round(($readCount / $deliveredCount) * 100, 2) : 0,
            'click_rate' => $deliveredCount > 0 ? round(($clickedCount / $deliveredCount) * 100, 2) : 0,
            'failure_rate' => $totalCount > 0 ? round(($failedCount / $totalCount) * 100, 2) : 0
        ];
    }
}
