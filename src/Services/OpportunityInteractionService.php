<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

class OpportunityInteractionService
{
    // Tipos de interação (alinhado com mercado)
    const TYPES = [
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'call' => 'Chamada',
        'meeting' => 'Reunião',
        'note' => 'Nota'
    ];

    /**
     * Registra interação de WhatsApp (automático)
     */
    public static function logWhatsApp(int $opportunityId, string $direction, string $content, array $metadata = [], ?int $userId = null): void
    {
        error_log("[Interaction] logWhatsApp chamado: oppId={$opportunityId}, direction={$direction}, content=" . substr($content, 0, 50) . "...");
        
        $title = $direction === 'inbound' ? 'Mensagem recebida' : 'Mensagem enviada';
        
        self::addInteraction($opportunityId, 'whatsapp', $direction, $title, $content, $metadata, $userId);
    }

    /**
     * Registra interação de Email
     */
    public static function logEmail(int $opportunityId, string $direction, string $subject, string $content, array $metadata = [], ?int $userId = null): void
    {
        $title = $direction === 'inbound' ? 'E-mail recebido' : 'E-mail enviado';
        $title .= $subject ? ": {$subject}" : '';
        
        self::addInteraction($opportunityId, 'email', $direction, $title, $content, $metadata, $userId);
    }

    /**
     * Adiciona nota manual
     */
    public static function logNote(int $opportunityId, string $content, ?int $userId = null): void
    {
        self::addInteraction($opportunityId, 'note', 'outbound', 'Nota adicionada', $content, [], $userId);
    }

    /**
     * Busca interações com filtros (estilo CRM)
     */
    public static function getInteractions(int $opportunityId, array $filters = []): array
    {
        $db = DB::getConnection();
        
        $where = "WHERE oi.opportunity_id = ?";
        $params = [$opportunityId];

        // Filtros
        if (!empty($filters['type'])) {
            $where .= " AND oi.interaction_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['direction'])) {
            $where .= " AND oi.direction = ?";
            $params[] = $filters['direction'];
        }

        if (!empty($filters['date_from'])) {
            $where .= " AND oi.created_at >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where .= " AND oi.created_at <= ?";
            $params[] = $filters['date_to'];
        }

        // Limite e ordenação
        $limit = !empty($filters['limit']) ? "LIMIT " . (int)$filters['limit'] : "";
        $order = !empty($filters['order']) ? $filters['order'] : "ORDER BY oi.created_at DESC";

        $sql = "
            SELECT oi.*, u.name as user_name
            FROM opportunity_interactions oi
            LEFT JOIN users u ON oi.user_id = u.id
            {$where}
            {$order}
            {$limit}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Resumo para dashboard (estilo HubSpot)
     */
    public static function getSummary(int $opportunityId): array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT 
                interaction_type,
                direction,
                COUNT(*) as count,
                MAX(created_at) as last_interaction
            FROM opportunity_interactions 
            WHERE opportunity_id = ?
            GROUP BY interaction_type, direction
            ORDER BY last_interaction DESC
        ");
        $stmt->execute([$opportunityId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Método privado para adicionar interação
     */
    private static function addInteraction(int $opportunityId, string $type, string $direction, string $title, ?string $content, array $metadata, ?int $userId): void
    {
        error_log("[Interaction] addInteraction: oppId={$opportunityId}, type={$type}, direction={$direction}");
        
        $db = DB::getConnection();
        $stmt = $db->prepare("
            INSERT INTO opportunity_interactions 
            (opportunity_id, interaction_type, direction, title, content, metadata, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $metadataJson = empty($metadata) ? null : json_encode($metadata);
        $result = $stmt->execute([$opportunityId, $type, $direction, $title, $content, $metadataJson, $userId]);
        
        error_log("[Interaction] addInteraction result: " . ($result ? 'SUCCESS' : 'FAILED'));
    }
}
