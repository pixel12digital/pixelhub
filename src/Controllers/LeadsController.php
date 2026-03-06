<?php

namespace PixelHub\Controllers;

use PixelHub\Core\Controller;
use PixelHub\Core\Auth;
use PixelHub\Core\DB;
use PixelHub\Services\LeadService;
use PixelHub\Services\OriginCatalog;

/**
 * Controller para gerenciar leads (tabela leads legada)
 */
class LeadsController extends Controller
{
    /**
     * Lista todos os leads com filtros e paginação
     * GET /leads
     */
    public function index(): void
    {
        Auth::requireInternal();

        $search       = trim($_GET['search'] ?? '');
        $statusFilter = trim($_GET['status'] ?? 'active');
        $sourceFilter = trim($_GET['source'] ?? '');
        $page         = max(1, (int) ($_GET['page'] ?? 1));
        $perPage      = 25;
        $offset       = ($page - 1) * $perPage;

        $result     = $this->searchWithPagination($search, $statusFilter, $sourceFilter, $perPage, $offset);
        $leads      = $result['items'];
        $total      = $result['total'];
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        if ($page > $totalPages && $totalPages > 0) {
            $page   = $totalPages;
            $offset = ($page - 1) * $perPage;
            $result = $this->searchWithPagination($search, $statusFilter, $sourceFilter, $perPage, $offset);
            $leads  = $result['items'];
        }

        $isAjax = isset($_GET['ajax']) && $_GET['ajax'] === '1';

        if ($isAjax) {
            $viewData = compact('leads', 'search', 'statusFilter', 'sourceFilter', 'page', 'totalPages');

            ob_start();
            extract($viewData);
            require __DIR__ . '/../../views/leads/_table_rows.php';
            $tableRowsHtml = ob_get_clean();

            ob_start();
            extract($viewData);
            require __DIR__ . '/../../views/leads/_pagination.php';
            $paginationHtml = ob_get_clean();

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'html'           => $tableRowsHtml,
                'paginationHtml' => $paginationHtml,
                'total'          => $total,
                'page'           => $page,
                'totalPages'     => $totalPages,
            ]);
            return;
        }

        $sources = OriginCatalog::getForSelect('');

        $this->view('leads.index', compact(
            'leads', 'search', 'statusFilter', 'sourceFilter',
            'page', 'perPage', 'total', 'totalPages', 'sources'
        ));
    }

    /**
     * Salva novo lead (form submit)
     * POST /leads/store
     */
    public function store(): void
    {
        Auth::requireInternal();

        $name    = trim($_POST['name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $source  = trim($_POST['source'] ?? 'crm_manual');
        $notes   = trim($_POST['notes'] ?? '');

        if (empty($phone) && empty($email)) {
            $this->redirect('/leads?error=contact_required');
            return;
        }

        try {
            $id = LeadService::create([
                'name'       => $name ?: null,
                'company'    => $company ?: null,
                'phone'      => $phone ?: null,
                'email'      => $email ?: null,
                'source'     => $source ?: 'crm_manual',
                'notes'      => $notes ?: null,
                'created_by' => Auth::user()['id'] ?? null,
            ]);
            $this->redirect('/leads/edit?id=' . $id . '&success=lead_created&back=' . urlencode('/leads'));
        } catch (\Exception $e) {
            error_log('[Leads] Erro ao criar lead: ' . $e->getMessage());
            $this->redirect('/leads?error=database_error');
        }
    }

    /**
     * Pesquisa leads com paginação
     */
    private function searchWithPagination(
        string $search,
        string $statusFilter,
        string $sourceFilter,
        int $limit,
        int $offset
    ): array {
        $db     = DB::getConnection();
        $where  = [];
        $params = [];

        if ($statusFilter === 'active') {
            $where[] = "status NOT IN ('converted', 'lost')";
        } elseif ($statusFilter !== 'all') {
            $where[] = "status = ?";
            $params[] = $statusFilter;
        }

        if (!empty($sourceFilter)) {
            $where[] = "source = ?";
            $params[] = $sourceFilter;
        }

        if (!empty($search)) {
            $term   = '%' . $search . '%';
            $digits = preg_replace('/[^0-9]/', '', $search);
            if (!empty($digits)) {
                $where[] = "(name LIKE ? OR company LIKE ? OR email LIKE ? OR REPLACE(REPLACE(REPLACE(phone,'(',''),')',''),'-','') LIKE ?)";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = '%' . $digits . '%';
            } else {
                $where[] = "(name LIKE ? OR company LIKE ? OR email LIKE ?)";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $db->prepare("SELECT COUNT(*) FROM leads {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataParams   = array_merge($params, [$limit, $offset]);
        $dataStmt     = $db->prepare("
            SELECT l.*,
                   (SELECT COUNT(*) FROM opportunities o WHERE o.lead_id = l.id) AS opp_count
            FROM leads l
            {$whereSql}
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $dataStmt->execute($dataParams);
        $items = $dataStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return compact('items', 'total');
    }

    /**
     * Formulário de edição de lead
     * GET /leads/edit?id=X
     */
    public function edit(): void
    {
        Auth::requireInternal();

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $db = DB::getConnection();
        
        try {
            $stmt = $db->prepare("SELECT * FROM leads WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $lead = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log('[Leads] Erro ao buscar lead #' . $id . ': ' . $e->getMessage());
            $this->redirect('/opportunities?error=database_error');
            return;
        }

        if (!$lead) {
            $this->redirect('/opportunities?error=lead_not_found');
            return;
        }

        // Busca oportunidades vinculadas a este lead
        $opportunities = [];
        try {
            $stmt = $db->prepare("SELECT id, name, stage, status FROM opportunities WHERE lead_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$id]);
            $opportunities = $stmt->fetchAll() ?: [];
        } catch (\Exception $e) {
            error_log('[Leads] Erro ao buscar oportunidades do lead #' . $id . ': ' . $e->getMessage());
        }

        // Determina URL de voltar: ?back= tem prioridade; senão, verifica prospecção; senão, /opportunities
        $backParam = trim($_GET['back'] ?? '');
        $tenantId  = null;

        if (!empty($backParam)) {
            $backUrl = pixelhub_url($backParam);
        } else {
            $backUrl = pixelhub_url('/opportunities');
            try {
                $stmt = $db->prepare("SELECT tenant_id FROM prospecting_results WHERE lead_id = ? AND tenant_id IS NOT NULL LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!empty($row['tenant_id'])) {
                    $tenantId = (int) $row['tenant_id'];
                    $backUrl  = pixelhub_url('/opportunities?tenant_id=' . $tenantId);
                }
            } catch (\Exception $e) {
                // ignora — usa backUrl padrão
            }
        }

        $this->view('leads.edit', [
            'lead'          => $lead,
            'opportunities' => $opportunities,
            'backUrl'       => $backUrl,
            'tenantId'      => $tenantId,
        ]);
    }

    /**
     * Atualiza lead (form submit)
     * POST /leads/update
     */
    public function update(): void
    {
        Auth::requireInternal();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $name = trim($_POST['name'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $source = trim($_POST['source'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Validação básica
        if (empty($phone) && empty($email)) {
            $this->redirect('/leads/edit?id=' . $id . '&error=contact_required');
            return;
        }

        $db = DB::getConnection();

        try {
            $stmt = $db->prepare("
                UPDATE leads 
                SET name = ?, company = ?, phone = ?, email = ?, source = ?, notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $company, $phone, $email, $source, $notes, $id]);

            // Redireciona de volta para a oportunidade ou lista de oportunidades
            $redirectUrl = $_POST['redirect_url'] ?? '/opportunities';
            $this->redirect($redirectUrl . '?success=lead_updated');
        } catch (\Exception $e) {
            error_log("[Leads] Erro ao atualizar lead #{$id}: " . $e->getMessage());
            $this->redirect('/leads/edit?id=' . $id . '&error=database_error');
        }
    }

    /**
     * Exclui um lead
     * POST /leads/delete
     */
    public function delete(): void
    {
        Auth::requireInternal();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            $this->redirect('/opportunities?error=invalid_lead');
            return;
        }

        $result = LeadService::delete($id);

        if ($result['success']) {
            $redirectUrl = $_POST['redirect_url'] ?? '/opportunities';
            $this->redirect($redirectUrl . '?success=lead_deleted');
        } else {
            $errorParam = urlencode($result['error']);
            $this->redirect('/leads/edit?id=' . $id . '&error=delete_failed&message=' . $errorParam);
        }
    }
}
