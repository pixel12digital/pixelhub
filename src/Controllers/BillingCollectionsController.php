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
}

