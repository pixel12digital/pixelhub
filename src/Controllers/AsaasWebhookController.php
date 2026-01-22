<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\DB;
use PixelHub\Services\AsaasConfig;
use PixelHub\Services\AsaasBillingService;

/**
 * Controller para receber webhooks do Asaas
 */
class AsaasWebhookController extends Controller
{
    /**
     * Processa webhook do Asaas
     * 
     * Valida token, grava log e atualiza faturas.
     */
    public function handle(): void
    {
        // Valida token do webhook
        $tokenHeader = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? $_SERVER['HTTP_X_ASAAS_ACCESS_TOKEN'] ?? null;
        $expectedToken = AsaasConfig::getWebhookToken();

        if (empty($expectedToken) || $tokenHeader !== $expectedToken) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid token'
            ]);
            exit;
        }

        // Lê payload JSON
        $rawPayload = file_get_contents('php://input');
        $payload = json_decode($rawPayload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON payload'
            ]);
            exit;
        }

        $db = DB::getConnection();

        // Grava log do webhook
        $event = $payload['event'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO asaas_webhook_logs (event, payload, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$event, $rawPayload]);

        // Processa payment se existir
        if (isset($payload['payment']) && is_array($payload['payment'])) {
            $this->processPayment($db, $payload['payment']);
        }

        // TODO: Processar subscription quando necessário
        // if (isset($payload['subscription']) && is_array($payload['subscription'])) {
        //     $this->processSubscription($db, $payload['subscription']);
        // }

        // Responde sucesso
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    /**
     * Processa atualização de payment
     * 
     * @param PDO $db Conexão com banco
     * @param array $payment Dados do payment do Asaas
     * @return void
     */
    private function processPayment(PDO $db, array $payment): void
    {
        $paymentId = $payment['id'] ?? null;
        if (empty($paymentId)) {
            return;
        }

        // Busca fatura existente
        $stmt = $db->prepare("SELECT * FROM billing_invoices WHERE asaas_payment_id = ?");
        $stmt->execute([$paymentId]);
        $invoice = $stmt->fetch();

        // Verifica se a cobrança foi deletada no Asaas
        // O campo 'deleted' pode ser boolean true ou string 'true'
        $asaasDeleted = isset($payment['deleted']) && (
            $payment['deleted'] === true || 
            $payment['deleted'] === 'true' || 
            $payment['deleted'] === 1
        );
        
        // Extrai dados do payment
        $asaasStatus = strtoupper($payment['status'] ?? 'PENDING');
        $status = $this->mapAsaasStatusToInternal($asaasStatus);
        
        // Se a cobrança estiver deletada ou com status de cancelamento/reembolso,
        // marca como cancelada e is_deleted = 1
        $isDeleted = 0;
        if ($asaasDeleted || in_array($asaasStatus, ['CANCELED', 'REFUNDED'])) {
            $status = 'canceled';
            $isDeleted = 1;
            
            // Log informativo para auditoria
            error_log("AsaasWebhook: Cobrança {$paymentId} marcada como deletada/cancelada (deleted={$asaasDeleted}, status={$asaasStatus})");
        }
        
        $dueDate = $payment['dueDate'] ?? null;
        $amount = $payment['value'] ?? 0;
        $customerId = $payment['customer'] ?? null;
        $externalRef = $payment['externalReference'] ?? null;
        $invoiceUrl = $payment['invoiceUrl'] ?? null;
        $billingType = $payment['billingType'] ?? null;
        $confirmedDate = $payment['confirmedDate'] ?? null;
        $description = $payment['description'] ?? null;

        // Tenta resolver tenant_id
        $tenantId = null;

        if ($invoice) {
            $tenantId = $invoice['tenant_id'];
        } elseif ($externalRef) {
            // Tenta extrair tenant_id do external_reference
            // Formato esperado: "PIXEL_CONTRACT:ID" ou "tenant:ID"
            if (preg_match('/tenant:(\d+)/', $externalRef, $matches)) {
                $tenantId = (int) $matches[1];
            } elseif (preg_match('/PIXEL_CONTRACT:.*_(\d+)/', $externalRef, $matches)) {
                // Extrai hosting_account_id e busca tenant_id
                $hostingAccountId = (int) $matches[1];
                $stmt = $db->prepare("SELECT tenant_id FROM hosting_accounts WHERE id = ?");
                $stmt->execute([$hostingAccountId]);
                $hostingAccount = $stmt->fetch();
                if ($hostingAccount) {
                    $tenantId = $hostingAccount['tenant_id'];
                }
            }
        }

        if (empty($tenantId)) {
            // Se não conseguir resolver, não cria/atualiza fatura
            error_log("AsaasWebhook: Não foi possível resolver tenant_id para payment {$paymentId}");
            return;
        }

        // Converte dueDate para formato DATE
        $dueDateFormatted = null;
        if ($dueDate) {
            try {
                $date = new \DateTime($dueDate);
                $dueDateFormatted = $date->format('Y-m-d');
            } catch (\Exception $e) {
                error_log("AsaasWebhook: Erro ao converter dueDate: " . $e->getMessage());
            }
        }

        // Converte confirmedDate para DATETIME
        $paidAt = null;
        if ($confirmedDate) {
            try {
                $date = new \DateTime($confirmedDate);
                $paidAt = $date->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                error_log("AsaasWebhook: Erro ao converter confirmedDate: " . $e->getMessage());
            }
        }

        if ($invoice) {
            // Atualiza fatura existente
            $stmt = $db->prepare("
                UPDATE billing_invoices 
                SET status = ?, is_deleted = ?, due_date = ?, amount = ?, 
                    paid_at = ?, invoice_url = ?, billing_type = ?, 
                    description = ?, external_reference = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $status,
                $isDeleted,
                $dueDateFormatted,
                $amount,
                $paidAt,
                $invoiceUrl,
                $billingType,
                $description,
                $externalRef,
                $invoice['id'],
            ]);
        } else {
            // Cria nova fatura
            $stmt = $db->prepare("
                INSERT INTO billing_invoices 
                (tenant_id, asaas_payment_id, asaas_customer_id, due_date, amount, 
                 status, is_deleted, paid_at, invoice_url, billing_type, description, 
                 external_reference, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $tenantId,
                $paymentId,
                $customerId,
                $dueDateFormatted,
                $amount,
                $status,
                $isDeleted,
                $paidAt,
                $invoiceUrl,
                $billingType,
                $description,
                $externalRef,
            ]);
        }

        // Atualiza status financeiro do tenant
        AsaasBillingService::refreshTenantBillingStatus($tenantId);
    }

    /**
     * Mapeia status do Asaas para status interno
     * 
     * @param string $asaasStatus Status do Asaas
     * @return string Status interno
     */
    private function mapAsaasStatusToInternal(string $asaasStatus): string
    {
        $mapping = [
            'PENDING' => 'pending',
            'CONFIRMED' => 'paid',
            'RECEIVED' => 'paid',
            'RECEIVED_IN_CASH' => 'paid',
            'OVERDUE' => 'overdue',
            'CANCELED' => 'canceled',
            'REFUNDED' => 'refunded',
            'RECEIVED_IN_CASH_UNDONE' => 'canceled',
            'CHARGEBACK_REQUESTED' => 'canceled',
            'CHARGEBACK_DISPUTE' => 'canceled',
            'AWAITING_CHARGEBACK_REVERSAL' => 'canceled',
            'DUNNING_REQUESTED' => 'overdue',
            'DUNNING_RECEIVED' => 'paid',
            'AWAITING_RISK_ANALYSIS' => 'pending',
        ];

        $normalized = strtoupper($asaasStatus);
        return $mapping[$normalized] ?? 'pending';
    }
}

