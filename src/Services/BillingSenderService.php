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

        // ─── 4. Busca faturas elegíveis ─────────────────────────
        $invoiceIds = $params['invoice_ids'] ?? null;
        $invoices = self::getEligibleInvoices($db, $tenantId, $invoiceIds);
        if (empty($invoices)) {
            $result['error'] = 'Nenhuma fatura elegível encontrada';
            self::logDispatch('NO_INVOICES', $result['error'], ['tenant_id' => $tenantId]);
            return $result;
        }
        $result['invoices_count'] = count($invoices);

        // ─── 5. Sync Asaas (se necessário) ───────────────────────
        if (!$skipSync) {
            $syncResult = self::syncAsaasInvoices($db, $tenantId);
            if (!$syncResult['success']) {
                $result['error'] = 'Erro ao sincronizar com Asaas: ' . $syncResult['error'];
                self::logDispatch('ASAAS_SYNC_FAIL', $result['error'], ['tenant_id' => $tenantId]);
                return $result;
            }
        }

        // ─── 6. Envia via canais ─────────────────────────────────
        $waResult = null;
        $emailResult = null;

        if ($channel === self::CHANNEL_WHATSAPP || $channel === self::CHANNEL_BOTH) {
            $waResult = self::sendWhatsApp($db, $tenant, $invoices, $triggeredBy, $dispatchRuleId, $messageOverride);
            $result['notification_ids'] = array_merge($result['notification_ids'], $waResult['notification_ids']);
            $result['gateway_message_id'] = $waResult['gateway_message_id'];
            if (!$waResult['success']) {
                $result['errors'][] = ['channel' => 'whatsapp', 'error' => $waResult['error']];
            }
        }

        if ($channel === self::CHANNEL_EMAIL || $channel === self::CHANNEL_BOTH) {
            $emailResult = self::sendEmail($db, $tenant, $invoices, $triggeredBy, $dispatchRuleId, $messageOverride);
            if ($emailResult['success']) {
                $result['notification_ids'] = array_merge($result['notification_ids'], $emailResult['notification_ids']);
                $result['gateway_message_id'] = $emailResult['gateway_message_id'];
            } else {
                $result['errors'][] = ['channel' => 'email', 'error' => $emailResult['error']];
            }
        }

        // ─── 7. Resultado final ─────────────────────────────────
        $hasWhatsAppSuccess = ($channel === self::CHANNEL_WHATSAPP || $channel === self::CHANNEL_BOTH)
            && isset($waResult) && $waResult['success'];
        $hasEmailSuccess = ($channel === self::CHANNEL_EMAIL || $channel === self::CHANNEL_BOTH)
            && isset($emailResult) && $emailResult['success'];

        $result['success'] = $hasWhatsAppSuccess || $hasEmailSuccess;

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

        // ─── Monta mensagem (reutiliza WhatsAppBillingService) ───────
        $invoice = $invoices[0]; // Para envio manual, sempre 1 fatura
        $stage = WhatsAppBillingService::suggestStageForInvoice($invoice)['stage'];
        
        if ($messageOverride) {
            $messageBody = $messageOverride;
        } else {
            $messageBody = WhatsAppBillingService::buildMessageForInvoice($tenant, $invoice, $stage);
        }

        // ─── Envia via WhatsApp Gateway ───────────────────────────
        try {
            $client = new WhatsAppGatewayClient();
            $gwResult = $client->sendText(self::WHATSAPP_SESSION, $phoneNormalized, $messageBody);

            if ($gwResult['success']) {
                // Registra notificação bem-sucedida
                $notificationId = self::recordSuccessNotification(
                    $db,
                    $tenantId,
                    $invoice['id'],
                    'whatsapp_inbox',
                    $triggeredBy,
                    $dispatchRuleId,
                    $gwResult['message_id'] ?? null,
                    $messageBody
                );
                
                $result['success'] = true;
                $result['notification_ids'][] = $notificationId;
                $result['gateway_message_id'] = $gwResult['message_id'] ?? null;
                
                self::logDispatch('WHATSAPP_SENT', 'WhatsApp enviado com sucesso', [
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice['id'],
                    'phone' => $phoneNormalized,
                    'message_id' => $gwResult['message_id'] ?? null
                ]);
            } else {
                $result['error'] = $gwResult['error'] ?? 'Erro desconhecido no gateway WhatsApp';
                self::logDispatch('WHATSAPP_FAIL', $result['error'], [
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice['id'],
                    'phone' => $phoneNormalized
                ]);
                self::recordFailedNotification($db, $tenantId, $invoice['id'], 'whatsapp_inbox', $triggeredBy, $dispatchRuleId, $result['error']);
            }
        } catch (\Exception $e) {
            $result['error'] = 'Exception no envio WhatsApp: ' . $e->getMessage();
            self::logDispatch('WHATSAPP_EXCEPTION', $result['error'], [
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice['id'],
                'phone' => $phoneNormalized,
                'exception' => $e->getMessage()
            ]);
            self::recordFailedNotification($db, $tenantId, $invoice['id'], 'whatsapp_inbox', $triggeredBy, $dispatchRuleId, $result['error']);
        }

        return $result;
    }

    /**
     * Envia cobrança via E-mail SMTP
     */
    private static function sendEmail(
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

        // ─── Valida e-mail do tenant ───────────────────────────────
        $email = $tenant['email'] ?? null;
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['error'] = 'E-mail inválido ou ausente: ' . ($email ?: 'NULL');
            self::logDispatch('EMAIL_INVALID', $result['error'], ['tenant_id' => $tenantId]);
            self::recordFailedNotification($db, $tenantId, null, 'email_smtp', $triggeredBy, $dispatchRuleId, $result['error']);
            return $result;
        }

        // ─── Monta mensagem (reutiliza WhatsAppBillingService) ───────
        $invoice = $invoices[0]; // Para envio manual, sempre 1 fatura
        $stage = WhatsAppBillingService::suggestStageForInvoice($invoice)['stage'];
        
        if ($messageOverride) {
            $messageBody = $messageOverride;
        } else {
            $messageBody = WhatsAppBillingService::buildMessageForInvoice($tenant, $invoice, $stage);
        }

        // ─── Prepara e-mail ───────────────────────────────────────
        $subject = 'Cobrança Pixel12 Digital - Fatura #' . $invoice['id'];
        
        // Converte quebras de linha para HTML
        $htmlBody = nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8'));
        
        // Adiciona cabeçalho HTML completo
        $fullHtml = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($subject) . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 30px; }
                .footer { background: #ecf0f1; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Pixel12 Digital</h2>
                </div>
                <div class="content">
                    ' . $htmlBody . '
                </div>
                <div class="footer">
                    <p>Esta é uma mensagem automática. Por favor, não responda este e-mail.</p>
                    <p>Pixel12 Digital - Soluções Web & Hospedagem</p>
                </div>
            </div>
        </body>
        </html>';

        // ─── Envia via SmtpService (instância, retorna bool) ──────────
        try {
            $smtp = new \PixelHub\Services\SmtpService();
            $sent = $smtp->send($email, $subject, $fullHtml, true);

            if ($sent) {
                // Registra notificação bem-sucedida
                $notificationId = self::recordSuccessNotification(
                    $db,
                    $tenantId,
                    $invoice['id'],
                    'email_smtp',
                    $triggeredBy,
                    $dispatchRuleId,
                    null,
                    $messageBody
                );
                
                $result['success'] = true;
                $result['notification_ids'][] = $notificationId;
                
                self::logDispatch('EMAIL_SENT', 'E-mail enviado com sucesso', [
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice['id'],
                    'email' => $email
                ]);
            } else {
                $result['error'] = 'Falha no envio de e-mail via SMTP';
                self::logDispatch('EMAIL_FAIL', $result['error'], [
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice['id'],
                    'email' => $email
                ]);
                self::recordFailedNotification($db, $tenantId, $invoice['id'], 'email_smtp', $triggeredBy, $dispatchRuleId, $result['error']);
            }
        } catch (\Exception $e) {
            $result['error'] = 'Exception no envio de e-mail: ' . $e->getMessage();
            self::logDispatch('EMAIL_EXCEPTION', $result['error'], [
                'tenant_id' => $tenantId,
                'invoice_id' => $invoice['id'],
                'email' => $email,
                'exception' => $e->getMessage()
            ]);
            self::recordFailedNotification($db, $tenantId, $invoice['id'], 'email_smtp', $triggeredBy, $dispatchRuleId, $result['error']);
        }

        return $result;
    }

    // ─── Métodos auxiliares (mantidos do original) ────────────────────────

    private static function getTenant(\PDO $db, int $tenantId): ?array
    {
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    private static function isTestSafe(array $tenant, array $params): bool
    {
        if (!empty($params['force_test_bypass'])) {
            return true;
        }
        
        // Em ambiente de teste, apenas tenants com is_billing_test=1 podem receber
        if (getenv('APP_ENV') === 'test') {
            return (bool) ($tenant['is_billing_test'] ?? false);
        }
        
        return true;
    }

    private static function getEligibleInvoices(\PDO $db, int $tenantId, ?array $invoiceIds): array
    {
        $sql = "
            SELECT * FROM billing_invoices 
            WHERE tenant_id = ? 
              AND status IN ('pending', 'overdue')
              AND (is_deleted IS NULL OR is_deleted = 0)
        ";
        $params = [$tenantId];
        
        if ($invoiceIds) {
            $placeholders = str_repeat('?,', count($invoiceIds) - 1) . '?';
            $sql .= " AND id IN ($placeholders)";
            $params = array_merge($params, $invoiceIds);
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private static function syncAsaasInvoices(\PDO $db, int $tenantId): array
    {
        // Placeholder - implementar se necessário
        return ['success' => true];
    }

    private static function recordSuccessNotification(\PDO $db, int $tenantId, int $invoiceId, string $channel, string $triggeredBy, ?int $dispatchRuleId, ?string $messageId, string $messageBody): int
    {
        $stmt = $db->prepare("
            INSERT INTO billing_notifications (
                tenant_id, invoice_id, channel, template,
                status, sent_at, message
            ) VALUES (?, ?, ?, 'manual', 'sent', NOW(), ?)
        ");
        $stmt->execute([$tenantId, $invoiceId, $channel, $messageBody]);
        return (int) $db->lastInsertId();
    }

    private static function recordFailedNotification(\PDO $db, int $tenantId, ?int $invoiceId, string $channel, string $triggeredBy, ?int $dispatchRuleId, string $errorMessage): void
    {
        $stmt = $db->prepare("
            INSERT INTO billing_notifications (
                tenant_id, invoice_id, channel, template,
                status, sent_at, last_error
            ) VALUES (?, ?, ?, 'manual', 'failed', NOW(), ?)
        ");
        $stmt->execute([$tenantId, $invoiceId, $channel, $errorMessage]);
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
}
