<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Services\TrackingCodesService;
use PixelHub\Services\OpportunityProductService;

/**
 * Service para gerenciar Oportunidades (pipeline comercial)
 * 
 * Fluxo: Lead/Cliente → Oportunidade → (won) → Service Order → Projeto
 */
class OpportunityService
{
    /** Etapas do pipeline */
    public const STAGES = [
        'new'         => 'Novo',
        'contact'     => 'Em contato',
        'proposal'    => 'Proposta',
        'negotiation' => 'Negociação',
        'won'         => 'Fechado (Ganho)',
        'lost'        => 'Perdido',
    ];

    /** Status possíveis */
    public const STATUSES = ['active', 'won', 'lost'];

    /**
     * Cria uma nova oportunidade
     */
    public static function create(array $data, ?int $userId = null, ?array $trackingData = null): int
    {
        $db = DB::getConnection();

        $name = trim($data['name'] ?? '');
        if (empty($name)) {
            throw new \InvalidArgumentException('Nome da oportunidade é obrigatório');
        }

        $leadId = !empty($data['lead_id']) ? (int) $data['lead_id'] : null;
        $tenantId = !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null;

        if (!$leadId && !$tenantId) {
            throw new \InvalidArgumentException('A oportunidade deve estar vinculada a um Lead ou Cliente');
        }

        // Validação de tracking code
        if ($trackingData && empty($data['tracking_code'])) {
            // Detectou código mas não informou manualmente - exige confirmação
            throw new \InvalidArgumentException(
                "Código de rastreamento '{$trackingData['tracking_code']}' detectado na mensagem. " .
                "Por favor, confirme o código no campo 'Código Rastreamento'."
            );
        }

        $stage = $data['stage'] ?? 'new';
        if (!array_key_exists($stage, self::STAGES)) {
            $stage = 'new';
        }

        $estimatedValue = isset($data['estimated_value']) && $data['estimated_value'] !== '' 
            ? (float) str_replace(['.', ','], ['', '.'], $data['estimated_value']) 
            : null;

        $responsibleUserId = !empty($data['responsible_user_id']) ? (int) $data['responsible_user_id'] : $userId;
        $serviceId = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        $productId = !empty($data['product_id']) ? (int) $data['product_id'] : null;
        $expectedCloseDate = !empty($data['expected_close_date']) ? $data['expected_close_date'] : null;
        $conversationId = !empty($data['conversation_id']) ? (int) $data['conversation_id'] : null;
        $notes = !empty($data['notes']) ? trim($data['notes']) : null;

        // Campos de tracking
        $trackingCode = !empty($data['tracking_code']) ? trim($data['tracking_code']) : null;
        $trackingSource = !empty($data['tracking_source']) ? trim($data['tracking_source']) : null;
        $trackingAutoDetected = !empty($data['tracking_auto_detected']) ? (bool) $data['tracking_auto_detected'] : false;
        $trackingMetadata = !empty($data['tracking_metadata']) ? $data['tracking_metadata'] : null;

        $stmt = $db->prepare("
            INSERT INTO opportunities 
            (name, stage, estimated_value, status, lead_id, tenant_id, responsible_user_id, 
             service_id, product_id, expected_close_date, conversation_id, notes, tracking_code, tracking_source, 
             tracking_auto_detected, tracking_metadata, created_by, created_at, updated_at)
            VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $name, $stage, $estimatedValue,
            $leadId, $tenantId, $responsibleUserId,
            $serviceId, $productId, $expectedCloseDate, $conversationId,
            $notes, $trackingCode, $trackingSource, 
            $trackingAutoDetected, $trackingMetadata, $userId
        ]);

        $id = (int) $db->lastInsertId();

        // Se detectou tracking automaticamente e não foi informado manualmente, aplica
        if ($trackingData && empty($trackingCode)) {
            $trackingCode = $trackingData['tracking_code'];
            $trackingSource = $trackingData['tracking_source'];
            $trackingAutoDetected = $trackingData['tracking_auto_detected'];
            $trackingMetadata = $trackingData['tracking_metadata'];
            
            // Atualiza com os dados detectados
            $updateStmt = $db->prepare("
                UPDATE opportunities 
                SET tracking_code = ?, tracking_source = ?, tracking_auto_detected = ?, tracking_metadata = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$trackingCode, $trackingSource, $trackingAutoDetected, $trackingMetadata, $id]);
        }

        // Registra histórico
        $historyDesc = 'Oportunidade criada';
        if ($trackingCode || ($trackingData && $trackingData['tracking_code'])) {
            $code = $trackingCode ?? $trackingData['tracking_code'];
            $historyDesc .= " (código: {$code})";
        }
        self::addHistory($id, 'created', null, $stage, $historyDesc, $userId);

        return $id;
    }

    /**
     * Busca oportunidade por ID
     */
    public static function findById(int $id): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT o.*,
                   t.name as tenant_name, t.phone as tenant_phone, t.email as tenant_email,
                   l.name as lead_name, l.phone as lead_phone, l.email as lead_email,
                   u.name as responsible_name,
                   cb.name as created_by_name,
                   p.label as product_label,
                   p.slug as product_slug
            FROM opportunities o
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN leads l ON o.lead_id = l.id
            LEFT JOIN users u ON o.responsible_user_id = u.id
            LEFT JOIN users cb ON o.created_by = cb.id
            LEFT JOIN opportunity_products p ON o.product_id = p.id
            WHERE o.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Lista oportunidades com filtros
     */
    public static function list(array $filters = []): array
    {
        $db = DB::getConnection();

        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = 'o.status = ?';
            $params[] = $filters['status'];
        } elseif (empty($filters['status'])) {
            // Por padrão, mostra apenas ativas
            $where[] = "o.status = 'active'";
        }

        if (!empty($filters['stage'])) {
            $where[] = 'o.stage = ?';
            $params[] = $filters['stage'];
        }

        if (!empty($filters['product_id'])) {
            $where[] = 'o.product_id = ?';
            $params[] = (int) $filters['product_id'];
        }

        if (!empty($filters['responsible_user_id'])) {
            $where[] = 'o.responsible_user_id = ?';
            $params[] = (int) $filters['responsible_user_id'];
        }

        if (!empty($filters['source'])) {
            $where[] = 'l.source = ?';
            $params[] = $filters['source'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $searchDigits = preg_replace('/[^0-9]/', '', trim($filters['search']));
            
            // Verifica se é busca numérica (telefone)
            if (!empty($searchDigits) && strlen($searchDigits) >= 2) {
                // Busca por telefone normalizado + campos de texto
                $where[] = '(
                    (o.name LIKE ? OR t.name LIKE ? OR l.name LIKE ? OR t.email LIKE ? OR l.email LIKE ?) OR
                    (REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(t.phone, \'\'), \'(\', \'\'), \')\', \'\'), \'-\', \'\'), \' \', \'\') LIKE ? OR
                     REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(l.phone, \'\'), \'(\', \'\'), \')\', \'\'), \'-\', \'\'), \' \', \'\') LIKE ?)
                )';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = '%' . $searchDigits . '%';
                $params[] = '%' . $searchDigits . '%';
            } else {
                // Busca só por campos de texto
                $where[] = '(o.name LIKE ? OR t.name LIKE ? OR l.name LIKE ? OR t.email LIKE ? OR l.email LIKE ?)';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT o.*,
                   COALESCE(t.name, l.name, l.phone, l.email) as contact_name,
                   CASE 
        WHEN o.tenant_id IS NOT NULL THEN 
            CASE WHEN t.contact_type = 'client' THEN 'cliente' ELSE 'lead' END
        ELSE 'lead' 
        END as contact_type,
                   t.name as tenant_name,
                   l.name as lead_name,
                   l.phone as lead_phone,
                   u.name as responsible_name,
                   p.label as product_label,
                   p.slug as product_slug,
                   -- Dias sem interação (simplificado)
                   DATEDIFF(CURRENT_DATE, o.updated_at) as days_inactive,
                   -- Tem tarefa agendada (simplificado)
                   (SELECT COUNT(*) FROM agenda_manual_items ami 
                    WHERE ami.opportunity_id = o.id 
                    AND ami.item_date >= CURRENT_DATE 
                    LIMIT 1) > 0 as has_scheduled_task
            FROM opportunities o
            LEFT JOIN tenants t ON o.tenant_id = t.id
            LEFT JOIN leads l ON o.lead_id = l.id
            LEFT JOIN users u ON o.responsible_user_id = u.id
            LEFT JOIN opportunity_products p ON o.product_id = p.id
            WHERE {$whereClause}
            ORDER BY 
                CASE o.stage 
                    WHEN 'new' THEN 1 
                    WHEN 'contact' THEN 2 
                    WHEN 'proposal' THEN 3 
                    WHEN 'negotiation' THEN 4 
                    WHEN 'won' THEN 5 
                    WHEN 'lost' THEN 6 
                END,
                o.updated_at DESC
            LIMIT 200
        ");
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Atualiza oportunidade
     */
    public static function update(int $id, array $data, ?int $userId = null): bool
    {
        $db = DB::getConnection();
        $current = self::findById($id);
        if (!$current) return false;

        $fields = [];
        $params = [];

        if (isset($data['name']) && trim($data['name']) !== '') {
            $fields[] = 'name = ?';
            $params[] = trim($data['name']);
        }

        if (isset($data['estimated_value'])) {
            $newValue = $data['estimated_value'] !== '' 
                ? (float) str_replace(['.', ','], ['', '.'], $data['estimated_value']) 
                : null;
            if ($newValue != $current['estimated_value']) {
                self::addHistory($id, 'value_changed', 
                    $current['estimated_value'] ? number_format($current['estimated_value'], 2, ',', '.') : 'N/A',
                    $newValue ? number_format($newValue, 2, ',', '.') : 'N/A',
                    'Valor estimado alterado', $userId);
            }
            $fields[] = 'estimated_value = ?';
            $params[] = $newValue;
        }

        if (isset($data['responsible_user_id'])) {
            $fields[] = 'responsible_user_id = ?';
            $params[] = !empty($data['responsible_user_id']) ? (int) $data['responsible_user_id'] : null;
            if ($data['responsible_user_id'] != $current['responsible_user_id']) {
                self::addHistory($id, 'assigned', null, null, 'Responsável alterado', $userId);
            }
        }

        if (isset($data['product_id'])) {
            $fields[] = 'product_id = ?';
            $params[] = !empty($data['product_id']) ? (int) $data['product_id'] : null;
        }

        if (isset($data['service_id'])) {
            $fields[] = 'service_id = ?';
            $params[] = !empty($data['service_id']) ? (int) $data['service_id'] : null;
        }

        if (isset($data['expected_close_date'])) {
            $fields[] = 'expected_close_date = ?';
            $params[] = !empty($data['expected_close_date']) ? $data['expected_close_date'] : null;
        }

        if (isset($data['notes'])) {
            $fields[] = 'notes = ?';
            $params[] = !empty($data['notes']) ? trim($data['notes']) : null;
        }

        if (empty($fields)) return true;

        $fields[] = 'updated_at = NOW()';
        $params[] = $id;

        $sql = "UPDATE opportunities SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Muda a etapa da oportunidade
     */
    public static function changeStage(int $id, string $newStage, ?int $userId = null): bool
    {
        if (!array_key_exists($newStage, self::STAGES)) {
            throw new \InvalidArgumentException("Etapa inválida: {$newStage}");
        }

        $db = DB::getConnection();
        $current = self::findById($id);
        if (!$current) return false;

        $oldStage = $current['stage'];
        if ($oldStage === $newStage) return true;

        // Se mudou para 'won', usa markAsWon
        if ($newStage === 'won') {
            return self::markAsWon($id, $userId);
        }

        // Se mudou para 'lost', usa markAsLost
        if ($newStage === 'lost') {
            return self::markAsLost($id, null, $userId);
        }

        $stmt = $db->prepare("UPDATE opportunities SET stage = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStage, $id]);

        self::addHistory($id, 'stage_changed', 
            self::STAGES[$oldStage] ?? $oldStage, 
            self::STAGES[$newStage] ?? $newStage,
            "Etapa alterada de " . (self::STAGES[$oldStage] ?? $oldStage) . " para " . (self::STAGES[$newStage] ?? $newStage),
            $userId);

        return true;
    }

    /**
     * Marca oportunidade como ganha e cria service_order automaticamente
     */
    public static function markAsWon(int $id, ?int $userId = null): bool
    {
        $db = DB::getConnection();
        $opp = self::findById($id);
        if (!$opp) return false;

        $db->beginTransaction();
        try {
            $oldStage = $opp['stage'];

            // Atualiza oportunidade
            $stmt = $db->prepare("
                UPDATE opportunities 
                SET stage = 'won', status = 'won', won_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            // Cria service_order automaticamente se tiver service_id
            $serviceOrderId = null;
            if (!empty($opp['service_id'])) {
                try {
                    $serviceOrderId = ServiceOrderService::createOrder([
                        'service_id' => $opp['service_id'],
                        'tenant_id' => $opp['tenant_id'],
                        'contract_value' => $opp['estimated_value'],
                        'notes' => "Gerado automaticamente da oportunidade #{$id}: " . ($opp['name'] ?? ''),
                        'created_by' => $userId,
                    ]);

                    // Vincula service_order à oportunidade
                    $stmt2 = $db->prepare("UPDATE opportunities SET service_order_id = ? WHERE id = ?");
                    $stmt2->execute([$serviceOrderId, $id]);
                } catch (\Exception $e) {
                    error_log("[Opportunity] Erro ao criar service_order para opp #{$id}: " . $e->getMessage());
                    // Não bloqueia — oportunidade é marcada como ganha mesmo sem service_order
                }
            }

            self::addHistory($id, 'status_changed', 
                self::STAGES[$oldStage] ?? $oldStage, 'Fechado (Ganho)',
                'Oportunidade marcada como GANHA' . ($serviceOrderId ? " — Pedido #{$serviceOrderId} criado" : ''),
                $userId);

            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("[Opportunity] Erro ao marcar como ganha #{$id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca oportunidade como perdida
     */
    public static function markAsLost(int $id, ?string $reason = null, ?int $userId = null): bool
    {
        $db = DB::getConnection();
        $opp = self::findById($id);
        if (!$opp) return false;

        $oldStage = $opp['stage'];

        $stmt = $db->prepare("
            UPDATE opportunities 
            SET stage = 'lost', status = 'lost', lost_at = NOW(), lost_reason = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$reason, $id]);

        self::addHistory($id, 'status_changed', 
            self::STAGES[$oldStage] ?? $oldStage, 'Perdido',
            'Oportunidade marcada como PERDIDA' . ($reason ? ": {$reason}" : ''),
            $userId);

        return true;
    }

    /**
     * Reabrir oportunidade perdida
     */
    public static function reopen(int $id, ?int $userId = null): bool
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            UPDATE opportunities 
            SET stage = 'new', status = 'active', lost_at = NULL, lost_reason = NULL, 
                won_at = NULL, service_order_id = NULL, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        self::addHistory($id, 'status_changed', 'Fechado', 'Novo', 'Oportunidade reaberta', $userId);
        return true;
    }

    /**
     * Busca histórico da oportunidade
     */
    public static function getHistory(int $opportunityId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT oh.*, u.name as user_name
            FROM opportunity_history oh
            LEFT JOIN users u ON oh.user_id = u.id
            WHERE oh.opportunity_id = ?
            AND oh.description NOT LIKE '%WhatsApp:%'
            ORDER BY oh.created_at DESC
        ");
        $stmt->execute([$opportunityId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Adiciona entrada no histórico
     */
    private static function addHistory(int $oppId, string $action, ?string $oldValue, ?string $newValue, ?string $description, ?int $userId): void
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            INSERT INTO opportunity_history (opportunity_id, action, old_value, new_value, description, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$oppId, $action, $oldValue, $newValue, $description, $userId]);
    }

    /**
     * Registra uma interação genérica (ex: WhatsApp enviado) no histórico
     */
    public static function addInteractionHistory(int $oppId, string $description, ?int $userId = null): void
    {
        self::addHistory($oppId, 'note_added', null, null, $description, $userId);
    }

    /**
     * Conta oportunidades por status
     */
    public static function countByStatus(): array
    {
        $db = DB::getConnection();
        $stmt = $db->query("
            SELECT status, COUNT(*) as total, COALESCE(SUM(estimated_value), 0) as total_value
            FROM opportunities
            GROUP BY status
        ");
        $rows = $stmt->fetchAll() ?: [];
        $result = ['active' => 0, 'won' => 0, 'lost' => 0, 'active_value' => 0, 'won_value' => 0];
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['total'];
            $result[$row['status'] . '_value'] = (float) $row['total_value'];
        }
        return $result;
    }
}
