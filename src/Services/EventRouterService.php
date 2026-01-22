<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;
use PixelHub\Services\WhatsAppBillingService;

/**
 * Service para rotear eventos para canais apropriados
 */
class EventRouterService
{
    /**
     * Roteia um evento normalizado
     * 
     * @param array $normalizedEvent Evento normalizado
     * @return array Resultado do roteamento { success: bool, action: string, error?: string }
     */
    public static function route(array $normalizedEvent): array
    {
        $eventType = $normalizedEvent['event_type'];
        $sourceSystem = $normalizedEvent['source_system'];

        // Busca regras de roteamento
        $rules = self::findMatchingRules($eventType, $sourceSystem);
        
        if (empty($rules)) {
            // Sem regra, apenas loga
            if (function_exists('pixelhub_log')) {
                pixelhub_log(sprintf(
                    '[EventRouter] Nenhuma regra encontrada para %s (source: %s)',
                    $eventType,
                    $sourceSystem
                ));
            }
            return ['success' => true, 'action' => 'none', 'message' => 'No routing rule found'];
        }

        // Usa a regra com maior prioridade (menor número)
        $rule = $rules[0];
        $channel = $rule['channel'];

        // Atualiza status do evento para processing
        EventIngestionService::updateStatus($normalizedEvent['event_id'], 'processing');

        try {
            // Roteia baseado no canal (match() requer PHP 8.0+)
            switch ($channel) {
                case 'whatsapp':
                    $result = self::routeToWhatsApp($normalizedEvent, $rule);
                    break;
                case 'chat':
                    $result = self::routeToChat($normalizedEvent, $rule);
                    break;
                case 'email':
                    $result = self::routeToEmail($normalizedEvent, $rule);
                    break;
                case 'none':
                    $result = ['success' => true, 'action' => 'none'];
                    break;
                default:
                    $result = ['success' => false, 'error' => "Canal desconhecido: {$channel}"];
                    break;
            }

            // Atualiza status
            if ($result['success']) {
                EventIngestionService::updateStatus($normalizedEvent['event_id'], 'processed');
            } else {
                EventIngestionService::updateStatus(
                    $normalizedEvent['event_id'], 
                    'failed', 
                    $result['error'] ?? 'Erro desconhecido'
                );
            }

            return $result;
        } catch (\Exception $e) {
            error_log("[EventRouter] Erro ao rotear evento: " . $e->getMessage());
            EventIngestionService::updateStatus(
                $normalizedEvent['event_id'], 
                'failed', 
                $e->getMessage()
            );
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Busca regras de roteamento que correspondem ao evento
     * 
     * @param string $eventType Tipo do evento
     * @param string $sourceSystem Sistema de origem
     * @return array Regras ordenadas por prioridade
     */
    private static function findMatchingRules(string $eventType, string $sourceSystem): array
    {
        $db = DB::getConnection();

        // Busca regras exatas e com wildcard
        $stmt = $db->prepare("
            SELECT * FROM routing_rules 
            WHERE is_enabled = 1
            AND (
                (event_type = ? AND (source_system IS NULL OR source_system = ?))
                OR (event_type LIKE ? AND (source_system IS NULL OR source_system = ?))
            )
            ORDER BY priority ASC, id ASC
        ");

        // Converte event_type para padrão wildcard (ex: billing.invoice.overdue -> billing.invoice.%)
        $wildcardType = preg_replace('/\.[^.]+$/', '.%', $eventType);
        if ($wildcardType === $eventType) {
            $wildcardType = $eventType . '.%';
        }

        $stmt->execute([$eventType, $sourceSystem, $wildcardType, $sourceSystem]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Roteia evento para WhatsApp
     * 
     * @param array $normalizedEvent Evento normalizado
     * @param array $rule Regra de roteamento
     * @return array Resultado
     */
    private static function routeToWhatsApp(array $normalizedEvent, array $rule): array
    {
        $tenantId = $normalizedEvent['tenant_id'];
        if (empty($tenantId)) {
            return ['success' => false, 'error' => 'tenant_id não encontrado'];
        }

        // Busca channel do tenant
        $channel = self::getTenantChannel($tenantId);
        if (empty($channel)) {
            return ['success' => false, 'error' => 'Channel WhatsApp não configurado para tenant'];
        }

        // Extrai dados do payload
        $payload = $normalizedEvent['payload'];
        $to = $payload['to'] ?? $payload['phone'] ?? $payload['phone_normalized'] ?? null;
        $text = $payload['text'] ?? $payload['message'] ?? null;

        if (empty($to) || empty($text)) {
            return ['success' => false, 'error' => 'to e text são obrigatórios para WhatsApp'];
        }

        // Normaliza telefone
        $toNormalized = WhatsAppBillingService::normalizePhone($to);
        if (empty($toNormalized)) {
            return ['success' => false, 'error' => 'Telefone inválido'];
        }

        // Envia via gateway
        $gateway = new WhatsAppGatewayClient();
        $result = $gateway->sendText($channel['channel_id'], $toNormalized, $text, [
            'event_id' => $normalizedEvent['event_id'],
            'trace_id' => $normalizedEvent['trace_id'],
            'template' => $rule['template'] ?? null
        ]);

        if ($result['success']) {
            // Registra em billing_notifications se for evento de cobrança
            if (strpos($normalizedEvent['event_type'], 'billing.') === 0) {
                self::registerBillingNotification($tenantId, $toNormalized, $text, $normalizedEvent);
            }
        }

        return $result;
    }

    /**
     * Roteia evento para chat interno
     * 
     * @param array $normalizedEvent Evento normalizado
     * @param array $rule Regra de roteamento
     * @return array Resultado
     */
    private static function routeToChat(array $normalizedEvent, array $rule): array
    {
        // Para mensagens WhatsApp inbound, cria thread de chat se necessário
        if ($normalizedEvent['event_type'] === 'whatsapp.inbound.message') {
            $payload = $normalizedEvent['payload'];
            $tenantId = $normalizedEvent['tenant_id'];
            
            // TODO: Criar thread de chat genérico (não vinculado a order)
            // Por enquanto, apenas loga
            if (function_exists('pixelhub_log')) {
                pixelhub_log(sprintf(
                    '[EventRouter] Mensagem WhatsApp inbound recebida (tenant_id: %s, trace_id: %s)',
                    $tenantId ?: 'NULL',
                    $normalizedEvent['trace_id']
                ));
            }
        }

        return ['success' => true, 'action' => 'chat'];
    }

    /**
     * Roteia evento para e-mail
     * 
     * @param array $normalizedEvent Evento normalizado
     * @param array $rule Regra de roteamento
     * @return array Resultado
     */
    private static function routeToEmail(array $normalizedEvent, array $rule): array
    {
        // TODO: Implementar envio de e-mail
        return ['success' => false, 'error' => 'Email routing não implementado'];
    }

    /**
     * Busca channel do tenant
     * 
     * @param int $tenantId ID do tenant
     * @return array|null Channel ou null
     */
    private static function getTenantChannel(int $tenantId): ?array
    {
        $db = DB::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM tenant_message_channels 
            WHERE tenant_id = ? 
            AND provider = 'wpp_gateway' 
            AND is_enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Registra notificação de cobrança
     * 
     * @param int $tenantId ID do tenant
     * @param string $phone Telefone normalizado
     * @param string $message Mensagem
     * @param array $normalizedEvent Evento normalizado
     * @return void
     */
    private static function registerBillingNotification(int $tenantId, string $phone, string $message, array $normalizedEvent): void
    {
        $db = DB::getConnection();
        $payload = $normalizedEvent['payload'];
        $invoiceId = $payload['invoice_id'] ?? null;

        $stmt = $db->prepare("
            INSERT INTO billing_notifications
            (tenant_id, invoice_id, channel, template, status, message, phone_normalized, sent_at, created_at, updated_at)
            VALUES (?, ?, 'whatsapp_gateway', ?, 'sent_auto', ?, ?, NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            $invoiceId,
            $normalizedEvent['event_type'],
            $message,
            $phone
        ]);
    }
}

