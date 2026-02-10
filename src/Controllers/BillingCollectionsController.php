<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\WhatsAppBillingService;
use PixelHub\Services\BillingSenderService;
use PixelHub\Services\BillingDispatchQueueService;

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
            $errorMsg = "Erro ao marcar WhatsApp como enviado: " . $e->getMessage();
            error_log($errorMsg);
            self::logFinancialError('whatsapp', $errorMsg, ['invoice_id' => $invoiceId]);
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
        $ordenacao = $_GET['ordenacao'] ?? 'mais_vencidas';

        // Query base agregada por tenant
        // CORREÇÃO: Usa subquery para notificações para evitar multiplicação de linhas no JOIN
        // O JOIN com billing_notifications estava causando duplicação na contagem de faturas
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
                
                -- Qtd faturas vencidas (usa COUNT DISTINCT para evitar duplicação)
                COUNT(DISTINCT CASE WHEN bi.status = 'overdue' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.id END) as qtd_invoices_overdue,
                
                -- Valor vencendo hoje
                COALESCE(SUM(CASE WHEN bi.due_date = CURDATE() AND bi.status = 'pending' AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) THEN bi.amount ELSE 0 END), 0) as total_due_today,
                
                -- Valor vencendo próximos 7 dias
                COALESCE(SUM(CASE WHEN bi.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                                  AND bi.status = 'pending' 
                                  AND (bi.is_deleted IS NULL OR bi.is_deleted = 0) 
                             THEN bi.amount ELSE 0 END), 0) as total_due_next_7d,
                
                -- Último contato WhatsApp (da tabela billing_invoices)
                MAX(bi.whatsapp_last_at) as last_whatsapp_contact,
                
                -- Último contato via billing_notifications (usando subquery para evitar JOIN multiplicador)
                (SELECT MAX(sent_at) FROM billing_notifications WHERE tenant_id = t.id AND status = 'sent_manual') as last_notification_sent,
                
                -- Dias em atraso (da fatura mais antiga vencida) - usando subquery para evitar problema no ORDER BY
                (SELECT MAX(DATEDIFF(CURDATE(), bi2.due_date)) 
                 FROM billing_invoices bi2 
                 WHERE bi2.tenant_id = t.id 
                   AND bi2.status = 'overdue' 
                   AND (bi2.is_deleted IS NULL OR bi2.is_deleted = 0)) as max_days_overdue
                
            FROM tenants t
            LEFT JOIN billing_invoices bi ON t.id = bi.tenant_id
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

        // Ordenação
        switch ($ordenacao) {
            case 'mais_vencidas':
                // Mais faturas vencidas primeiro
                $sql .= " ORDER BY qtd_invoices_overdue DESC, total_overdue DESC, COALESCE(max_days_overdue, 0) DESC";
                break;
            case 'menos_vencidas':
                // Menos faturas vencidas primeiro
                $sql .= " ORDER BY qtd_invoices_overdue ASC, total_overdue ASC, COALESCE(max_days_overdue, 0) ASC";
                break;
            case 'maior_valor':
                // Maior valor em atraso primeiro
                $sql .= " ORDER BY total_overdue DESC, qtd_invoices_overdue DESC, COALESCE(max_days_overdue, 0) DESC";
                break;
            case 'menor_valor':
                // Menor valor em atraso primeiro
                $sql .= " ORDER BY total_overdue ASC, qtd_invoices_overdue ASC, COALESCE(max_days_overdue, 0) ASC";
                break;
            case 'mais_antigo':
                // Mais dias em atraso primeiro (mais antigo)
                $sql .= " ORDER BY COALESCE(max_days_overdue, 0) DESC, qtd_invoices_overdue DESC, total_overdue DESC";
                break;
            case 'mais_recente':
                // Menos dias em atraso primeiro (mais recente)
                $sql .= " ORDER BY COALESCE(max_days_overdue, 0) ASC, qtd_invoices_overdue ASC, total_overdue ASC";
                break;
            default:
                // Padrão: mais vencidas
                $sql .= " ORDER BY qtd_invoices_overdue DESC, total_overdue DESC, COALESCE(max_days_overdue, 0) DESC";
        }

        // Conta total de registros (antes da paginação)
        // Usa subquery para contar corretamente após GROUP BY
        $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as counted";
        $countStmt = $db->query($countSql);
        $countResult = $countStmt->fetch();
        $total = (int)($countResult['total'] ?? 0);

        // Paginação
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $page = $page > 0 ? $page : 1;
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Aplica LIMIT e OFFSET
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $db->prepare($sql);
        $stmt->execute();
        $tenants = $stmt->fetchAll();

        // Calcula total de páginas
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        if ($page > $totalPages && $totalPages > 0) {
            $page = $totalPages;
            $offset = ($page - 1) * $perPage;
            $sql = preg_replace('/LIMIT \d+ OFFSET \d+$/', "LIMIT {$perPage} OFFSET {$offset}", $sql);
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $tenants = $stmt->fetchAll();
        }

        $this->view('billing_collections.overview', [
            'tenants' => $tenants,
            'statusGeral' => $statusGeral,
            'semContatoRecente' => $semContatoRecente,
            'diasSemContato' => $diasSemContato,
            'ordenacao' => $ordenacao,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
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
            $errorMsg = "Erro ao marcar lembrete como enviado: " . $e->getMessage();
            error_log($errorMsg);
            self::logFinancialError('reminder', $errorMsg, ['tenant_id' => $tenantId]);
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
                
                // Salva erros em arquivo de log específico
                $this->logSyncErrors($stats['errors']);
                
                error_log("Erros na sincronização Asaas: " . implode('; ', $stats['errors']));
            }

            // Log completo
            error_log("Sincronização Asaas concluída: " . json_encode($stats));

            $this->redirect('/billing/overview?success=sync_completed&message=' . urlencode($message));
        } catch (\Exception $e) {
            $errorMsg = "Erro na sincronização completa do Asaas: " . $e->getMessage();
            error_log($errorMsg);
            self::logFinancialError('sync', $errorMsg);
            $this->redirect('/billing/overview?error=sync_failed&message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Visualiza erros de sincronização
     * 
     * GET /billing/sync-errors
     */
    public function viewSyncErrors(): void
    {
        Auth::requireInternal();

        $logFile = __DIR__ . '/../../logs/asaas_sync_errors.log';
        $errors = [];

        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            // Pega as últimas 50 linhas
            $lines = array_slice($lines, -50);
            
            foreach ($lines as $line) {
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*(.+)$/', $line, $matches)) {
                    $errors[] = [
                        'timestamp' => $matches[1],
                        'message' => $matches[2]
                    ];
                }
            }
            $errors = array_reverse($errors); // Mais recentes primeiro
        }

        $this->view('billing_collections.sync_errors', [
            'errors' => $errors,
            'logFile' => $logFile,
        ]);
    }

    /**
     * Envia cobrança via Inbox (WhatsApp/Email) usando BillingSenderService
     * 
     * POST /billing/send-via-inbox
     */
    public function sendViaInbox(): void
    {
        Auth::requireInternal();

        $tenantId = $_POST['tenant_id'] ?? null;
        $invoiceIds = !empty($_POST['invoice_ids']) ? (array) $_POST['invoice_ids'] : null;
        $channel = $_POST['channel'] ?? null;
        $message = $_POST['message'] ?? null;
        $redirectTo = $_POST['redirect_to'] ?? 'collections';

        if (!$tenantId) {
            $this->jsonOrRedirect($redirectTo, false, 'tenant_id obrigatório', $tenantId);
            return;
        }

        $result = BillingSenderService::send([
            'tenant_id' => (int) $tenantId,
            'invoice_ids' => $invoiceIds,
            'channel' => $channel,
            'triggered_by' => 'manual',
            'message_override' => !empty($message) ? $message : null,
        ]);

        if ($result['success']) {
            $msg = "Cobrança enviada via Inbox! ({$result['invoices_count']} fatura(s))";
            if ($result['gateway_message_id']) {
                $msg .= " [msg_id: {$result['gateway_message_id']}]";
            }
            $this->jsonOrRedirect($redirectTo, true, $msg, $tenantId);
        } else {
            $this->jsonOrRedirect($redirectTo, false, $result['error'] ?? 'Erro desconhecido', $tenantId);
        }
    }

    /**
     * Atualiza configurações de cobrança automática do tenant
     * 
     * POST /billing/update-auto-settings
     */
    public function updateAutoSettings(): void
    {
        Auth::requireInternal();

        $tenantId = $_POST['tenant_id'] ?? null;
        $autoSend = isset($_POST['billing_auto_send']) ? 1 : 0;
        $autoChannel = $_POST['billing_auto_channel'] ?? 'whatsapp';

        if (!$tenantId) {
            $this->redirect('/billing/overview?error=missing_tenant_id');
            return;
        }

        // Valida canal
        $validChannels = ['whatsapp', 'email', 'both'];
        if (!in_array($autoChannel, $validChannels)) {
            $autoChannel = 'whatsapp';
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                UPDATE tenants
                SET billing_auto_send = ?,
                    billing_auto_channel = ?
                WHERE id = ?
            ");
            $stmt->execute([$autoSend, $autoChannel, $tenantId]);

            $redirectTab = $_POST['redirect_to'] ?? 'tenant';
            if ($redirectTab === 'tenant') {
                $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&success=auto_settings_saved');
            } else {
                $this->redirect('/billing/overview?success=auto_settings_saved');
            }
        } catch (\Exception $e) {
            error_log('[BILLING] Erro ao salvar auto settings: ' . $e->getMessage());
            $this->redirect('/tenants/view?id=' . $tenantId . '&tab=financial&error=save_failed');
        }
    }

    /**
     * Tela de auditoria de envios de cobrança
     * 
     * GET /billing/notifications-log
     */
    public function notificationsLog(): void
    {
        Auth::requireInternal();

        $db = DB::getConnection();

        $tenantId = $_GET['tenant_id'] ?? null;
        $status = $_GET['status'] ?? 'all';
        $channel = $_GET['channel'] ?? 'all';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($tenantId) {
            $where[] = 'bn.tenant_id = ?';
            $params[] = (int) $tenantId;
        }
        if ($status !== 'all') {
            $where[] = 'bn.status = ?';
            $params[] = $status;
        }
        if ($channel !== 'all') {
            $where[] = 'bn.channel = ?';
            $params[] = $channel;
        }

        $whereStr = implode(' AND ', $where);

        // Total
        $countStmt = $db->prepare("SELECT COUNT(*) FROM billing_notifications bn WHERE {$whereStr}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Dados
        $stmt = $db->prepare("
            SELECT bn.*, t.name AS tenant_name, bi.due_date, bi.amount, bi.description AS invoice_description
            FROM billing_notifications bn
            LEFT JOIN tenants t ON t.id = bn.tenant_id
            LEFT JOIN billing_invoices bi ON bi.id = bn.invoice_id
            WHERE {$whereStr}
            ORDER BY bn.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $notifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Contagem de falhas recentes (últimas 24h)
        $failStmt = $db->query("SELECT COUNT(*) FROM billing_notifications WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $recentFailures = (int) $failStmt->fetchColumn();

        // Resumo da fila do dia
        $queueSummary = BillingDispatchQueueService::todaySummary();

        // Jobs pendentes na fila (para exibição detalhada)
        $queueJobs = $db->query("
            SELECT bdq.*, t.name AS tenant_name
            FROM billing_dispatch_queue bdq
            LEFT JOIN tenants t ON t.id = bdq.tenant_id
            WHERE DATE(bdq.created_at) = CURDATE()
            ORDER BY bdq.scheduled_at ASC
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->view('billing_collections.notifications_log', [
            'notifications' => $notifications,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'tenantId' => $tenantId,
            'status' => $status,
            'channel' => $channel,
            'recentFailures' => $recentFailures,
            'queueSummary' => $queueSummary,
            'queueJobs' => $queueJobs,
        ]);
    }

    /**
     * Retorna contagem de falhas recentes (para badge no menu)
     * 
     * GET /billing/failure-count
     */
    public function failureCount(): void
    {
        Auth::requireInternal();
        $db = DB::getConnection();
        $stmt = $db->query("SELECT COUNT(*) FROM billing_notifications WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $count = (int) $stmt->fetchColumn();
        $this->json(['count' => $count]);
    }

    /**
     * Helper: responde JSON ou redireciona dependendo do contexto
     */
    private function jsonOrRedirect(string $redirectTo, bool $success, string $message, ?int $tenantId = null): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $acceptsJson = isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;

        if ($isAjax || $acceptsJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
            ]);
            return;
        }

        $param = $success ? 'success' : 'error';
        $encodedMsg = urlencode($message);

        if ($redirectTo === 'tenant' && $tenantId) {
            $this->redirect("/tenants/view?id={$tenantId}&tab=financial&{$param}=inbox_send&message={$encodedMsg}");
        } elseif ($redirectTo === 'overview') {
            $this->redirect("/billing/overview?{$param}=inbox_send&message={$encodedMsg}");
        } else {
            $this->redirect("/billing/collections?{$param}=inbox_send&message={$encodedMsg}");
        }
    }

    /**
     * Salva erros de sincronização em arquivo de log específico
     */
    private function logSyncErrors(array $errors): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/asaas_sync_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logContent = "\n[{$timestamp}] Sincronização Asaas - Erros detectados:\n";
        foreach ($errors as $index => $error) {
            $logContent .= "[{$timestamp}] Erro #" . ($index + 1) . ": {$error}\n";
        }
        $logContent .= "[{$timestamp}] ---\n";

        file_put_contents($logFile, $logContent, FILE_APPEND);
    }

    /**
     * Log genérico de erros financeiros (para uso em outros métodos)
     */
    public static function logFinancialError(string $category, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/financial_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $contextStr = !empty($context) ? ' | Contexto: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logContent = "[{$timestamp}] [{$category}] {$message}{$contextStr}\n";

        file_put_contents($logFile, $logContent, FILE_APPEND);
    }

    /**
     * Envia cobrança manualmente
     */
    public function sendManual(): void
    {
        Auth::requireInternal();
        
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $channel = $_POST['channel'] ?? 'whatsapp';
        $reason = $_POST['reason'] ?? 'Envio manual solicitado';
        $isForced = isset($_POST['is_forced']) && $_POST['is_forced'] === '1';
        $forceReason = $isForced ? ($_POST['force_reason'] ?? '') : null;

        if (!$invoiceId) {
            $this->json(['success' => false, 'error' => 'Fatura não informada']);
            return;
        }

        $db = DB::getConnection();
        
        // Busca dados da fatura
        $stmt = $db->prepare("
            SELECT bi.*, t.name as tenant_name, t.billing_whatsapp, t.billing_email
            FROM billing_invoices bi
            JOIN tenants t ON bi.tenant_id = t.id
            WHERE bi.id = ? AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$invoice) {
            $this->json(['success' => false, 'error' => 'Fatura não encontrada']);
            return;
        }

        // Verifica se canal está disponível
        if ($channel === 'whatsapp' && empty($invoice['billing_whatsapp'])) {
            $this->json(['success' => false, 'error' => 'WhatsApp não configurado para este cliente']);
            return;
        }

        if ($channel === 'email' && empty($invoice['billing_email'])) {
            $this->json(['success' => false, 'error' => 'E-mail não configurado para este cliente']);
            return;
        }

        $user = Auth::user();
        $templateKey = $this->getTemplateKey($invoice);

        // Enfileira envio manual
        $queueId = BillingDispatchQueueService::enqueueManual(
            $invoice['tenant_id'],
            [$invoiceId],
            $channel,
            $templateKey,
            $user['id'],
            $reason,
            $isForced,
            $forceReason
        );

        if (!$queueId) {
            $this->json(['success' => false, 'error' => 'Envio em cooldown. Use "Forçar envio" se necessário.']);
            return;
        }

        $this->json([
            'success' => true,
            'message' => 'Cobrança enfileirada para envio imediato',
            'queue_id' => $queueId
        ]);
    }

    /**
     * Busca último envio da fatura
     */
    public function getLastDispatch(): void
    {
        Auth::requireInternal();
        
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);
        $channel = $_GET['channel'] ?? null;

        if (!$invoiceId) {
            $this->json(['success' => false, 'error' => 'Fatura não informada']);
            return;
        }

        $lastDispatch = BillingDispatchQueueService::getLastDispatch($invoiceId, $channel);

        if ($lastDispatch) {
            $this->json([
                'success' => true,
                'data' => [
                    'sent_at' => $lastDispatch['sent_at'],
                    'channel' => $lastDispatch['channel'],
                    'trigger_source' => $lastDispatch['trigger_source'],
                    'user_name' => $lastDispatch['user_name'],
                    'is_forced' => $lastDispatch['is_forced'],
                    'force_reason' => $lastDispatch['force_reason']
                ]
            ]);
        } else {
            $this->json(['success' => true, 'data' => null]);
        }
    }

    /**
     * Preview da mensagem de cobrança
     */
    public function previewMessage(): void
    {
        Auth::requireInternal();
        
        $invoiceId = (int) ($_GET['invoice_id'] ?? 0);
        $channel = $_GET['channel'] ?? 'whatsapp';

        if (!$invoiceId) {
            $this->json(['success' => false, 'error' => 'Fatura não informada']);
            return;
        }

        $db = DB::getConnection();
        
        // Busca dados da fatura e tenant
        $stmt = $db->prepare("
            SELECT bi.*, t.name as tenant_name, t.nome_fantasia, t.person_type,
                   t.billing_whatsapp, t.billing_email
            FROM billing_invoices bi
            JOIN tenants t ON bi.tenant_id = t.id
            WHERE bi.id = ? AND (bi.is_deleted IS NULL OR bi.is_deleted = 0)
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$invoice) {
            $this->json(['success' => false, 'error' => 'Fatura não encontrada']);
            return;
        }

        // Prepara dados do tenant
        $tenant = [
            'name' => $invoice['tenant_name'],
            'nome_fantasia' => $invoice['nome_fantasia'],
            'person_type' => $invoice['person_type'],
            'billing_whatsapp' => $invoice['billing_whatsapp'],
            'billing_email' => $invoice['billing_email']
        ];

        // Determina o estágio/template
        $stageInfo = \PixelHub\Services\WhatsAppBillingService::suggestStageForInvoice($invoice);
        $stage = $stageInfo['stage'];

        // Monta a mensagem
        try {
            if ($channel === 'whatsapp') {
                $message = \PixelHub\Services\WhatsAppBillingService::buildMessageForInvoice($tenant, $invoice, $stage);
            } else {
                // Para email, usa uma versão mais simples
                $message = $this->buildEmailMessage($tenant, $invoice, $stage);
            }

            $this->json([
                'success' => true,
                'message' => $message,
                'stage' => $stage,
                'stage_label' => $stageInfo['label']
            ]);
        } catch (\Exception $e) {
            error_log("Erro ao montar mensagem de preview: " . $e->getMessage());
            $this->json([
                'success' => false,
                'error' => 'Erro ao montar mensagem: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Monta mensagem para email (versão simplificada)
     */
    private function buildEmailMessage(array $tenant, array $invoice, string $stage): string
    {
        $tenantName = $tenant['nome_fantasia'] ?? $tenant['name'];
        $amount = number_format($invoice['amount'], 2, ',', '.');
        $dueDate = (new \DateTime($invoice['due_date']))->format('d/m/Y');
        
        $subject = "Cobrança - Fatura #{$invoice['id']} - {$tenantName}";
        
        $message = "Olá {$tenantName},\n\n";
        $message .= "Gostaríamos de lembrar sobre sua fatura:\n\n";
        $message .= "Fatura: #{$invoice['id']}\n";
        $message .= "Valor: R$ {$amount}\n";
        $message .= "Vencimento: {$dueDate}\n";
        $message .= "Status: " . ($invoice['status'] === 'paid' ? 'Paga' : 'Pendente') . "\n\n";
        
        if ($invoice['status'] !== 'paid') {
            $message .= "Por favor, regularize o pagamento para evitar juros.\n\n";
            $message .= "Para acessar a fatura: " . pixelhub_url("/billing/view_invoice?id={$invoice['id']}") . "\n\n";
        }
        
        $message .= "Atenciosamente,\n";
        $message .= "Equipe Pixel12 Digital";
        
        return $message;
    }

    /**
     * Determina template key baseado na fatura
     */
    private function getTemplateKey(array $invoice): string
    {
        $daysOverdue = $invoice['status'] === 'overdue' 
            ? (int) ((new \DateTime())->diff(new \DateTime($invoice['due_date']))->days)
            : 0;

        if ($invoice['status'] === 'pending') {
            $daysToDue = (int) ((new \DateTime($invoice['due_date']))->diff(new \DateTime())->days);
            if ($daysToDue <= 3) {
                return 'due_soon_3d';
            }
            return 'pending';
        }

        if ($daysOverdue <= 3) {
            return 'overdue_3d';
        } elseif ($daysOverdue <= 7) {
            return 'overdue_7d';
        } elseif ($daysOverdue <= 15) {
            return 'overdue_15d';
        } else {
            return 'overdue_30d';
        }
    }
}

