<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PDO;

/**
 * Service para agregação de histórico de WhatsApp
 * 
 * Unifica registros de billing_notifications e whatsapp_generic_logs
 * em uma timeline cronológica única.
 */
class WhatsAppHistoryService
{
    /**
     * Busca timeline unificada de histórico WhatsApp do tenant
     * 
     * @param int $tenantId ID do tenant
     * @param int $limit Limite de registros (padrão: 10)
     * @return array Array normalizado de registros ordenados por sent_at DESC
     */
    public static function getTimelineByTenant(int $tenantId, int $limit = 10): array
    {
        $db = DB::getConnection();

        // Busca registros de billing_notifications
        $billingStmt = $db->prepare("
            SELECT 
                bn.id,
                bn.sent_at,
                bn.template as stage,
                bn.invoice_id,
                bn.message,
                bn.phone_normalized as phone,
                bi.id as invoice_id_exists
            FROM billing_notifications bn
            LEFT JOIN billing_invoices bi ON bn.invoice_id = bi.id
            WHERE bn.tenant_id = ? 
            AND bn.sent_at IS NOT NULL
            ORDER BY bn.sent_at DESC
            LIMIT ?
        ");
        $billingStmt->execute([$tenantId, $limit * 2]); // Busca mais para garantir que temos o suficiente após unificação
        $billingRecords = $billingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Busca registros de whatsapp_generic_logs
        $genericStmt = $db->prepare("
            SELECT 
                id,
                sent_at,
                template_id,
                phone,
                message
            FROM whatsapp_generic_logs
            WHERE tenant_id = ?
            AND sent_at IS NOT NULL
            ORDER BY sent_at DESC
            LIMIT ?
        ");
        $genericStmt->execute([$tenantId, $limit * 2]);
        $genericRecords = $genericStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Normaliza registros de billing
        $normalizedBilling = [];
        foreach ($billingRecords as $record) {
            $message = $record['message'] ?? '';
            $normalizedBilling[] = [
                'source' => 'billing',
                'sent_at' => $record['sent_at'],
                'template_id' => null, // billing_notifications não tem template_id direto
                'template_name' => null, // Será resolvido depois se necessário
                'description' => self::formatBillingDescription($record),
                'message' => $message,
                'message_full' => $message, // Mensagem completa para modal de detalhes
                'phone' => $record['phone'] ?? null, // Telefone normalizado
                'invoice_id' => $record['invoice_id'] ?? null,
                'stage' => $record['stage'] ?? null,
            ];
        }

        // Normaliza registros genéricos
        $normalizedGeneric = [];
        foreach ($genericRecords as $record) {
            $templateName = null;
            if (!empty($record['template_id'])) {
                $template = WhatsAppTemplateService::getById((int)$record['template_id']);
                $templateName = $template['name'] ?? null;
            }

            $message = $record['message'] ?? '';
            $normalizedGeneric[] = [
                'source' => 'generic',
                'sent_at' => $record['sent_at'],
                'template_id' => $record['template_id'] ? (int)$record['template_id'] : null,
                'template_name' => $templateName,
                'description' => 'Envio manual pela Visão Geral',
                'message' => $message,
                'message_full' => $message, // Mensagem completa para modal de detalhes
                'phone' => $record['phone'] ?? null, // Telefone normalizado
                'invoice_id' => null,
                'stage' => null,
            ];
        }

        // Unifica e ordena por sent_at DESC
        $unified = array_merge($normalizedBilling, $normalizedGeneric);
        
        // Ordena por sent_at DESC
        usort($unified, function($a, $b) {
            $dateA = $a['sent_at'] ? strtotime($a['sent_at']) : 0;
            $dateB = $b['sent_at'] ? strtotime($b['sent_at']) : 0;
            return $dateB <=> $dateA; // DESC
        });

        // Limita ao número solicitado
        return array_slice($unified, 0, $limit);
    }

    /**
     * Formata descrição para registros de billing
     * 
     * @param array $record Registro de billing_notifications
     * @return string Descrição formatada
     */
    private static function formatBillingDescription(array $record): string
    {
        $invoiceId = $record['invoice_id'] ?? null;
        $stage = $record['stage'] ?? null;

        if ($invoiceId) {
            $stageLabel = self::getStageLabel($stage);
            return "Cobrança fatura #{$invoiceId} – {$stageLabel}";
        }

        return "Cobrança – " . self::getStageLabel($stage);
    }

    /**
     * Retorna label legível para estágio de cobrança
     * 
     * @param string|null $stage Estágio (pre_due, overdue_3d, overdue_7d, etc.)
     * @return string Label legível
     */
    private static function getStageLabel(?string $stage): string
    {
        $labels = [
            'pre_due' => '1ª cobrança',
            'overdue_3d' => '2ª cobrança (3 dias)',
            'overdue_7d' => '3ª cobrança (7 dias)',
        ];

        return $labels[$stage] ?? ($stage ?? 'Cobrança');
    }
}

