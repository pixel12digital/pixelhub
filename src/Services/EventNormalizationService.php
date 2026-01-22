<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;

/**
 * Service para normalizar eventos de diferentes sistemas
 */
class EventNormalizationService
{
    /**
     * Normaliza um evento bruto para formato padrão
     * 
     * @param array $event Evento bruto (da tabela communication_events)
     * @return array Evento normalizado
     */
    public static function normalize(array $event): array
    {
        $payload = is_string($event['payload']) 
            ? json_decode($event['payload'], true) 
            : $event['payload'];

        $metadata = !empty($event['metadata']) 
            ? (is_string($event['metadata']) ? json_decode($event['metadata'], true) : $event['metadata'])
            : [];

        // Resolve tenant_id se não estiver definido
        $tenantId = $event['tenant_id'];
        if (empty($tenantId)) {
            $tenantId = self::resolveTenantId($event, $payload);
        }

        return [
            'event_id' => $event['event_id'],
            'event_type' => $event['event_type'],
            'source_system' => $event['source_system'],
            'tenant_id' => $tenantId,
            'trace_id' => $event['trace_id'],
            'correlation_id' => $event['correlation_id'],
            'payload' => $payload,
            'metadata' => $metadata,
            'status' => $event['status'],
            'created_at' => $event['created_at']
        ];
    }

    /**
     * Tenta resolver tenant_id a partir do evento
     * 
     * @param array $event Evento bruto
     * @param array $payload Payload do evento
     * @return int|null ID do tenant ou null
     */
    private static function resolveTenantId(array $event, array $payload): ?int
    {
        $sourceSystem = $event['source_system'];
        $eventType = $event['event_type'];

        // WhatsApp: tenta pelo channel_id
        if ($sourceSystem === 'wpp_gateway' && isset($payload['channel'])) {
            return self::resolveTenantByChannel($payload['channel']);
        }

        // Asaas: tenta pelo external_reference ou customer_id
        if ($sourceSystem === 'asaas' && isset($payload['payment'])) {
            $payment = $payload['payment'];
            $externalRef = $payment['externalReference'] ?? null;
            
            if ($externalRef && preg_match('/tenant:(\d+)/', $externalRef, $matches)) {
                return (int) $matches[1];
            }
        }

        // Billing: tenta pelo invoice_id
        if ($sourceSystem === 'billing' && isset($payload['invoice_id'])) {
            return self::resolveTenantByInvoice((int) $payload['invoice_id']);
        }

        return null;
    }

    /**
     * Resolve tenant_id pelo channel_id
     * 
     * @param string $channelId ID do channel
     * @return int|null ID do tenant ou null
     */
    private static function resolveTenantByChannel(string $channelId): ?int
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tenant_id 
            FROM tenant_message_channels 
            WHERE provider = 'wpp_gateway' 
            AND channel_id = ? 
            AND is_enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$channelId]);
        $result = $stmt->fetch();
        return $result ? (int) $result['tenant_id'] : null;
    }

    /**
     * Resolve tenant_id pelo invoice_id
     * 
     * @param int $invoiceId ID da fatura
     * @return int|null ID do tenant ou null
     */
    private static function resolveTenantByInvoice(int $invoiceId): ?int
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT tenant_id 
            FROM billing_invoices 
            WHERE id = ? 
            LIMIT 1
        ");
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch();
        return $result ? (int) $result['tenant_id'] : null;
    }
}

