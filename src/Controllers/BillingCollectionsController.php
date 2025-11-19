<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppBillingService;

/**
 * Controller para gerenciar cobranças via WhatsApp Web
 */
class BillingCollectionsController extends Controller
{
    /**
     * Tela principal de cobranças
     */
    public function index(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Filtros
        $statusFilter = $_GET['status'] ?? 'all';
        $whatsappStageFilter = $_GET['whatsapp_stage'] ?? 'all';
        $tenantIdFilter = isset($_GET['tenant_id']) && $_GET['tenant_id'] !== '' ? (int) $_GET['tenant_id'] : null;

        // Monta query base - sempre exclui cobranças deletadas
        $where = ["(bi.is_deleted IS NULL OR bi.is_deleted = 0)"];
        $params = [];

        // Filtro por tenant (cliente)
        if ($tenantIdFilter) {
            $where[] = "bi.tenant_id = ?";
            $params[] = $tenantIdFilter;
        }

        // Filtro de status
        if ($statusFilter === 'all') {
            // Por padrão, mostra apenas cobranças em aberto (pending/overdue)
            $where[] = "bi.status IN ('pending', 'overdue')";
        } elseif ($statusFilter === 'vencendo') {
            $where[] = "bi.due_date >= CURDATE() AND bi.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND bi.status = 'pending'";
        } elseif ($statusFilter === 'vencidas_7d') {
            $where[] = "bi.status = 'overdue' AND DATEDIFF(CURDATE(), bi.due_date) <= 7";
        } elseif ($statusFilter === 'vencidas_mais_7d') {
            $where[] = "bi.status = 'overdue' AND DATEDIFF(CURDATE(), bi.due_date) > 7";
        } elseif ($statusFilter === 'pending') {
            $where[] = "bi.status = 'pending'";
        } elseif ($statusFilter === 'overdue') {
            $where[] = "bi.status = 'overdue'";
        } elseif ($statusFilter === 'paid') {
            $where[] = "bi.status = 'paid'";
        }

        // Filtro de estágio WhatsApp
        if ($whatsappStageFilter !== 'all' && $whatsappStageFilter !== 'none') {
            $where[] = "bi.whatsapp_last_stage = ?";
            $params[] = $whatsappStageFilter;
        } elseif ($whatsappStageFilter === 'none') {
            $where[] = "(bi.whatsapp_last_stage IS NULL OR bi.whatsapp_last_stage = '')";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Busca faturas
        $sql = "
            SELECT 
                bi.*,
                t.id as tenant_id,
                t.name as tenant_name,
                t.person_type,
                t.nome_fantasia,
                t.phone,
                DATEDIFF(CURDATE(), bi.due_date) as days_overdue
            FROM billing_invoices bi
            INNER JOIN tenants t ON bi.tenant_id = t.id
            {$whereClause}
            ORDER BY 
                CASE 
                    WHEN bi.status = 'overdue' THEN 1
                    WHEN bi.status = 'pending' AND bi.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                    ELSE 3
                END,
                bi.due_date ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        // Calcula resumo - apenas cobranças em aberto e não deletadas
        $stmt = $db->query("
            SELECT 
                SUM(CASE WHEN status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0) THEN amount ELSE 0 END) as total_overdue,
                COUNT(DISTINCT CASE WHEN status = 'overdue' AND (is_deleted IS NULL OR is_deleted = 0) THEN tenant_id ELSE NULL END) as clients_overdue,
                COUNT(CASE WHEN status = 'pending' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND (is_deleted IS NULL OR is_deleted = 0) THEN 1 ELSE NULL END) as invoices_due_soon
            FROM billing_invoices
            WHERE (is_deleted IS NULL OR is_deleted = 0)
        ");
        $summary = $stmt->fetch();

        // Busca lista de tenants para o filtro (sempre busca todos para o select)
        $stmt = $db->query("SELECT id, name, nome_fantasia, person_type FROM tenants WHERE status = 'active' ORDER BY name ASC");
        $tenantsList = $stmt->fetchAll();

        $this->view('billing_collections.index', [
            'invoices' => $invoices,
            'summary' => $summary,
            'statusFilter' => $statusFilter,
            'whatsappStageFilter' => $whatsappStageFilter,
            'tenantIdFilter' => $tenantIdFilter,
            'tenantsList' => $tenantsList,
        ]);
    }

    /**
     * Exibe modal/página para cobrança via WhatsApp
     */
    public function showWhatsAppModal(): void
    {
        Auth::requireInternal();

        $invoiceId = $_GET['invoice_id'] ?? null;
        if (!$invoiceId) {
            $this->redirect('/billing/collections?error=missing_invoice_id');
            return;
        }

        $db = DB::getConnection();

        // Busca fatura com tenant
        $stmt = $db->prepare("
            SELECT bi.*, t.*
            FROM billing_invoices bi
            INNER JOIN tenants t ON bi.tenant_id = t.id
            WHERE bi.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            $this->redirect('/billing/collections?error=invoice_not_found');
            return;
        }

        // Prepara dados do tenant
        $tenant = [
            'id' => $invoice['tenant_id'],
            'name' => $invoice['tenant_name'] ?? $invoice['name'],
            'person_type' => $invoice['person_type'] ?? 'pf',
            'nome_fantasia' => $invoice['nome_fantasia'] ?? null,
            'razao_social' => $invoice['razao_social'] ?? null,
            'phone' => $invoice['phone'] ?? null,
        ];

        // Sugere estágio
        $stageInfo = WhatsAppBillingService::suggestStageForInvoice($invoice);

        // Monta mensagem
        $message = WhatsAppBillingService::buildMessageForInvoice($tenant, $invoice, $stageInfo['stage']);

        // Normaliza telefone
        $phoneRaw = $tenant['phone'] ?? null;
        $phoneNormalized = WhatsAppBillingService::normalizePhone($phoneRaw);

        // Prepara link do WhatsApp
        $whatsappLink = null;
        if ($phoneNormalized) {
            $messageEncoded = rawurlencode($message);
            $whatsappLink = "https://wa.me/{$phoneNormalized}?text={$messageEncoded}";
        }

        // Verifica redirect_to
        $redirectTo = $_GET['redirect_to'] ?? 'collections';

        $this->view('billing_collections.whatsapp_modal', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'stageInfo' => $stageInfo,
            'message' => $message,
            'phoneRaw' => $phoneRaw,
            'phoneNormalized' => $phoneNormalized,
            'whatsappLink' => $whatsappLink,
            'redirectTo' => $redirectTo,
        ]);
    }

    /**
     * Marca cobrança como enviada via WhatsApp
     */
    public function markWhatsAppSent(): void
    {
        Auth::requireInternal();

        $invoiceId = $_POST['invoice_id'] ?? null;
        $stage = $_POST['stage'] ?? null;
        $phone = $_POST['phone'] ?? null;
        $message = $_POST['message'] ?? '';
        $redirectTo = $_POST['redirect_to'] ?? 'collections';

        if (!$invoiceId || !$stage) {
            $this->redirect('/billing/collections?error=missing_data');
            return;
        }

        $db = DB::getConnection();

        try {
            $db->beginTransaction();

            // Busca fatura e tenant
            $stmt = $db->prepare("
                SELECT bi.*, t.*
                FROM billing_invoices bi
                INNER JOIN tenants t ON bi.tenant_id = t.id
                WHERE bi.id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                throw new \Exception('Fatura não encontrada');
            }

            $tenant = [
                'id' => $invoice['tenant_id'],
                'name' => $invoice['tenant_name'] ?? $invoice['name'],
                'person_type' => $invoice['person_type'] ?? 'pf',
                'nome_fantasia' => $invoice['nome_fantasia'] ?? null,
                'razao_social' => $invoice['razao_social'] ?? null,
                'phone' => $phone ?: ($invoice['phone'] ?? null),
            ];

            // Normaliza telefone
            $phoneNormalized = WhatsAppBillingService::normalizePhone($phone);

            // Busca ou cria notificação
            $stmt = $db->prepare("
                SELECT id FROM billing_notifications
                WHERE invoice_id = ? AND template = ? AND status = 'prepared'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$invoiceId, $stage]);
            $notification = $stmt->fetch();

            if ($notification) {
                // Atualiza notificação existente
                $stmt = $db->prepare("
                    UPDATE billing_notifications
                    SET status = 'sent_manual',
                        message = ?,
                        phone_raw = ?,
                        phone_normalized = ?,
                        sent_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$message, $phone, $phoneNormalized, $notification['id']]);
                $notificationId = $notification['id'];
            } else {
                // Cria nova notificação
                $stmt = $db->prepare("
                    INSERT INTO billing_notifications
                    (tenant_id, invoice_id, channel, template, status, message, phone_raw, phone_normalized, sent_at, created_at, updated_at)
                    VALUES (?, ?, 'whatsapp_web', ?, 'sent_manual', ?, ?, ?, NOW(), NOW(), NOW())
                ");
                $stmt->execute([
                    $tenant['id'],
                    $invoiceId,
                    $stage,
                    $message,
                    $phone,
                    $phoneNormalized
                ]);
                $notificationId = (int) $db->lastInsertId();
            }

            // Atualiza fatura
            $stmt = $db->prepare("
                UPDATE billing_invoices
                SET whatsapp_last_stage = ?,
                    whatsapp_last_at = NOW(),
                    whatsapp_total_messages = whatsapp_total_messages + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$stage, $invoiceId]);

            $db->commit();

            // Redireciona
            if ($redirectTo === 'tenant') {
                $tenantId = $invoice['tenant_id'];
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&success=whatsapp_sent');
            } else {
                $this->redirect('/billing/collections?success=whatsapp_sent');
            }
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao marcar WhatsApp como enviado: " . $e->getMessage());
            $this->redirect('/billing/collections?error=save_failed');
        }
    }

    /**
     * Central de Cobranças - Visão geral agrupada por tenant
     */
    public function overview(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        // Filtros
        $statusGeral = $_GET['status_geral'] ?? 'all';
        $semContatoRecente = isset($_GET['sem_contato_recente']) && $_GET['sem_contato_recente'] == '1';
        $diasSemContato = (int) ($_GET['dias_sem_contato'] ?? 7);

        // Query base agregada por tenant
        $sql = "
            SELECT 
                t.id as tenant_id,
                t.name as tenant_name,
                t.person_type,
                t.nome_fantasia,
                t.phone,
                t.billing_status,
                
                -- Valor em atraso
                COALESCE(SUM(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_overdue,
                
                -- Qtd faturas vencidas
                COUNT(CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN 1 END) as qtd_invoices_overdue,
                
                -- Valor vencendo hoje
                COALESCE(SUM(CASE WHEN bi.due_date = CURDATE() AND bi.status = 'pending' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_due_today,
                
                -- Valor vencendo próximos 7 dias
                COALESCE(SUM(CASE WHEN bi.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                                  AND bi.status = 'pending' 
                                  AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) 
                             THEN bi.amount ELSE 0 END), 0) as total_due_next_7d,
                
                -- Último contato WhatsApp (da tabela billing_invoices)
                MAX(bi.whatsapp_last_at) as last_whatsapp_contact,
                
                -- Último contato via billing_notifications (mais completo)
                MAX(bn.sent_at) as last_notification_sent
                
            FROM tenants t
            LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
            LEFT JOIN billing_notifications bn ON t.id = bn.tenant_id AND bn.status = 'sent_manual'
            WHERE t.status = 'active'
            GROUP BY t.id, t.name, t.person_type, t.nome_fantasia, t.phone, t.billing_status
        ";

        // Aplica filtros via HAVING
        $having = [];
        
        if ($statusGeral === 'em_atraso') {
            $having[] = "total_overdue > 0";
        } elseif ($statusGeral === 'vencendo_hoje') {
            $having[] = "total_due_today > 0";
        } elseif ($statusGeral === 'vencendo_7d') {
            $having[] = "total_due_next_7d > 0";
        } elseif ($statusGeral === 'all') {
            // Mostra todos que têm alguma cobrança em aberto
            $having[] = "(total_overdue > 0 OR qtd_invoices_overdue > 0 OR total_due_today > 0 OR total_due_next_7d > 0)";
        }

        // Filtro: sem contato recente
        if ($semContatoRecente) {
            // Cliente sem contato se ambos os campos forem NULL ou antigos
            $having[] = "((last_whatsapp_contact IS NULL OR last_whatsapp_contact < DATE_SUB(NOW(), INTERVAL {$diasSemContato} DAY)) 
                         AND (last_notification_sent IS NULL OR last_notification_sent < DATE_SUB(NOW(), INTERVAL {$diasSemContato} DAY)))";
        }

        if (!empty($having)) {
            $sql .= " HAVING " . implode(' AND ', $having);
        }

        $sql .= " ORDER BY total_overdue DESC, qtd_invoices_overdue DESC, total_due_next_7d DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $tenants = $stmt->fetchAll();

        $this->view('billing_collections.overview', [
            'tenants' => $tenants,
            'statusGeral' => $statusGeral,
            'semContatoRecente' => $semContatoRecente,
            'diasSemContato' => $diasSemContato,
        ]);
    }

    /**
     * Retorna JSON com dados para modal de cobrança agregada por cliente
     * 
     * GET /billing/tenant-reminder?tenant_id={id}
     */
    public function getTenantReminderData(): void
    {
        Auth::requireInternal();
        
        $tenantId = $_GET['tenant_id'] ?? null;
        if (!$tenantId) {
            $this->json(['error' => 'tenant_id obrigatório']);
            return;
        }
        
        $db = DB::getConnection();
        
        // Busca tenant
        $stmt = $db->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        $tenant = $stmt->fetch();
        
        if (!$tenant) {
            $this->json(['error' => 'Cliente não encontrado']);
            return;
        }
        
        // Busca faturas pendentes/vencidas (não deletadas)
        $stmt = $db->prepare("
            SELECT * FROM billing_invoices
            WHERE tenant_id = ?
              AND status IN ('pending', 'overdue')
              AND (is_deleted IS NULL OR is_deleted = 0)
            ORDER BY due_date ASC
        ");
        $stmt->execute([$tenantId]);
        $invoices = $stmt->fetchAll();
        
        if (empty($invoices)) {
            $this->json(['error' => 'Nenhuma cobrança pendente encontrada']);
            return;
        }
        
        // Monta mensagem
        $message = WhatsAppBillingService::buildReminderMessageForTenant($tenant, $invoices);
        
        // Normaliza telefone
        $phoneRaw = $tenant['phone'] ?? null;
        $phoneNormalized = WhatsAppBillingService::normalizePhone($phoneRaw);
        
        // Prepara link WhatsApp
        $whatsappLink = null;
        if ($phoneNormalized) {
            $messageEncoded = rawurlencode($message);
            $whatsappLink = "https://wa.me/{$phoneNormalized}?text={$messageEncoded}";
        }
        
        $this->json([
            'tenant' => [
                'id' => $tenant['id'],
                'name' => $tenant['name'],
                'nome_fantasia' => $tenant['nome_fantasia'] ?? null,
                'person_type' => $tenant['person_type'] ?? 'pf',
                'phone' => $phoneRaw,
                'phone_normalized' => $phoneNormalized,
            ],
            'invoices' => $invoices,
            'message' => $message,
            'whatsapp_link' => $whatsappLink,
        ]);
    }

    /**
     * Marca todas as faturas do cliente como "cobradas"
     * 
     * POST /billing/tenant-reminder-sent
     */
    public function markTenantReminderSent(): void
    {
        Auth::requireInternal();
        
        $tenantId = $_POST['tenant_id'] ?? null;
        $message = $_POST['message'] ?? '';
        $phone = $_POST['phone'] ?? null;
        
        if (!$tenantId) {
            $this->redirect('/billing/overview?error=missing_tenant_id');
            return;
        }
        
        $db = DB::getConnection();
        
        try {
            $db->beginTransaction();
            
            // Busca faturas pendentes/vencidas do tenant
            $stmt = $db->prepare("
                SELECT id FROM billing_invoices
                WHERE tenant_id = ?
                  AND status IN ('pending', 'overdue')
                  AND (is_deleted IS NULL OR is_deleted = 0)
            ");
            $stmt->execute([$tenantId]);
            $invoiceIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($invoiceIds)) {
                throw new \Exception('Nenhuma fatura pendente encontrada');
            }
            
            // Atualiza cada fatura
            $phoneNormalized = WhatsAppBillingService::normalizePhone($phone);
            
            foreach ($invoiceIds as $invoiceId) {
                // Atualiza fatura
                $stmt = $db->prepare("
                    UPDATE billing_invoices
                    SET whatsapp_last_at = NOW(),
                        whatsapp_total_messages = COALESCE(whatsapp_total_messages, 0) + 1,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$invoiceId]);
                
                // Cria notificação
                $stmt = $db->prepare("
                    INSERT INTO billing_notifications
                    (tenant_id, invoice_id, channel, template, status, message, phone_raw, phone_normalized, sent_at, created_at, updated_at)
                    VALUES (?, ?, 'whatsapp_web', 'bulk_reminder', 'sent_manual', ?, ?, ?, NOW(), NOW(), NOW())
                ");
                $stmt->execute([$tenantId, $invoiceId, $message, $phone, $phoneNormalized]);
            }
            
            $db->commit();
            
            $this->redirect('/billing/overview?success=reminder_sent');
        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Erro ao marcar lembrete como enviado: " . $e->getMessage());
            $this->redirect('/billing/overview?error=save_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Sincroniza todos os customers e faturas do Asaas
     * 
     * POST /billing/sync-all-from-asaas
     */
    public function syncAllFromAsaas(): void
    {
        Auth::requireInternal();

        try {
            // Executa sincronização
            $stats = \PixelHub\Services\AsaasBillingService::syncAllCustomersAndInvoices();

            // Prepara mensagem de sucesso
            $message = sprintf(
                'Sincronização concluída: %d clientes criados, %d atualizados, %d faturas criadas, %d faturas atualizadas.',
                $stats['created_customers'],
                $stats['updated_customers'],
                $stats['total_invoices_created'],
                $stats['total_invoices_updated']
            );

            if ($stats['skipped_customers'] > 0) {
                $message .= " ({$stats['skipped_customers']} clientes ignorados)";
            }

            if (!empty($stats['errors'])) {
                $errorCount = count($stats['errors']);
                $message .= " ({$errorCount} erro(s) - verifique os logs)";
                error_log("Erros na sincronização Asaas: " . implode('; ', $stats['errors']));
            }

            // Log completo
            error_log("Sincronização Asaas concluída: " . json_encode($stats));

            $this->redirect('/billing/overview?success=sync_completed&message=' . urlencode($message));
        } catch (\Exception $e) {
            error_log("Erro na sincronização completa do Asaas: " . $e->getMessage());
            $this->redirect('/billing/overview?error=sync_failed&message=' . urlencode($e->getMessage()));
        }
    }
}

