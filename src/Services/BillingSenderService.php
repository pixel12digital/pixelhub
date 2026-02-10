<?php

namespace PixelHub\Services;

use PixelHub\Core\DB;
use PixelHub\Integrations\WhatsAppGateway\WhatsAppGatewayClient;

/**
 * Serviço centralizado de envio de cobranças via Inbox (WhatsApp/Email)
 * 
 * Ponto único de envio — tanto a tela do Cliente quanto a Central Financeira
 * chamam este serviço. O scheduler automático também usa este mesmo fluxo.
 * 
 * Fluxo obrigatório:
 * 1. Sync Asaas (se necessário)
 * 2. Revalidar elegibilidade das faturas
 * 3. Normalizar telefone
 * 4. Montar mensagem (reutiliza WhatsAppBillingService)
 * 5. Enviar via gateway (sessão pixel12digital)
 * 6. Registrar resultado em billing_notifications
 * 
 * SEGURANÇA: em ambiente de teste, apenas tenants com is_billing_test=1 recebem envios.
 */
class BillingSenderService
{
    /** Sessão WhatsApp fixa para cobranças */
    public const WHATSAPP_SESSION = 'pixel12digital';

    /** Canais suportados */
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_BOTH = 'both';

    /**
     * Envia cobrança para um tenant via Inbox
     * 
     * @param array $params {
     *   tenant_id: int (obrigatório),
     *   invoice_ids: int[]|null (null = todas pendentes/vencidas),
     *   channel: string ('whatsapp'|'email'|'both', default respeita tenant config),
     *   triggered_by: string ('manual'|'scheduler'),
     *   dispatch_rule_id: int|null,
     *   message_override: string|null (mensagem personalizada, se null usa template),
     *   skip_asaas_sync: bool (default false — NUNCA pular em produção),
     *   force_test_bypass: bool (default false — para debug apenas),
     * }
     * @return array {
     *   success: bool,
     *   channel: string,
     *   invoices_count: int,
     *   notification_ids: int[],
     *   gateway_message_id: string|null,
     *   error: string|null,
     *   errors: array (detalhes de múltiplas falhas),
     * }
     */
    public static function send(array $params): array
    {
        $db = DB::getConnection();
        $tenantId = (int) ($params['tenant_id'] ?? 0);
        $triggeredBy = $params['triggered_by'] ?? 'manual';
        $dispatchRuleId = $params['dispatch_rule_id'] ?? null;
        $skipSync = (bool) ($params['skip_asaas_sync'] ?? false);
        $messageOverride = $params['message_override'] ?? null;

        $result = [
            'success' => false,
            'channel' => null,
            'invoices_count' => 0,
            'notification_ids' => [],
            'gateway_message_id' => null,
            'error' => null,
            'errors' => [],
        ];

        // ─── 0. Validação básica ────────────────────────────────
        if ($tenantId <= 0) {
            $result['error'] = 'tenant_id inválido';
            self::logDispatch('ERROR', $result['error'], ['tenant_id' => $tenantId]);
            return $result;
        }

        // ─── 1. Busca tenant ────────────────────────────────────
        $tenant = self::getTenant($db, $tenantId);
        if (!$tenant) {
            $result['error'] = 'Tenant não encontrado';
            self::logDispatch('ERROR', $result['error'], ['tenant_id' => $tenantId]);
            return $result;
        }

        // ─── 2. Guarda de teste: apenas is_billing_test=1 ──────
        if (!self::isTestSafe($tenant, $params)) {
            $result['error'] = 'Envio bloqueado: tenant não é de teste (is_billing_test=0). Durante fase de testes, apenas Charles Dietrich recebe envios.';
            self::logDispatch('BLOCKED', $result['error'], ['tenant_id' => $tenantId, 'name' => $tenant['name']]);
            return $result;
        }

        // ─── 3. Determina canal ─────────────────────────────────
        $channel = $params['channel'] ?? $tenant['billing_auto_channel'] ?? self::CHANNEL_WHATSAPP;
        $result['channel'] = $channel;

        // ─── 4. Sync Asaas (obrigatório antes de envio) ────────
        if (!$skipSync) {
            $syncResult = self::syncAsaas($tenantId);
            if (!$syncResult['success']) {
                $result['error'] = 'Falha na sincronização Asaas: ' . $syncResult['error'];
                self::logDispatch('SYNC_FAIL', $result['error'], ['tenant_id' => $tenantId]);
                self::recordFailedNotification($db, $tenantId, null, $channel, $triggeredBy, $dispatchRuleId, $result['error']);
                return $result;
            }
        }

        // ─── 5. Busca faturas elegíveis ─────────────────────────
        $invoiceIds = $params['invoice_ids'] ?? null;
        $invoices = self::getEligibleInvoices($db, $tenantId, $invoiceIds);
        
        if (empty($invoices)) {
            $result['error'] = 'Nenhuma fatura elegível (pending/overdue) encontrada após sync';
            self::logDispatch('NO_INVOICES', $result['error'], ['tenant_id' => $tenantId]);
            return $result;
        }
        $result['invoices_count'] = count($invoices);

        // ─── 6. Envia por canal ─────────────────────────────────
        if ($channel === self::CHANNEL_WHATSAPP || $channel === self::CHANNEL_BOTH) {
            $waResult = self::sendWhatsApp($db, $tenant, $invoices, $triggeredBy, $dispatchRuleId, $messageOverride);
            $result['notification_ids'] = array_merge($result['notification_ids'], $waResult['notification_ids']);
            $result['gateway_message_id'] = $waResult['gateway_message_id'];
            if (!$waResult['success']) {
                $result['errors'][] = ['channel' => 'whatsapp', 'error' => $waResult['error']];
            }
        }

        if ($channel === self::CHANNEL_EMAIL || $channel === self::CHANNEL_BOTH) {
            // Email: placeholder para implementação futura
            $result['errors'][] = ['channel' => 'email', 'error' => 'Canal email ainda não implementado'];
            self::logDispatch('EMAIL_SKIP', 'Canal email ainda não implementado', ['tenant_id' => $tenantId]);
        }

        // ─── 7. Resultado final ─────────────────────────────────
        $hasWhatsAppSuccess = ($channel === self::CHANNEL_WHATSAPP || $channel === self::CHANNEL_BOTH)
            && isset($waResult) && $waResult['success'];

        $result['success'] = $hasWhatsAppSuccess;

        if (!$result['success'] && !empty($result['errors'])) {
            $result['error'] = implode('; ', array_column($result['errors'], 'error'));
        }

        return $result;
    }

    /**
     * Envia cobrança via WhatsApp Inbox (gateway)
     */
    private static function sendWhatsApp(
        \PDO $db,
        array $tenant,
        array $invoices,
        string $triggeredBy,
        ?int $dispatchRuleId,
        ?string $messageOverride
    ): array {
        $tenantId = (int) $tenant['id'];
        $result = [
            'success' => false,
            'notification_ids' => [],
            'gateway_message_id' => null,
            'error' => null,
        ];

        // ─── Normaliza telefone ─────────────────────────────────
        $phoneRaw = $tenant['phone'] ?? null;
        $phoneNormalized = WhatsAppBillingService::normalizePhone($phoneRaw);

        if (!$phoneNormalized) {
            $result['error'] = 'Telefone inválido ou ausente: ' . ($phoneRaw ?: 'NULL');
            self::logDispatch('PHONE_INVALID', $result['error'], ['tenant_id' => $tenantId]);
            self::recordFailedNotification($db, $tenantId, null, 'whatsapp_inbox', $triggeredBy, $dispatchRuleId, $result['error']);
            return $result;
        }

        // ─── Monta mensagem ─────────────────────────────────────
        $message = $messageOverride ?? WhatsAppBillingService::buildReminderMessageForTenant($tenant, $invoices);

        if (empty(trim($message))) {
            $result['error'] = 'Mensagem vazia após construção do template';
            self::logDispatch('MSG_EMPTY', $result['error'], ['tenant_id' => $tenantId]);
            return $result;
        }

        // ─── Envia via gateway ──────────────────────────────────
        try {
            $gateway = new WhatsAppGatewayClient();
            $gatewayResponse = $gateway->sendText(self::WHATSAPP_SESSION, $phoneNormalized, $message, [
                'source' => 'billing_auto',
                'tenant_id' => $tenantId,
                'triggered_by' => $triggeredBy,
            ]);
        } catch (\Exception $e) {
            $result['error'] = 'Erro ao conectar com gateway: ' . $e->getMessage();
            self::logDispatch('GATEWAY_ERROR', $result['error'], ['tenant_id' => $tenantId]);
            self::recordFailedNotification($db, $tenantId, null, 'whatsapp_inbox', $triggeredBy, $dispatchRuleId, $result['error']);
            return $result;
        }

        if (!($gatewayResponse['success'] ?? false)) {
            $result['error'] = 'Gateway retornou erro: ' . ($gatewayResponse['error'] ?? json_encode($gatewayResponse));
            self::logDispatch('GATEWAY_FAIL', $result['error'], ['tenant_id' => $tenantId, 'response' => $gatewayResponse]);
            self::recordFailedNotification($db, $tenantId, null, 'whatsapp_inbox', $triggeredBy, $dispatchRuleId, $result['error']);
            return $result;
        }

        $gatewayMessageId = $gatewayResponse['message_id'] ?? null;
        $result['gateway_message_id'] = $gatewayMessageId;

        // ─── Registra notificações + atualiza faturas ───────────
        try {
            $db->beginTransaction();

            foreach ($invoices as $invoice) {
                $invoiceId = (int) $invoice['id'];

                // Insere billing_notification
                $stmt = $db->prepare("
                    INSERT INTO billing_notifications
                    (tenant_id, invoice_id, channel, template, status, triggered_by, dispatch_rule_id, gateway_message_id, message, phone_raw, phone_normalized, sent_at, created_at, updated_at)
                    VALUES (?, ?, 'whatsapp_inbox', 'reminder', 'sent', ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                ");
                $stmt->execute([
                    $tenantId,
                    $invoiceId,
                    $triggeredBy,
                    $dispatchRuleId,
                    $gatewayMessageId,
                    $message,
                    $phoneRaw,
                    $phoneNormalized,
                ]);
                $result['notification_ids'][] = (int) $db->lastInsertId();

                // Atualiza fatura
                $stmt = $db->prepare("
                    UPDATE billing_invoices
                    SET whatsapp_last_at = NOW(),
                        whatsapp_total_messages = COALESCE(whatsapp_total_messages, 0) + 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$invoiceId]);
            }

            $db->commit();
            $result['success'] = true;

            self::logDispatch('SENT', 'Cobrança enviada via WhatsApp Inbox', [
                'tenant_id' => $tenantId,
                'invoices' => count($invoices),
                'phone' => $phoneNormalized,
                'gateway_message_id' => $gatewayMessageId,
                'triggered_by' => $triggeredBy,
            ]);

            // ─── Ingere evento no sistema de comunicação ────────
            try {
                EventIngestionService::ingest([
                    'event_type' => 'whatsapp.outbound.message',
                    'source_system' => 'pixelhub_billing',
                    'tenant_id' => $tenantId,
                    'payload' => [
                        'id' => $gatewayMessageId ?? ('billing_' . uniqid()),
                        'from' => self::WHATSAPP_SESSION,
                        'to' => $phoneNormalized,
                        'body' => $message,
                        'type' => 'chat',
                        'timestamp' => time(),
                        'channelId' => self::WHATSAPP_SESSION,
                    ],
                    'metadata' => [
                        'billing_auto' => true,
                        'triggered_by' => $triggeredBy,
                        'invoice_count' => count($invoices),
                    ],
                ]);
            } catch (\Exception $e) {
                // Não bloqueia o envio se o registro de evento falhar
                error_log('[BILLING_DISPATCH] Aviso: falha ao ingerir evento: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $result['error'] = 'Erro ao registrar notificações: ' . $e->getMessage();
            self::logDispatch('DB_ERROR', $result['error'], ['tenant_id' => $tenantId]);
            // Mensagem já foi enviada pelo gateway — registra como "sent" com erro de persistência
            $result['success'] = true; // Gateway enviou, apenas o registro local falhou
        }

        return $result;
    }

    /**
     * Sincroniza faturas do tenant com Asaas antes do envio
     */
    private static function syncAsaas(int $tenantId): array
    {
        try {
            $syncStats = AsaasBillingService::syncInvoicesForTenant($tenantId);
            self::logDispatch('SYNC_OK', 'Sync Asaas concluída', [
                'tenant_id' => $tenantId,
                'created' => $syncStats['created'] ?? 0,
                'updated' => $syncStats['updated'] ?? 0,
            ]);
            return ['success' => true, 'stats' => $syncStats, 'error' => null];
        } catch (\Exception $e) {
            return ['success' => false, 'stats' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Busca faturas elegíveis para cobrança
     */
    private static function getEligibleInvoices(\PDO $db, int $tenantId, ?array $invoiceIds = null): array
    {
        $sql = "
            SELECT * FROM billing_invoices
            WHERE tenant_id = ?
              AND status IN ('pending', 'overdue')
              AND (is_deleted IS NULL OR is_deleted = 0)
        ";
        $params = [$tenantId];

        if (!empty($invoiceIds)) {
            $placeholders = implode(',', array_fill(0, count($invoiceIds), '?'));
            $sql .= " AND id IN ({$placeholders})";
            $params = array_merge($params, $invoiceIds);
        }

        $sql .= " ORDER BY due_date ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Busca tenant do banco
     */
    private static function getTenant(\PDO $db, int $tenantId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $tenant ?: null;
    }

    /**
     * Guarda de segurança: durante fase de testes, só permite envio para tenants de teste
     */
    private static function isTestSafe(array $tenant, array $params): bool
    {
        // Se force_test_bypass está ativo (debug), permite
        if (!empty($params['force_test_bypass'])) {
            return true;
        }

        // REGRA: apenas tenants com is_billing_test=1 recebem envios
        return (int) ($tenant['is_billing_test'] ?? 0) === 1;
    }

    /**
     * Registra uma notificação com status 'failed'
     */
    private static function recordFailedNotification(
        \PDO $db,
        int $tenantId,
        ?int $invoiceId,
        string $channel,
        string $triggeredBy,
        ?int $dispatchRuleId,
        string $error
    ): void {
        try {
            $stmt = $db->prepare("
                INSERT INTO billing_notifications
                (tenant_id, invoice_id, channel, template, status, triggered_by, dispatch_rule_id, last_error, created_at, updated_at)
                VALUES (?, ?, ?, 'reminder', 'failed', ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$tenantId, $invoiceId, $channel, $triggeredBy, $dispatchRuleId, $error]);
        } catch (\Exception $e) {
            error_log('[BILLING_DISPATCH] Falha ao registrar notificação de erro: ' . $e->getMessage());
        }
    }

    /**
     * Log público para uso externo (scheduler, worker)
     */
    public static function logDispatchPublic(string $level, string $message, array $context = []): void
    {
        self::logDispatch($level, $message, $context);
    }

    /**
     * Log estruturado para billing dispatch
     */
    private static function logDispatch(string $level, string $message, array $context = []): void
    {
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[BILLING_DISPATCH] [{$level}] {$message}{$contextStr}";
        error_log($logLine);

        // Log em arquivo dedicado
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/billing_dispatch.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[{$timestamp}] [{$level}] {$message}{$contextStr}\n", FILE_APPEND);
    }

    /**
     * Verifica se uma fatura já teve notificação enviada para um determinado estágio/regra recentemente
     * Usado pelo scheduler para anti-spam
     * 
     * @param \PDO $db
     * @param int $invoiceId
     * @param int $dispatchRuleId
     * @param int $cooldownHours Horas mínimas entre envios da mesma regra (default 20h)
     * @return bool true se já foi enviado recentemente
     */
    public static function wasRecentlySent(\PDO $db, int $invoiceId, int $dispatchRuleId, int $cooldownHours = 20): bool
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM billing_notifications
            WHERE invoice_id = ?
              AND dispatch_rule_id = ?
              AND status = 'sent'
              AND sent_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$invoiceId, $dispatchRuleId, $cooldownHours]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Conta quantas vezes uma regra já disparou para uma fatura
     */
    public static function countSentForRule(\PDO $db, int $invoiceId, int $dispatchRuleId): int
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM billing_notifications
            WHERE invoice_id = ?
              AND dispatch_rule_id = ?
              AND status = 'sent'
        ");
        $stmt->execute([$invoiceId, $dispatchRuleId]);
        return (int) $stmt->fetchColumn();
    }
}
